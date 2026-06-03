<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AccountingDashboardBladeController extends Controller
{
    public function __invoke(): View
    {
        return view('accounting::dashboard', [
            'summary' => [
                'accountTypes' => AccountType::query()->count(),
                'currencies' => Currency::query()->count(),
                'accounts' => ChartOfAccount::query()->count(),
                'postedJournalEntries' => JournalEntry::query()->where('status', 'posted')->count(),
            ],
        ]);
    }
}
