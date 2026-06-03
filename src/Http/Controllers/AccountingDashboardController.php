<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AccountingDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('accounting/dashboard', [
            'summary' => [
                'accountTypes' => AccountType::query()->count(),
                'currencies' => Currency::query()->count(),
                'accounts' => ChartOfAccount::query()->count(),
                'postedJournalEntries' => JournalEntry::query()->where('status', 'posted')->count(),
            ],
        ]);
    }
}
