<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AccountingDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $can = fn (string $permission): bool => $user?->can($permission) ?? false;

        // A card only for what the user may open; the journal count follows the working branch.
        return Inertia::render('accounting/dashboard', [
            'section' => 'accounting',
            'summary' => array_filter([
                'accountTypes' => $can('account-types.view') ? AccountType::query()->count() : null,
                'currencies' => $can('currencies.view') ? Currency::query()->count() : null,
                'accounts' => $can('chart-of-accounts.view') ? ChartOfAccount::query()->count() : null,
                'postedJournalEntries' => $can('journal-entries.view') ? CurrentBranch::constrain(JournalEntry::query()->where('status', 'posted'), 'accounting_journal_entries.branch_id')->count() : null,
            ], fn ($count) => $count !== null),
        ]);
    }
}
