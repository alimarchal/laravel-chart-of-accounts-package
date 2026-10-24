<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_payslip_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payslip_id')->constrained('accounting_payslips', indexName: 'acct_payslip_line_slip_fk')->cascadeOnDelete();
            $table->foreignId('pay_component_id')->nullable()->constrained('accounting_pay_components', indexName: 'acct_payslip_line_comp_fk')->nullOnDelete();
            $table->string('kind', 12); // basic | earning | deduction | tax
            $table->string('description', 160);
            $table->decimal('amount', 18, 2);
            $table->foreignId('account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_payslip_line_account_fk')->restrictOnDelete();
            $table->timestamps();
            $table->index('payslip_id', 'acct_payslip_line_slip_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payslip_lines');
    }
};
