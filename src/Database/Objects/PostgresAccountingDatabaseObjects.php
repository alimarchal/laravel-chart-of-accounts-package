<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Objects;

use Illuminate\Support\Facades\DB;

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
        DB::statement('DROP INDEX IF EXISTS acct_currencies_single_base_idx');
    }

    private function createAuditTriggers(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION accounting_audit_trigger()
            RETURNS trigger AS $$
            DECLARE
                row_id bigint;
            BEGIN
                row_id := CASE
                    WHEN TG_OP = 'DELETE' THEN (to_jsonb(OLD)->>'id')::bigint
                    ELSE (to_jsonb(NEW)->>'id')::bigint
                END;

                INSERT INTO accounting_audit_logs (
                    table_name,
                    record_id,
                    action,
                    old_values,
                    new_values,
                    changed_fields,
                    metadata,
                    created_at
                ) VALUES (
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
                jed.description AS line_description,
                cc.code AS cost_center_code,
                cc.name AS cost_center_name,
                c.code AS currency_code,
                je.fx_rate_to_base
            FROM accounting_journal_entry_lines jed
            JOIN accounting_journal_entries je ON je.id = jed.journal_entry_id
            JOIN accounting_chart_of_accounts coa ON coa.id = jed.chart_of_account_id
            LEFT JOIN accounting_cost_centers cc ON cc.id = jed.cost_center_id
            LEFT JOIN accounting_currencies c ON c.id = je.currency_id
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_accounting_trial_balance AS
            SELECT
                coa.id AS account_id,
                coa.account_code,
                coa.account_name,
                at.name AS account_type,
                at.report_group,
                coa.normal_balance,
                COALESCE(SUM(CASE WHEN je.status = 'posted' THEN jed.debit ELSE 0 END), 0) AS total_debits,
                COALESCE(SUM(CASE WHEN je.status = 'posted' THEN jed.credit ELSE 0 END), 0) AS total_credits,
                CASE
                    WHEN coa.normal_balance = 'debit' THEN COALESCE(SUM(CASE WHEN je.status = 'posted' THEN jed.debit - jed.credit ELSE 0 END), 0)
                    ELSE COALESCE(SUM(CASE WHEN je.status = 'posted' THEN jed.credit - jed.debit ELSE 0 END), 0)
                END AS balance
            FROM accounting_chart_of_accounts coa
            JOIN accounting_account_types at ON at.id = coa.account_type_id
            LEFT JOIN accounting_journal_entry_lines jed ON jed.chart_of_account_id = coa.id
            LEFT JOIN accounting_journal_entries je ON je.id = jed.journal_entry_id
            WHERE coa.is_active = true OR je.id IS NOT NULL
            GROUP BY coa.id, coa.account_code, coa.account_name, at.name, at.report_group, coa.normal_balance
        SQL);

        DB::statement("CREATE OR REPLACE VIEW vw_accounting_balance_sheet AS SELECT * FROM vw_accounting_trial_balance WHERE report_group = 'BalanceSheet'");
        DB::statement("CREATE OR REPLACE VIEW vw_accounting_income_statement AS SELECT * FROM vw_accounting_trial_balance WHERE report_group = 'IncomeStatement'");
    }
}
