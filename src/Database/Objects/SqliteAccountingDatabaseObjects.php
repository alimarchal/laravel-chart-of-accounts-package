<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Objects;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SqliteAccountingDatabaseObjects implements AccountingDatabaseObjects
{
    public function sync(): void
    {
        $this->drop();
        $this->createAuditTriggers();
        $this->createImmutabilityTriggers();
        $this->createChartGuards();

        DB::statement(str_replace('je.company_id', 'je.company_id'.$this->ledgerDocumentColumns(), <<<'SQL'
            CREATE VIEW IF NOT EXISTS vw_accounting_general_ledger AS
            SELECT
                je.id AS journal_entry_id,
                je.entry_date,
                je.reference,
                je.description AS journal_description,
                je.status,
                coa.id AS account_id,
                coa.account_code,
                coa.account_name,
                jed.line_no,
                jed.debit,
                jed.credit,
                jed.base_debit,
                jed.base_credit,
                jed.description AS line_description,
                cc.code AS cost_center_code,
                cc.name AS cost_center_name,
                c.code AS currency_code,
                je.fx_rate_to_base,
                je.company_id
            FROM accounting_journal_entry_lines jed
            JOIN accounting_journal_entries je ON je.id = jed.journal_entry_id
            JOIN accounting_chart_of_accounts coa ON coa.id = jed.chart_of_account_id
            LEFT JOIN accounting_cost_centers cc ON cc.id = jed.cost_center_id
            LEFT JOIN accounting_currencies c ON c.id = je.currency_id
        SQL));

        DB::statement(<<<'SQL'
            CREATE VIEW IF NOT EXISTS vw_accounting_trial_balance AS
            SELECT
                coa.company_id,
                coa.id AS account_id,
                coa.account_code,
                coa.account_name,
                at.name AS account_type,
                at.report_group,
                coa.normal_balance,
                COALESCE(SUM(CASE WHEN je.status = 'posted' THEN jed.base_debit ELSE 0 END), 0) AS total_debits,
                COALESCE(SUM(CASE WHEN je.status = 'posted' THEN jed.base_credit ELSE 0 END), 0) AS total_credits,
                CASE
                    WHEN coa.normal_balance = 'debit' THEN COALESCE(SUM(CASE WHEN je.status = 'posted' THEN jed.base_debit - jed.base_credit ELSE 0 END), 0)
                    ELSE COALESCE(SUM(CASE WHEN je.status = 'posted' THEN jed.base_credit - jed.base_debit ELSE 0 END), 0)
                END AS balance
            FROM accounting_chart_of_accounts coa
            JOIN accounting_account_types at ON at.id = coa.account_type_id
            LEFT JOIN accounting_journal_entry_lines jed ON jed.chart_of_account_id = coa.id
            LEFT JOIN accounting_journal_entries je ON je.id = jed.journal_entry_id
            WHERE coa.is_active = 1 OR je.id IS NOT NULL
            GROUP BY coa.company_id, coa.id, coa.account_code, coa.account_name, at.name, at.report_group, coa.normal_balance
        SQL);

        DB::statement("CREATE VIEW IF NOT EXISTS vw_accounting_balance_sheet AS SELECT * FROM vw_accounting_trial_balance WHERE report_group = 'BalanceSheet'");
        DB::statement("CREATE VIEW IF NOT EXISTS vw_accounting_income_statement AS SELECT * FROM vw_accounting_trial_balance WHERE report_group = 'IncomeStatement'");
    }

    public function drop(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_accounting_income_statement');
        DB::statement('DROP VIEW IF EXISTS vw_accounting_balance_sheet');
        DB::statement('DROP VIEW IF EXISTS vw_accounting_trial_balance');
        DB::statement('DROP VIEW IF EXISTS vw_accounting_general_ledger');
        foreach ($this->auditedTables() as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_insert");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_update");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_delete");
        }
        foreach ([...$this->immutabilityTriggers(), ...$this->chartGuardTriggers()] as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function jsonRow(string $table, string $row): string
    {
        $pairs = collect(Schema::getColumnListing($table))
            ->map(fn (string $column) => "'{$column}', {$row}.\"{$column}\"")
            ->implode(', ');

        return "json_object({$pairs})";
    }

    private function createAuditTriggers(): void
    {
        foreach ($this->auditedTables() as $table) {
            $newCompany = $this->companyOf($table, 'NEW');
            $oldCompany = $this->companyOf($table, 'OLD');
            $new = $this->jsonRow($table, 'NEW');
            $old = $this->jsonRow($table, 'OLD');

            DB::statement("CREATE TRIGGER {$table}_audit_insert AFTER INSERT ON {$table} BEGIN INSERT INTO accounting_audit_logs (company_id, table_name, record_id, action, new_values, metadata, created_at) VALUES ({$newCompany}, '{$table}', NEW.id, 'insert', {$new}, json_object('source', 'database_trigger'), datetime('now')); END");
            DB::statement("CREATE TRIGGER {$table}_audit_update AFTER UPDATE ON {$table} BEGIN INSERT INTO accounting_audit_logs (company_id, table_name, record_id, action, old_values, new_values, metadata, created_at) VALUES ({$newCompany}, '{$table}', NEW.id, 'update', {$old}, {$new}, json_object('source', 'database_trigger'), datetime('now')); END");
            DB::statement("CREATE TRIGGER {$table}_audit_delete AFTER DELETE ON {$table} BEGIN INSERT INTO accounting_audit_logs (company_id, table_name, record_id, action, old_values, metadata, created_at) VALUES ({$oldCompany}, '{$table}', OLD.id, 'delete', {$old}, json_object('source', 'database_trigger'), datetime('now')); END");
        }
    }

    /**
     * @return array<int, string>
     */
    private function immutabilityTriggers(): array
    {
        return [
            'acct_journals_posted_guard_update',
            'acct_journals_posted_guard_delete',
            'acct_lines_posted_guard_insert',
            'acct_lines_posted_guard_update',
            'acct_lines_posted_guard_delete',
        ];
    }

    /**
     * Posted journal entries (and their lines) are immutable at the database layer. Only the
     * reversal / closing / reconciliation bookkeeping columns may still change.
     */
    private function createImmutabilityTriggers(): void
    {
        $abort = "SELECT RAISE(ABORT, 'Posted journal entries are immutable; reverse them instead.')";
        $voucher = Schema::hasColumn('accounting_journal_entries', 'voucher_number')
            ? 'OR NEW.voucher_number IS NOT OLD.voucher_number OR NEW.voucher_type_id IS NOT OLD.voucher_type_id'
            : '';

        if (Schema::hasColumn('accounting_journal_entries', 'source_document_number')) {
            $voucher .= ' OR NEW.source_document_type IS NOT OLD.source_document_type OR NEW.source_document_number IS NOT OLD.source_document_number OR NEW.source_document_date IS NOT OLD.source_document_date OR NEW.sourceable_type IS NOT OLD.sourceable_type OR NEW.sourceable_id IS NOT OLD.sourceable_id';
        }

        if (Schema::hasColumn('accounting_journal_entries', 'origin_module')) {
            $voucher .= ' OR NEW.origin_module IS NOT OLD.origin_module';
        }
        $parentPosted = fn (string $row) => "(SELECT status FROM accounting_journal_entries WHERE id = {$row}.journal_entry_id) = 'posted'";

        DB::statement("CREATE TRIGGER acct_journals_posted_guard_update BEFORE UPDATE ON accounting_journal_entries
            WHEN OLD.status = 'posted' AND (NEW.status <> 'posted' OR NEW.entry_date IS NOT OLD.entry_date OR NEW.currency_id IS NOT OLD.currency_id
                OR NEW.company_id IS NOT OLD.company_id
                {$voucher}
                OR NEW.fx_rate_to_base IS NOT OLD.fx_rate_to_base OR NEW.deleted_at IS NOT OLD.deleted_at)
            BEGIN {$abort}; END");
        DB::statement("CREATE TRIGGER acct_journals_posted_guard_delete BEFORE DELETE ON accounting_journal_entries
            WHEN OLD.status = 'posted' BEGIN {$abort}; END");
        DB::statement("CREATE TRIGGER acct_lines_posted_guard_insert BEFORE INSERT ON accounting_journal_entry_lines
            WHEN {$parentPosted('NEW')} BEGIN {$abort}; END");
        DB::statement("CREATE TRIGGER acct_lines_posted_guard_update BEFORE UPDATE ON accounting_journal_entry_lines
            WHEN {$parentPosted('OLD')} AND (NEW.debit IS NOT OLD.debit OR NEW.credit IS NOT OLD.credit
                OR NEW.base_debit IS NOT OLD.base_debit OR NEW.base_credit IS NOT OLD.base_credit
                OR NEW.chart_of_account_id IS NOT OLD.chart_of_account_id OR NEW.journal_entry_id IS NOT OLD.journal_entry_id)
            BEGIN {$abort}; END");
        DB::statement("CREATE TRIGGER acct_lines_posted_guard_delete BEFORE DELETE ON accounting_journal_entry_lines
            WHEN {$parentPosted('OLD')} BEGIN {$abort}; END");
    }

    /**
     * @return array<int, string>
     */
    private function chartGuardTriggers(): array
    {
        return [
            'acct_coa_guard_insert',
            'acct_coa_guard_company',
            'acct_coa_guard_used',
            'acct_coa_guard_children',
            'acct_coa_guard_child_type',
            'acct_coa_guard_parent',
            'acct_coa_guard_cycle',
            'acct_lines_company_guard_insert',
            'acct_lines_company_guard_update',
            'acct_journals_group_guard',
        ];
    }

    /**
     * Chart-of-accounts integrity at the database layer (the application enforces the same rules with
     * friendlier messages): parents are group accounts of the same type and company, no cycles, the
     * meaning of an account with journal lines cannot change, groups with children stay groups,
     * journal lines stay in their entry's company, and only posting accounts can be posted to.
     */
    private function createChartGuards(): void
    {
        // Created once the multi-company migration has added company_id.
        if (! Schema::hasColumn('accounting_chart_of_accounts', 'company_id')) {
            return;
        }

        $abort = fn (string $message) => "SELECT RAISE(ABORT, '{$message}')";
        $badParent = 'NEW.parent_id IS NOT NULL AND NOT EXISTS (
            SELECT 1 FROM accounting_chart_of_accounts p
            WHERE p.id = NEW.parent_id AND p.is_group = 1 AND p.account_type_id = NEW.account_type_id AND p.company_id IS NEW.company_id
        )';
        $parentMessage = 'The parent account must be a group account of the same type and company.';

        DB::statement("CREATE TRIGGER acct_coa_guard_insert BEFORE INSERT ON accounting_chart_of_accounts
            WHEN {$badParent} BEGIN {$abort($parentMessage)}; END");
        DB::statement("CREATE TRIGGER acct_coa_guard_company BEFORE UPDATE ON accounting_chart_of_accounts
            WHEN NEW.company_id IS NOT OLD.company_id BEGIN {$abort('An account cannot move to another company.')}; END");
        DB::statement("CREATE TRIGGER acct_coa_guard_used BEFORE UPDATE ON accounting_chart_of_accounts
            WHEN (NEW.account_type_id IS NOT OLD.account_type_id OR NEW.normal_balance IS NOT OLD.normal_balance OR NEW.is_group IS NOT OLD.is_group)
                AND EXISTS (SELECT 1 FROM accounting_journal_entry_lines WHERE chart_of_account_id = OLD.id)
            BEGIN {$abort('An account with journal lines: its type, normal balance and group flag cannot change.')}; END");
        DB::statement("CREATE TRIGGER acct_coa_guard_children BEFORE UPDATE ON accounting_chart_of_accounts
            WHEN OLD.is_group = 1 AND NEW.is_group = 0 AND EXISTS (SELECT 1 FROM accounting_chart_of_accounts WHERE parent_id = OLD.id)
            BEGIN {$abort('A group account with child accounts cannot become a posting account.')}; END");
        DB::statement("CREATE TRIGGER acct_coa_guard_child_type BEFORE UPDATE ON accounting_chart_of_accounts
            WHEN NEW.account_type_id IS NOT OLD.account_type_id
                AND EXISTS (SELECT 1 FROM accounting_chart_of_accounts WHERE parent_id = OLD.id AND account_type_id <> NEW.account_type_id)
            BEGIN {$abort('Child accounts must have the same type as their group.')}; END");
        DB::statement("CREATE TRIGGER acct_coa_guard_parent BEFORE UPDATE ON accounting_chart_of_accounts
            WHEN {$badParent} BEGIN {$abort($parentMessage)}; END");

        // SQLite triggers cannot use recursive CTEs: walk up to 20 ancestor levels with joins.
        $levels = 20;
        $joins = collect(range(2, $levels))
            ->map(fn (int $i) => 'LEFT JOIN accounting_chart_of_accounts a'.$i.' ON a'.$i.'.id = a'.($i - 1).'.parent_id')
            ->implode(' ');
        $ids = collect(range(1, $levels))->map(fn (int $i) => "a{$i}.id")->implode(', ');

        DB::statement("CREATE TRIGGER acct_coa_guard_cycle BEFORE UPDATE OF parent_id ON accounting_chart_of_accounts
            WHEN NEW.parent_id IS NOT NULL AND NEW.parent_id IS NOT OLD.parent_id AND EXISTS (
                SELECT 1 FROM accounting_chart_of_accounts a1 {$joins} WHERE a1.id = NEW.parent_id AND NEW.id IN ({$ids})
            )
            BEGIN {$abort('An account cannot be placed under itself or one of its own sub-accounts.')}; END");

        $otherCompany = '(SELECT company_id FROM accounting_chart_of_accounts WHERE id = NEW.chart_of_account_id)
            IS NOT (SELECT company_id FROM accounting_journal_entries WHERE id = NEW.journal_entry_id)';
        $lineMessage = 'A journal line must use an account of its entry company.';

        DB::statement("CREATE TRIGGER acct_lines_company_guard_insert BEFORE INSERT ON accounting_journal_entry_lines
            WHEN {$otherCompany} BEGIN {$abort($lineMessage)}; END");
        DB::statement("CREATE TRIGGER acct_lines_company_guard_update BEFORE UPDATE OF chart_of_account_id, journal_entry_id ON accounting_journal_entry_lines
            WHEN {$otherCompany} BEGIN {$abort($lineMessage)}; END");

        DB::statement("CREATE TRIGGER acct_journals_group_guard BEFORE UPDATE OF status ON accounting_journal_entries
            WHEN NEW.status = 'posted' AND OLD.status <> 'posted' AND EXISTS (
                SELECT 1 FROM accounting_journal_entry_lines l
                JOIN accounting_chart_of_accounts a ON a.id = l.chart_of_account_id
                WHERE l.journal_entry_id = NEW.id AND a.is_group = 1
            )
            BEGIN {$abort('Journal lines can only post to posting (non-group) accounts.')}; END");
    }

    /**
     * SQL for the company an audited row belongs to: its own company_id, its journal entry's for
     * lines, NULL for shared tables (currencies).
     */
    private function companyOf(string $table, string $row): string
    {
        return match ($table) {
            'accounting_currencies' => 'NULL',
            'accounting_journal_entry_lines' => "(SELECT company_id FROM accounting_journal_entries WHERE id = {$row}.journal_entry_id)",
            default => "{$row}.company_id",
        };
    }

    /**
     * @return array<int, string>
     */
    private function auditedTables(): array
    {
        return [
            'accounting_currencies',
            'accounting_periods',
            'accounting_chart_of_accounts',
            'accounting_journal_entries',
            'accounting_journal_entry_lines',
        ];
    }

    /**
     * Voucher and source-document columns of the general ledger view, once their migrations have run.
     */
    private function ledgerDocumentColumns(): string
    {
        $columns = [];

        if (Schema::hasColumn('accounting_journal_entries', 'voucher_number')) {
            $columns[] = 'je.voucher_number';
        }

        if (Schema::hasColumn('accounting_journal_entries', 'source_document_number')) {
            array_push($columns, 'je.source_document_type', 'je.source_document_number', 'je.source_document_date');
        }

        return $columns === [] ? '' : ', '.implode(', ', $columns);
    }
}
