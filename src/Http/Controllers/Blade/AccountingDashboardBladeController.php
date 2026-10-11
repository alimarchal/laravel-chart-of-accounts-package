<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccountingDashboardBladeController extends Controller
{
    public function __invoke(): View
    {
        $summary = [
            'accountTypes' => AccountType::query()->count(),
            'currencies' => Currency::query()->count(),
            'accounts' => ChartOfAccount::query()->count(),
            'periods' => AccountingPeriod::query()->count(),
            'costCenters' => CostCenter::query()->count(),
            'bankAccounts' => BankAccount::query()->count(),
            'journalEntries' => CurrentBranch::constrain(JournalEntry::query(), 'accounting_journal_entries.branch_id')->count(),
            'postedJournalEntries' => CurrentBranch::constrain(JournalEntry::query()->where('status', 'posted'), 'accounting_journal_entries.branch_id')->count(),
            'taxCodes' => TaxCode::query()->count(),
            'taxRates' => TaxRate::query()->count(),
            'voucherTypes' => VoucherType::query()->count(),
            'reconciliations' => Reconciliation::query()->count(),
            'balanceSnapshots' => AccountBalanceSnapshot::query()->count(),
            'users' => app(config('auth.providers.users.model'))->newQuery()->count(),
            'roles' => Role::query()->count(),
            'permissions' => Permission::query()->count(),
        ];

        return view('accounting::dashboard', compact('summary'));
    }
}
