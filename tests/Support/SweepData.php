<?php

namespace Alimarchal\LaravelChartOfAccounts\Tests\Support;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGrade;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Models\Warehouse;
use Alimarchal\LaravelChartOfAccounts\Services\BudgetService;
use Alimarchal\LaravelChartOfAccounts\Services\FixedAssetService;
use Alimarchal\LaravelChartOfAccounts\Services\InventoryService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyDocumentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyPaymentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Illuminate\Support\Facades\Artisan;

/**
 * One record of every kind that has screens of its own, so a sweep over the routes also opens the pages of a single record
 * (a party, an invoice, a payroll run, an asset, a stock item, …). Needs the seeded chart and a user to be signed in.
 */
final class SweepData
{
    public static function create(): void
    {
        $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
        $day = fn (int $n): string => $start->copy()->addDays($n - 1)->toDateString();

        account('1103')->forceFill(['control_type' => 'receivables'])->save();
        account('2101')->forceFill(['control_type' => 'payables'])->save();

        $tax = TaxCode::query()->create(['code' => 'GST17', 'name' => 'GST', 'kind' => 'output', 'tax_account_id' => account('2104')->id]);
        TaxRate::query()->create(['tax_code_id' => $tax->id, 'rate' => '17', 'effective_from' => $start->copy()->subYear()->toDateString()]);
        BankAccount::query()->create(['chart_of_account_id' => account('1108')->id, 'account_name' => 'Operating', 'account_number' => 'PK00-1234']);
        journal(['1101' => 100, '4101' => -100], $day(2));

        $parties = app(PartyService::class);
        $customer = $parties->create($parties->validate(['type' => 'customer', 'code' => 'C001', 'name' => 'Acme Traders', 'payment_terms_days' => 30]));
        $documents = app(PartyDocumentService::class);
        $invoice = $documents->create($documents->validate([
            'party_id' => $customer->id, 'kind' => 'invoice', 'issue_date' => $day(1),
            'lines' => [['chart_of_account_id' => account('4101')->id, 'quantity' => 1, 'unit_price' => 1000, 'tax_code_id' => $tax->id]],
        ]));
        $documents->post($invoice);
        $payments = app(PartyPaymentService::class);
        $payments->create($payments->validate(['party_id' => $customer->id, 'kind' => 'receipt', 'payment_date' => $day(10), 'amount' => 500, 'account_id' => account('1101')->id, 'allocations' => [['document_id' => $invoice->id, 'amount' => 500]]]));

        $assets = app(FixedAssetService::class);
        $assets->create($assets->validate([
            'code' => 'EQ-1', 'name' => 'Server', 'acquisition_date' => $day(1), 'cost' => '12000', 'salvage_value' => '0', 'useful_life_months' => 12, 'method' => 'straight_line',
            'asset_account_id' => account('1205')->id, 'accumulated_account_id' => account('1206')->id, 'expense_account_id' => account('5114')->id,
        ]));

        $stock = app(InventoryService::class);
        $item = $stock->createItem($stock->validateItem(['sku' => 'BK-1', 'name' => 'Book', 'unit' => 'pcs', 'inventory_account_id' => account('1151')->id, 'cogs_account_id' => account('5202')->id]));
        $warehouse = Warehouse::query()->create(['code' => 'MAIN', 'name' => 'Main store']);
        $stock->move(['type' => 'receipt', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '10', 'unit_cost' => '5.50', 'offset_account_id' => account('2102')->id, 'movement_date' => $day(1)]);

        $payroll = app(PayrollService::class);
        $component = PayComponent::query()->create($payroll->validateComponent(['code' => 'HRA', 'name' => 'House rent', 'kind' => 'earning', 'method' => 'percent_of_basic', 'value' => 10, 'taxable' => true, 'account_id' => account('5102')->id]));
        $payroll->saveEmployee($payroll->validateEmployee(['code' => 'E1', 'name' => 'Amina', 'join_date' => $start->copy()->subYear()->toDateString(), 'base_salary' => '100000', 'components' => [['pay_component_id' => $component->id]]]));
        $payroll->createRun($start->toDateString());
        SalaryGrade::query()->forceCreate(['code' => 'G1', 'name' => 'Grade 1', 'base_salary' => '50000', 'is_active' => true, 'company_id' => Employee::query()->firstOrFail()->company_id]);

        $budgets = app(BudgetService::class);
        $budgets->create($budgets->validate([
            'name' => 'Annual', 'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addMonths(11)->toDateString(),
            'lines' => [['chart_of_account_id' => account('5102')->id, 'annual' => '12000'], ['chart_of_account_id' => account('4101')->id, 'annual' => '36000']],
        ]));

        Artisan::call('accounting:rebuild-snapshots');
    }
}
