<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The Settings part of the accounting area (Blade): users, roles and permissions, and how the books are set up. The page shows only the cards
 * the user's permissions allow.
 */
class AccountingSettingsBladeController extends Controller
{
    public function __invoke(): View
    {
        $summary = [
            'accountTypes' => AccountType::query()->count(),
            'currencies' => Currency::query()->count(),
            'periods' => AccountingPeriod::query()->count(),
            'costCenters' => CostCenter::query()->count(),
            'taxCodes' => TaxCode::query()->count(),
            'taxRates' => TaxRate::query()->count(),
            'voucherTypes' => VoucherType::query()->count(),
            'users' => app(config('auth.providers.users.model'))->newQuery()->count(),
            'roles' => Role::query()->count(),
            'permissions' => Permission::query()->count(),
        ];

        return view('accounting::settings', compact('summary'));
    }
}
