<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Objects;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostgresAccountingDatabaseObjects implements AccountingDatabaseObjects
{
    public function sync(): void
    {
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS acct_currencies_single_base_idx ON accounting_currencies (is_base) WHERE is_base = true');

        DB::statement('ALTER TABLE accounting_account_types DROP CONSTRAINT IF EXISTS acct_types_report_group_chk');
        DB::statement("ALTER TABLE accounting_account_types ADD CONSTRAINT acct_types_report_group_chk CHECK (report_group IN ('BalanceSheet', 'IncomeStatement'))");

        DB::statement('ALTER TABLE accounting_currencies DROP CONSTRAINT IF EXISTS acct_currencies_fx_positive_chk');
        DB::statement('ALTER TABLE accounting_currencies ADD CONSTRAINT acct_currencies_fx_positive_chk CHECK (exchange_rate_to_base > 0)');

        DB::statement('ALTER TABLE accounting_journal_entries DROP CONSTRAINT IF EXISTS acct_journals_fx_positive_chk');
        DB::statement('ALTER TABLE accounting_journal_entries ADD CONSTRAINT acct_journals_fx_positive_chk CHECK (fx_rate_to_base > 0)');

        DB::statement('ALTER TABLE accounting_journal_entry_lines DROP CONSTRAINT IF EXISTS acct_lines_debit_credit_chk');
        DB::statement('ALTER TABLE accounting_journal_entry_lines ADD CONSTRAINT acct_lines_debit_credit_chk CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))');

        $this->createAuditTriggers();
        $this->createImmutabilityTriggers();
        $this->createChartGuards();
        $this->createViews();
    }

    public function drop(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_accounting_income_statement');
        DB::statement('DROP VIEW IF EXISTS vw_accounting_balance_sheet');
        DB::statement('DROP VIEW IF EXISTS vw_accounting_trial_balance');
        DB::statement('DROP VIEW IF EXISTS vw_accounting_general_ledger');
        foreach ($this->auditedTables() as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_trigger ON {$table}");
        }
        DB::statement('DROP FUNCTION IF EXISTS accounting_audit_trigger()');
        DB::statement('DROP TRIGGER IF EXISTS acct_journals_posted_guard ON accounting_journal_entries');
        DB::statement('DROP TRIGGER IF EXISTS acct_lines_posted_guard ON accounting_journal_entry_lines');
        DB::statement('DROP FUNCTION IF EXISTS accounting_journal_posted_guard()');
        DB::statement('DROP FUNCTION IF EXISTS accounting_line_posted_guard()');
        DB::statement('DROP TRIGGER IF EXISTS acct_coa_guard ON accounting_chart_of_accounts');
        DB::statement('DROP FUNCTION IF EXISTS accounting_coa_guard()');
        DB::statement('DROP TRIGGER IF EXISTS acct_lines_company_guard ON accounting_journal_entry_lines');
        DB::statement('DROP FUNCTION IF EXISTS accounting_line_company_guard()');
        DB::statement('DROP TRIGGER IF EXISTS acct_journals_group_guard ON accounting_journal_entries');
        DB::statement('DROP FUNCTION IF EXISTS accounting_journal_group_guard()');
        DB::statement('DROP INDEX IF EXISTS acct_currencies_single_base_idx');
    }

    /**
     * Posted journal entries (and their lines) are immutable at the database layer. Only the
     * reversal / closing / reconciliation bookkeeping columns may still change.
     */
    private function createImmutabilityTriggers(): void
    {
        $voucher = Schema::hasColumn('accounting_journal_entries', 'voucher_number')
            ? 'OR NEW.voucher_number IS DISTINCT FROM OLD.voucher_number OR NEW.voucher_type_id IS DISTINCT FROM OLD.voucher_type_id'
            : '';

        if (Schema::hasColumn('accounting_journal_entries', 'source_document_number')) {
            $voucher .= ' OR NEW.source_document_type IS DISTINCT FROM OLD.source_document_type OR NEW.source_document_number IS DISTINCT FROM OLD.source_document_number OR NEW.source_document_date IS DISTINCT FROM OLD.source_document_date OR NEW.sourceable_type IS DISTINCT FROM OLD.sourceable_type OR NEW.sourceable_id IS DISTINCT FROM OLD.sourceable_id';
        }

        if (Schema::hasColumn('accounting_journal_entries', 'origin_module')) {
            $voucher .= ' OR NEW.origin_module IS DISTINCT FROM OLD.origin_module';
        }

        DB::statement(str_replace('__VOUCHER__', $voucher, <<<'SQL'
            CREATE OR REPLACE FUNCTION accounting_journal_posted_guard()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.status = 'posted' AND (
                    TG_OP = 'DELETE'
                    OR NEW.status <> 'posted'
                    OR NEW.entry_date IS DISTINCT FROM OLD.entry_date
                    OR NEW.currency_id IS DISTINCT FROM OLD.currency_id
                    OR NEW.company_id IS DISTINCT FROM OLD.company_id
                    __VOUCHER__
                    OR NEW.fx_rate_to_base IS DISTINCT FROM OLD.fx_rate_to_base
                    OR NEW.deleted_at IS DISTINCT FROM OLD.deleted_at
                ) THEN
                    RAISE EXCEPTION 'Posted journal entries are immutable; reverse them instead.';
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        SQL));

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting_line_posted_guard()
            RETURNS trigger AS $$
            DECLARE
                parent_status text;
            BEGIN
                SELECT status INTO parent_status FROM accounting_journal_entries
                WHERE id = CASE WHEN TG_OP = 'INSERT' THEN NEW.journal_entry_id ELSE OLD.journal_entry_id END;

                IF parent_status = 'posted' AND (
                    TG_OP IN ('INSERT', 'DELETE')
                    OR NEW.debit IS DISTINCT FROM OLD.debit
                    OR NEW.credit IS DISTINCT FROM OLD.credit
                    OR NEW.base_debit IS DISTINCT FROM OLD.base_debit
                    OR NEW.base_credit IS DISTINCT FROM OLD.base_credit
                    OR NEW.chart_of_account_id IS DISTINCT FROM OLD.chart_of_account_id
                    OR NEW.journal_entry_id IS DISTINCT FROM OLD.journal_entry_id
                ) THEN
                    RAISE EXCEPTION 'Posted journal entries are immutable; reverse them instead.';
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS acct_journals_posted_guard ON accounting_journal_entries');
        DB::statement('CREATE TRIGGER acct_journals_posted_guard BEFORE UPDATE OR DELETE ON accounting_journal_entries FOR EACH ROW EXECUTE FUNCTION accounting_journal_posted_guard()');
        DB::statement('DROP TRIGGER IF EXISTS acct_lines_posted_guard ON accounting_journal_entry_lines');
        DB::statement('CREATE TRIGGER acct_lines_posted_guard BEFORE INSERT OR UPDATE OR DELETE ON accounting_journal_entry_lines FOR EACH ROW EXECUTE FUNCTION accounting_line_posted_guard()');
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

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting_coa_guard()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF NEW.company_id IS DISTINCT FROM OLD.company_id THEN
                        RAISE EXCEPTION 'An account cannot move to another company.';
                    END IF;

                    IF (NEW.account_type_id IS DISTINCT FROM OLD.account_type_id
                        OR NEW.normal_balance IS DISTINCT FROM OLD.normal_balance
                        OR NEW.is_group IS DISTINCT FROM OLD.is_group)
                        AND EXISTS (SELECT 1 FROM accounting_journal_entry_lines WHERE chart_of_account_id = OLD.id) THEN
                        RAISE EXCEPTION 'Account % has journal lines: its type, normal balance and group flag cannot change.', OLD.account_code;
                    END IF;

                    IF OLD.is_group AND NOT NEW.is_group
                        AND EXISTS (SELECT 1 FROM accounting_chart_of_accounts WHERE parent_id = OLD.id) THEN
                        RAISE EXCEPTION 'A group account with child accounts cannot become a posting account.';
                    END IF;

                    IF NEW.account_type_id IS DISTINCT FROM OLD.account_type_id
                        AND EXISTS (SELECT 1 FROM accounting_chart_of_accounts WHERE parent_id = OLD.id AND account_type_id <> NEW.account_type_id) THEN
                        RAISE EXCEPTION 'Child accounts must have the same type as their group.';
                    END IF;
                END IF;

                IF NEW.parent_id IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1 FROM accounting_chart_of_accounts p
                        WHERE p.id = NEW.parent_id AND p.is_group AND p.account_type_id = NEW.account_type_id AND p.company_id = NEW.company_id
                    ) THEN
                        RAISE EXCEPTION 'The parent account must be a group account of the same type and company.';
                    END IF;

                    IF TG_OP = 'UPDATE' AND NEW.parent_id IS DISTINCT FROM OLD.parent_id AND EXISTS (
                        WITH RECURSIVE ancestors (id, parent_id) AS (
                            SELECT id, parent_id FROM accounting_chart_of_accounts WHERE id = NEW.parent_id
                            UNION
                            SELECT a.id, a.parent_id FROM accounting_chart_of_accounts a JOIN ancestors ON a.id = ancestors.parent_id
                        )
                        SELECT 1 FROM ancestors WHERE id = NEW.id
                    ) THEN
                        RAISE EXCEPTION 'An account cannot be placed under itself or one of its own sub-accounts.';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting_line_company_guard()
            RETURNS trigger AS $$
            BEGIN
                IF (SELECT company_id FROM accounting_chart_of_accounts WHERE id = NEW.chart_of_account_id)
                    IS DISTINCT FROM (SELECT company_id FROM accounting_journal_entries WHERE id = NEW.journal_entry_id) THEN
                    RAISE EXCEPTION 'A journal line must use an account of its entry''s company.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting_journal_group_guard()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.status = 'posted' AND OLD.status <> 'posted' AND EXISTS (
                    SELECT 1 FROM accounting_journal_entry_lines l
                    JOIN accounting_chart_of_accounts a ON a.id = l.chart_of_account_id
                    WHERE l.journal_entry_id = NEW.id AND a.is_group
                ) THEN
                    RAISE EXCEPTION 'Journal lines can only post to posting (non-group) accounts.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS acct_coa_guard ON accounting_chart_of_accounts');
        DB::statement('CREATE TRIGGER acct_coa_guard BEFORE INSERT OR UPDATE ON accounting_chart_of_accounts FOR EACH ROW EXECUTE FUNCTION accounting_coa_guard()');
        DB::statement('DROP TRIGGER IF EXISTS acct_lines_company_guard ON accounting_journal_entry_lines');
        DB::statement('CREATE TRIGGER acct_lines_company_guard BEFORE INSERT OR UPDATE OF chart_of_account_id, journal_entry_id ON accounting_journal_entry_lines FOR EACH ROW EXECUTE FUNCTION accounting_line_company_guard()');
        DB::statement('DROP TRIGGER IF EXISTS acct_journals_group_guard ON accounting_journal_entries');
        DB::statement('CREATE TRIGGER acct_journals_group_guard BEFORE UPDATE OF status ON accounting_journal_entries FOR EACH ROW EXECUTE FUNCTION accounting_journal_group_guard()');
    }

    private function createAuditTriggers(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting_audit_trigger()
            RETURNS trigger AS $$
            DECLARE
                row_data jsonb;
                row_id bigint;
                row_company bigint;
            BEGIN
                row_data := CASE WHEN TG_OP = 'DELETE' THEN to_jsonb(OLD) ELSE to_jsonb(NEW) END;
                row_id := (row_data->>'id')::bigint;
                -- Lines take their journal entry's company; shared tables (currencies) have none.
                row_company := CASE
                    WHEN TG_TABLE_NAME = 'accounting_journal_entry_lines'
                        THEN (SELECT company_id FROM accounting_journal_entries WHERE id = (row_data->>'journal_entry_id')::bigint)
                    ELSE (row_data->>'company_id')::bigint
                END;

                INSERT INTO accounting_audit_logs (
                    company_id,
                    table_name,
                    record_id,
                    action,
                    old_values,
                    new_values,
                    changed_fields,
                    metadata,
                    created_at
                ) VALUES (
                    row_company,
                    TG_TABLE_NAME,
                    row_id,
                    LOWER(TG_OP),
                    CASE WHEN TG_OP IN ('UPDATE', 'DELETE') THEN to_jsonb(OLD) ELSE NULL END,
                    CASE WHEN TG_OP IN ('INSERT', 'UPDATE') THEN to_jsonb(NEW) ELSE NULL END,
                    NULL,
                    jsonb_build_object('source', 'database_trigger'),
                    now()
                );

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        foreach ($this->auditedTables() as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_trigger ON {$table}");
            DB::statement("CREATE TRIGGER {$table}_audit_trigger AFTER INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION accounting_audit_trigger()");
        }
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

    private function createViews(): void
    {
        DB::statement(str_replace('je.company_id', 'je.company_id'.$this->ledgerDocumentColumns(), <<<'SQL'
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
        SQL));

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
            WHERE coa.is_active = true OR je.id IS NOT NULL
            GROUP BY coa.company_id, coa.id, coa.account_code, coa.account_name, at.name, at.report_group, coa.normal_balance
        SQL);

        DB::statement("CREATE OR REPLACE VIEW vw_accounting_balance_sheet AS SELECT * FROM vw_accounting_trial_balance WHERE report_group = 'BalanceSheet'");
        DB::statement("CREATE OR REPLACE VIEW vw_accounting_income_statement AS SELECT * FROM vw_accounting_trial_balance WHERE report_group = 'IncomeStatement'");
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
