<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Actions\CloseAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReopenAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Http\Resources\AccountResource;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingHealthCheckService;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Account balances, the account tree, period workflow and health — the endpoints most integrations need.
 */
class AccountingApiController extends Controller
{
    public function tree(ChartOfAccountService $service): AnonymousResourceCollection
    {
        return AccountResource::collection($service->tree());
    }

    /**
     * Balance of an account — including all of its child accounts when it is a group — as of a date.
     * Signed by the account's normal balance (a positive asset/expense or liability/revenue balance).
     */
    public function balance(Request $request, ChartOfAccount $chartOfAccount, ChartOfAccountService $service): JsonResponse
    {
        $asOf = $request->validate(['as_of_date' => ['nullable', 'date']])['as_of_date'] ?? now()->toDateString();
        $ids = $service->idsWithDescendants([$chartOfAccount->account_code]);

        $row = DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->whereIn('line.chart_of_account_id', $ids)
            ->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '<=', $asOf)
            ->selectRaw('COALESCE(SUM(line.debit), 0) as debits, COALESCE(SUM(line.credit), 0) as credits')
            ->first();

        $debits = Money::toCents((string) $row->debits);
        $credits = Money::toCents((string) $row->credits);
        $balance = $chartOfAccount->normal_balance === 'debit' ? $debits - $credits : $credits - $debits;

        return response()->json(['data' => [
            'account_id' => $chartOfAccount->id,
            'account_code' => $chartOfAccount->account_code,
            'account_name' => $chartOfAccount->account_name,
            'normal_balance' => $chartOfAccount->normal_balance,
            'includes_child_accounts' => (bool) $chartOfAccount->is_group,
            'as_of_date' => $asOf,
            'total_debit' => Money::fromCents($debits),
            'total_credit' => Money::fromCents($credits),
            'balance' => Money::fromCents($balance),
        ]]);
    }

    public function closePeriod(AccountingPeriod $period, CloseAccountingPeriodAction $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($period)]);
    }

    public function reopenPeriod(AccountingPeriod $period, ReopenAccountingPeriodAction $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($period)]);
    }

    public function closeFiscalYear(AccountingPeriod $period, CloseFiscalYearAction $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($period)->load('closingJournalEntry.lines')]);
    }

    public function health(AccountingHealthCheckService $service): JsonResponse
    {
        $result = $service->check();

        return response()->json($result, $result['ok'] ? 200 : 503);
    }
}
