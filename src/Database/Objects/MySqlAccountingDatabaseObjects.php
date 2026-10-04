<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Objects;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MySqlAccountingDatabaseObjects implements AccountingDatabaseObjects
{
    public function sync(): void
    {
        $this->createAuditTriggers();
        $this->createImmutabilityTriggers();
        $this->createViews();
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
        foreach ($this->immutabilityTriggers() as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    /**
     * JSON_OBJECT('col', ROW.col, ...) over every column of the table, so the audit trail
     * records the full before/after state, not just the id.
     */
    protected function jsonRow(string $table, string $row): string
    {
        $pairs = collect(Schema::getColumnListing($table))
            ->map(fn (string $column) => "'{$column}', {$row}.`{$column}`")
            ->implode(', ');

        return "JSON_OBJECT({$pairs})";
    }

    /**
     * @return array<int, string>
     */
    protected function immutabilityTriggers(): array
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
    protected function createImmutabilityTriggers(): void
    {
        foreach ($this->immutabilityTriggers() as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
        }

        $message = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journal entries are immutable; reverse them instead.'";
        $parentPosted = fn (string $row) => "(SELECT status FROM accounting_journal_entries WHERE id = {$row}.journal_entry_id) = 'posted'";

        DB::unprepared("CREATE TRIGGER acct_journals_posted_guard_update BEFORE UPDATE ON accounting_journal_entries FOR EACH ROW BEGIN
            IF OLD.status = 'posted' AND (NEW.status <> 'posted' OR NEW.entry_date <> OLD.entry_date OR NEW.currency_id <> OLD.currency_id
                OR NEW.company_id <> OLD.company_id
                OR NEW.fx_rate_to_base <> OLD.fx_rate_to_base OR NOT (NEW.deleted_at <=> OLD.deleted_at)) THEN {$message}; END IF;
        END");
        DB::unprepared("CREATE TRIGGER acct_journals_posted_guard_delete BEFORE DELETE ON accounting_journal_entries FOR EACH ROW BEGIN
            IF OLD.status = 'posted' THEN {$message}; END IF;
        END");
        DB::unprepared("CREATE TRIGGER acct_lines_posted_guard_insert BEFORE INSERT ON accounting_journal_entry_lines FOR EACH ROW BEGIN
            IF {$parentPosted('NEW')} THEN {$message}; END IF;
        END");
        DB::unprepared("CREATE TRIGGER acct_lines_posted_guard_update BEFORE UPDATE ON accounting_journal_entry_lines FOR EACH ROW BEGIN
            IF {$parentPosted('OLD')} AND (NEW.debit <> OLD.debit OR NEW.credit <> OLD.credit
                OR NEW.base_debit <> OLD.base_debit OR NEW.base_credit <> OLD.base_credit
                OR NEW.chart_of_account_id <> OLD.chart_of_account_id OR NEW.journal_entry_id <> OLD.journal_entry_id) THEN {$message}; END IF;
        END");
        DB::unprepared("CREATE TRIGGER acct_lines_posted_guard_delete BEFORE DELETE ON accounting_journal_entry_lines FOR EACH ROW BEGIN
            IF {$parentPosted('OLD')} THEN {$message}; END IF;
        END");
    }

    protected function createAuditTriggers(): void
    {
        foreach ($this->auditedTables() as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_insert");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_update");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_delete");

            $new = $this->jsonRow($table, 'NEW');
            $old = $this->jsonRow($table, 'OLD');

            $newCompany = $this->companyOf($table, 'NEW');
            $oldCompany = $this->companyOf($table, 'OLD');

            DB::unprepared("CREATE TRIGGER {$table}_audit_insert AFTER INSERT ON {$table} FOR EACH ROW INSERT INTO accounting_audit_logs (company_id, table_name, record_id, action, new_values, metadata, created_at) VALUES ({$newCompany}, '{$table}', NEW.id, 'insert', {$new}, JSON_OBJECT('source', 'database_trigger'), NOW())");
            DB::unprepared("CREATE TRIGGER {$table}_audit_update AFTER UPDATE ON {$table} FOR EACH ROW INSERT INTO accounting_audit_logs (company_id, table_name, record_id, action, old_values, new_values, metadata, created_at) VALUES ({$newCompany}, '{$table}', NEW.id, 'update', {$old}, {$new}, JSON_OBJECT('source', 'database_trigger'), NOW())");
            DB::unprepared("CREATE TRIGGER {$table}_audit_delete AFTER DELETE ON {$table} FOR EACH ROW INSERT INTO accounting_audit_logs (company_id, table_name, record_id, action, old_values, metadata, created_at) VALUES ({$oldCompany}, '{$table}', OLD.id, 'delete', {$old}, JSON_OBJECT('source', 'database_trigger'), NOW())");
        }
    }

    /**
     * SQL for the company an audited row belongs to: its own company_id, its journal entry's for
     * lines, NULL for shared tables (currencies).
     */
    protected function companyOf(string $table, string $row): string
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
    protected function auditedTables(): array
    {
        return [
            'accounting_currencies',
            'accounting_periods',
            'accounting_chart_of_accounts',
            'accounting_journal_entries',
            'accounting_journal_entry_lines',
        ];
    }

    protected function createViews(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_accounting_general_ledger AS
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
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_accounting_trial_balance AS
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

        DB::statement("CREATE OR REPLACE VIEW vw_accounting_balance_sheet AS SELECT * FROM vw_accounting_trial_balance WHERE report_group = 'BalanceSheet'");
        DB::statement("CREATE OR REPLACE VIEW vw_accounting_income_statement AS SELECT * FROM vw_accounting_trial_balance WHERE report_group = 'IncomeStatement'");
    }
}
