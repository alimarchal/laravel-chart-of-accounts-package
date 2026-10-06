<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the lookups the receivables, payroll and inventory screens make by party, payslip, employee and date. MySQL
 * indexes foreign keys by itself; PostgreSQL and SQLite do not, and these tables grow with the business.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_party_allocations', function (Blueprint $table): void {
            $table->index(['party_id', 'allocated_on'], 'acct_palloc_party_date_idx');
        });

        Schema::table('accounting_payslips', function (Blueprint $table): void {
            $table->index('employee_id', 'acct_payslip_employee_idx');
        });

        Schema::table('accounting_payslip_lines', function (Blueprint $table): void {
            $table->index('payslip_id', 'acct_payslip_line_slip_idx');
        });

        Schema::table('accounting_stock_movements', function (Blueprint $table): void {
            $table->index(['company_id', 'movement_date'], 'acct_moves_company_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_stock_movements', function (Blueprint $table): void {
            $table->dropIndex('acct_moves_company_date_idx');
        });

        Schema::table('accounting_payslip_lines', function (Blueprint $table): void {
            $table->dropIndex('acct_payslip_line_slip_idx');
        });

        Schema::table('accounting_payslips', function (Blueprint $table): void {
            $table->dropIndex('acct_payslip_employee_idx');
        });

        Schema::table('accounting_party_allocations', function (Blueprint $table): void {
            $table->dropIndex('acct_palloc_party_date_idx');
        });
    }
};
