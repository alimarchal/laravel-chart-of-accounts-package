<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntry;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntryLine;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntryRun;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Services\RecurringEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recurring entries for the React and Blade screens and the API (JSON when the request expects it).
 */
class RecurringEntryController extends Controller
{
    public function __construct(private readonly RecurringEntryService $recurring) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $entries = RecurringEntry::query()->with('lines')->orderByRaw('next_run_date IS NULL')->orderBy('next_run_date')->orderBy('id')->get()
            ->map(fn (RecurringEntry $entry) => $this->present($entry))->values();

        if ($request->expectsJson()) {
            return response()->json(['data' => $entries]);
        }

        return $this->render('index', ['entries' => $entries]);
    }

    public function create(): Response|View
    {
        return $this->render('form', $this->formProps(null));
    }

    public function edit(RecurringEntry $recurringEntry): Response|View
    {
        return $this->render('form', $this->formProps($recurringEntry->load('lines')));
    }

    public function show(Request $request, RecurringEntry $recurringEntry): Response|View|JsonResponse
    {
        $recurringEntry->load('lines.account', 'lines.costCenter');
        $runs = RecurringEntryRun::query()->with('journalEntry:id,voucher_number,status,approval_status')->where('recurring_entry_id', $recurringEntry->id)->orderByDesc('run_date')->limit(100)->get()
            ->map(fn (RecurringEntryRun $run): array => [
                'id' => $run->id,
                'run_date' => $run->run_date->toDateString(),
                'status' => $run->status,
                'error' => $run->error,
                'journal_entry_id' => $run->journal_entry_id,
                'voucher_number' => $run->journalEntry?->getAttribute('voucher_number'),
                'journal_status' => $run->journalEntry?->getAttribute('status'),
            ])->values();
        $data = [
            'entry' => $this->present($recurringEntry, true),
            'runs' => $runs,
            'upcoming' => $this->recurring->upcoming($recurringEntry),
        ];

        return $request->expectsJson() ? response()->json(['data' => $data]) : $this->render('show', $data);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $entry = $this->recurring->create($this->recurring->validate($request->all()));

        return $request->expectsJson()
            ? response()->json(['data' => $this->present($entry, true)], 201)
            : to_route($this->routeName('recurring-entries.show'), $entry)->with('success', 'Recurring entry created.');
    }

    public function update(Request $request, RecurringEntry $recurringEntry): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $recurringEntry) {
            $entry = $this->recurring->update($recurringEntry, $this->recurring->validate($request->all(), $recurringEntry));

            return $request->expectsJson()
                ? response()->json(['data' => $this->present($entry, true)])
                : to_route($this->routeName('recurring-entries.show'), $entry)->with('success', 'Recurring entry updated.');
        });
    }

    public function destroy(Request $request, RecurringEntry $recurringEntry): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $recurringEntry) {
            $this->recurring->delete($recurringEntry);

            return $request->expectsJson() ? response()->json(null, 204) : to_route($this->routeName('recurring-entries.index'))->with('success', 'Recurring entry deleted.');
        });
    }

    public function pause(Request $request, RecurringEntry $recurringEntry): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->recurring->pause($recurringEntry), 'Paused: nothing is generated until you resume it.'));
    }

    public function resume(Request $request, RecurringEntry $recurringEntry): RedirectResponse|JsonResponse
    {
        $skip = $request->has('skip_missed') ? $request->boolean('skip_missed') : true;

        return $this->guard($request, fn () => $this->answer($request, $this->recurring->resume($recurringEntry, $skip), $skip ? 'Resumed; occurrences missed while it was paused are skipped.' : 'Resumed; missed occurrences will be generated.'));
    }

    public function run(Request $request, RecurringEntry $recurringEntry): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $recurringEntry) {
            $run = $this->recurring->runNow($recurringEntry);
            $message = match ($run->status) {
                'posted' => 'Entry generated and posted.',
                'submitted' => 'Entry generated and submitted for approval.',
                'draft' => 'Entry generated as a draft'.($run->error ? ': '.$run->error : '.'),
                default => 'The entry could not be generated: '.$run->error,
            };

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'data' => ['run' => ['id' => $run->id, 'run_date' => $run->run_date->toDateString(), 'status' => $run->status, 'journal_entry_id' => $run->journal_entry_id, 'error' => $run->error], 'entry' => $this->present($recurringEntry->refresh(), true)]], $run->status === 'failed' ? 422 : 201);
            }

            return back()->with($run->status === 'failed' ? 'error' : 'success', $message);
        });
    }

    /**
     * @param  \Closure(): (RedirectResponse|JsonResponse)  $action
     */
    private function guard(Request $request, \Closure $action): RedirectResponse|JsonResponse
    {
        try {
            return $action();
        } catch (AccountingException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->with('error', $exception->getMessage());
        }
    }

    private function answer(Request $request, RecurringEntry $entry, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $this->present($entry, true)]) : back()->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $page, array $props): Response|View
    {
        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::recurring-entries.'.$page, $props)
            : Inertia::render('accounting/recurring-entries/'.$page, $props);
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?RecurringEntry $entry): array
    {
        return [
            'entry' => $entry ? $this->present($entry, true) : null,
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'voucherTypes' => VoucherType::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'frequencies' => RecurringEntry::FREQUENCIES,
            'today' => now()->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(RecurringEntry $entry, bool $detailed = false): array
    {
        /** @var Collection<int, RecurringEntryLine> $lines */
        $lines = $entry->relationLoaded('lines') ? $entry->lines : $entry->lines()->get();
        $data = [
            'id' => $entry->id,
            'name' => $entry->name,
            'status' => $entry->status(),
            'frequency' => $entry->frequency,
            'interval' => $entry->interval,
            'day_of_month' => $entry->day_of_month,
            'start_date' => $entry->start_date->toDateString(),
            'end_date' => $entry->end_date?->toDateString(),
            'next_run_date' => $entry->next_run_date?->toDateString(),
            'max_runs' => $entry->max_runs,
            'runs_count' => $entry->runs_count,
            'mode' => $entry->mode,
            'is_active' => $entry->is_active,
            'voucher_type_id' => $entry->voucher_type_id,
            'reference' => $entry->reference,
            'description' => $entry->description,
            'last_run_at' => $entry->last_run_at?->toISOString(),
            'amount' => Money::fromCents($lines->sum(fn ($line) => Money::toCents((string) $line->getRawOriginal('debit')))),
        ];

        if ($detailed) {
            $data['lines'] = $lines->map(fn (RecurringEntryLine $line): array => [
                'chart_of_account_id' => $line->chart_of_account_id,
                'account' => $line->relationLoaded('account') && $line->account ? $line->account->account_code.' '.$line->account->account_name : null,
                'cost_center_id' => $line->cost_center_id,
                'cost_center' => $line->relationLoaded('costCenter') && $line->costCenter ? $line->costCenter->code : null,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
            ])->values()->all();
        }

        return $data;
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
