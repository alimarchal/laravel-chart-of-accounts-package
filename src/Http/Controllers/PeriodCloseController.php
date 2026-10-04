<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Actions\CloseAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReopenAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingPeriodService;
use Alimarchal\LaravelChartOfAccounts\Services\PeriodCloseChecklist;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Month-end and year-end close (React): the periods list, the close workspace with its checklist and
 * closing-entry preview, and the close / year-end close / reopen actions.
 */
class PeriodCloseController extends Controller
{
    public function index(): Response
    {
        $startMonth = app(CurrentCompany::class)->get()->fiscal_year_start_month ?: 1;

        return Inertia::render('accounting/periods/index', [
            'periods' => AccountingPeriod::query()
                ->orderByDesc('start_date')
                ->get()
                ->map(fn (AccountingPeriod $period) => [
                    ...$period->only(['id', 'name', 'status', 'closed_at', 'closing_net_income', 'closing_journal_entry_id']),
                    'start_date' => $period->start_date->toDateString(),
                    'end_date' => $period->end_date->toDateString(),
                    'is_fiscal_year_end' => self::isFiscalYearEnd($period, $startMonth),
                ]),
            'fiscalYearStartMonth' => $startMonth,
        ]);
    }

    public function show(Request $request, AccountingPeriod $period, PeriodCloseChecklist $checklist): Response
    {
        $yearEnd = $request->boolean('year_end');

        return Inertia::render('accounting/periods/close', [
            'checklist' => $checklist->build($period, $yearEnd),
            'isFiscalYearEnd' => self::isFiscalYearEnd($period, app(CurrentCompany::class)->get()->fiscal_year_start_month ?: 1),
        ]);
    }

    public function close(AccountingPeriod $period, CloseAccountingPeriodAction $action): RedirectResponse
    {
        return $this->attempt(fn () => $action->execute($period), $period, "{$period->name} is closed.");
    }

    public function closeFiscalYear(AccountingPeriod $period, CloseFiscalYearAction $action): RedirectResponse
    {
        return $this->attempt(fn () => $action->execute($period), $period, "Year-end close done: {$period->name} is closed and profit or loss moved to retained earnings.", yearEnd: true);
    }

    public function reopen(Request $request, AccountingPeriod $period, ReopenAccountingPeriodAction $action): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->attempt(fn () => $action->execute($period, $data['reason']), $period, "{$period->name} is open again.");
    }

    public function generateMonthly(Request $request, AccountingPeriodService $periods): RedirectResponse
    {
        $data = $request->validate(['start_date' => ['required', 'date']]);

        try {
            $created = $periods->generateMonthly($data['start_date']);
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Created {$created->count()} monthly periods: {$created->first()?->name} – {$created->last()?->name}.");
    }

    /**
     * Does the period end on the last day of the company's fiscal year?
     */
    public static function isFiscalYearEnd(AccountingPeriod $period, int $fiscalYearStartMonth): bool
    {
        $endMonth = $fiscalYearStartMonth === 1 ? 12 : $fiscalYearStartMonth - 1;

        return $period->end_date->month === $endMonth && $period->end_date->isLastOfMonth();
    }

    private function attempt(callable $callback, AccountingPeriod $period, string $success, bool $yearEnd = false): RedirectResponse
    {
        $prefix = config('accounting.route_name_prefix', 'accounting');

        try {
            $callback();
        } catch (AccountingException $exception) {
            return redirect()->route("{$prefix}.periods.close.show", ['period' => $period->getKey(), 'year_end' => $yearEnd ? 1 : null])
                ->with('error', $exception->getMessage());
        }

        return redirect()->route("{$prefix}.periods.close.show", ['period' => $period->getKey(), 'year_end' => $yearEnd ? 1 : null])
            ->with('success', $success);
    }
}
