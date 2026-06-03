<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Objects;

use Illuminate\Support\Facades\DB;

class MySqlAccountingDatabaseObjects implements AccountingDatabaseObjects
{
    public function sync(): void
    {
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
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_insert");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_update");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_delete");
        }
    }

    protected function createAuditTriggers(): void
    {
        foreach ($this->auditedTables() as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_insert");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_update");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_delete");

            DB::unprepared("CREATE TRIGGER {$table}_audit_insert AFTER INSERT ON {$table} FOR EACH ROW INSERT INTO accounting_audit_logs (table_name, record_id, action, new_values, metadata, created_at) VALUES ('{$table}', NEW.id, 'insert', JSON_OBJECT('id', NEW.id), JSON_OBJECT('source', 'database_trigger'), NOW())");
            DB::unprepared("CREATE TRIGGER {$table}_audit_update AFTER UPDATE ON {$table} FOR EACH ROW INSERT INTO accounting_audit_logs (table_name, record_id, action, old_values, new_values, metadata, created_at) VALUES ('{$table}', NEW.id, 'update', JSON_OBJECT('id', OLD.id), JSON_OBJECT('id', NEW.id), JSON_OBJECT('source', 'database_trigger'), NOW())");
            DB::unprepared("CREATE TRIGGER {$table}_audit_delete AFTER DELETE ON {$table} FOR EACH ROW INSERT INTO accounting_audit_logs (table_name, record_id, action, old_values, metadata, created_at) VALUES ('{$table}', OLD.id, 'delete', JSON_OBJECT('id', OLD.id), JSON_OBJECT('source', 'database_trigger'), NOW())");
        }
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
            WHERE coa.is_active = 1 OR je.id IS NOT NULL
            GROUP BY coa.id, coa.account_code, coa.account_name, at.name, at.report_group, coa.normal_balance
        SQL);

        DB::statement("CREATE OR REPLACE VIEW vw_accounting_balance_sheet AS SELECT * FROM vw_accounting_trial_balance WHERE report_group = 'BalanceSheet'");
        DB::statement("CREATE OR REPLACE VIEW vw_accounting_income_statement AS SELECT * FROM vw_accounting_trial_balance WHERE report_group = 'IncomeStatement'");
    }
}
