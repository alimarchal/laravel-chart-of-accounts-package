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
use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccountingDashboardBladeController extends Controller
{
    public function __invoke(): View
    {
        return view('accounting::dashboard', [
            'counts' => [
                'accountTypes' => AccountType::query()->count(),
                'currencies' => Currency::query()->count(),
                'accounts' => ChartOfAccount::query()->count(),
                'periods' => AccountingPeriod::query()->count(),
                'costCenters' => CostCenter::query()->count(),
                'bankAccounts' => BankAccount::query()->count(),
                'journalEntries' => JournalEntry::query()->count(),
                'postedJournalEntries' => JournalEntry::query()->where('status', 'posted')->count(),
                'taxCodes' => TaxCode::query()->count(),
                'taxRates' => TaxRate::query()->count(),
                'reconciliations' => Reconciliation::query()->count(),
                'balanceSnapshots' => AccountBalanceSnapshot::query()->count(),
                'users' => app(config('auth.providers.users.model', User::class))->count(),
                'roles' => Role::query()->count(),
                'permissions' => Permission::query()->count(),
            ],
        ]);
    }
}
