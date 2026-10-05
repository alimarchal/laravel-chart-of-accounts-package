<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntry;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntryLine;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntryRun;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Journal entries that repeat: rent, subscriptions, depreciation, accruals and their reversals.
 *
 * Each occurrence makes a draft (or posts it when the template says so and its creator may post) dated on its
 * scheduled day. An occurrence that cannot be posted — closed period, approval needed, control account — stays a
 * draft with the reason; one that cannot even be created stops the template until it is fixed. A run per scheduled
 * date is recorded once (unique), so two schedulers cannot make the same occurrence twice.
 */
class RecurringEntryService
{
    public function __construct(
        private readonly JournalEntryService $journals,
        private readonly PostJournalEntryAction $poster,
        private readonly JournalApprovalService $approvals,
    ) {}

    /**
     * Validate template input (header and lines) and return it normalised. The start date must not be in the past
     * for a new template, and the lines must balance.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input, ?RecurringEntry $entry = null): array
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'voucher_type_id' => ['nullable', 'integer', CompanyRule::exists('accounting_voucher_types', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'frequency' => ['required', Rule::in(array_keys(RecurringEntry::FREQUENCIES))],
            'interval' => ['nullable', 'integer', 'min:1', 'max:60'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date', ...($entry === null ? ['after_or_equal:today'] : [])],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'max_runs' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'mode' => ['required', Rule::in(['draft', 'post'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:2', 'max:200'],
            'lines.*.chart_of_account_id' => ['required', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true))],
            'lines.*.cost_center_id' => ['nullable', 'integer', CompanyRule::exists('accounting_cost_centers', 'id')],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ], [
            'lines.*.chart_of_account_id.exists' => 'Line :position: choose an active posting account of this company.',
        ]);

        $validator->after(function ($validator) use ($input): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $debit = 0;
            $credit = 0;

            foreach ((array) $input['lines'] as $index => $line) {
                $d = Money::toCents((string) ($line['debit'] ?? 0));
                $c = Money::toCents((string) ($line['credit'] ?? 0));

                if (($d > 0) === ($c > 0)) {
                    $validator->errors()->add("lines.{$index}.debit", 'Line '.($index + 1).': enter either a debit or a credit.');
                }

                $debit += $d;
                $credit += $c;
            }

            if ($debit !== $credit) {
                $validator->errors()->add('lines', 'The lines are not balanced: debits '.Money::fromCents($debit).', credits '.Money::fromCents($credit).'.');
            }
        });

        $data = $validator->validate();
        $data['interval'] = (int) ($data['interval'] ?? 1);
        $data['day_of_month'] = in_array($data['frequency'], ['monthly', 'quarterly', 'yearly'], true)
            ? (int) ($data['day_of_month'] ?? Carbon::parse($data['start_date'])->day)
            : null;
        $data['lines'] = array_map(fn (array $line) => [
            'chart_of_account_id' => (int) $line['chart_of_account_id'],
            'cost_center_id' => ($line['cost_center_id'] ?? null) ?: null,
            'debit' => Money::fromCents(Money::toCents((string) ($line['debit'] ?? 0))),
            'credit' => Money::fromCents(Money::toCents((string) ($line['credit'] ?? 0))),
            'description' => ($line['description'] ?? null) ?: null,
        ], $data['lines']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data  validated (see validate())
     */
    public function create(array $data): RecurringEntry
    {
        return DB::transaction(function () use ($data): RecurringEntry {
            $entry = new RecurringEntry($this->attributes($data));
            $entry->forceFill(['next_run_date' => Carbon::parse($data['start_date'])->toDateString(), 'runs_count' => 0, 'is_active' => true])->save();
            $this->saveLines($entry, $data['lines']);
            AccountingAuditLog::record($entry, 'RECURRING_ENTRY_CREATED', null, ['name' => $entry->name, 'frequency' => $entry->frequency, 'mode' => $entry->mode]);

            return $entry->load('lines');
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated (see validate())
     */
    public function update(RecurringEntry $entry, array $data): RecurringEntry
    {
        return DB::transaction(function () use ($entry, $data): RecurringEntry {
            $entry = RecurringEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->runs_count > 0 && Carbon::parse($data['start_date'])->toDateString() !== $entry->start_date->toDateString()) {
                throw new AccountingException('The start date cannot change once the template has generated entries.');
            }

            $entry->fill($this->attributes($data));

            if ($entry->runs_count === 0) {
                $entry->forceFill(['next_run_date' => Carbon::parse($data['start_date'])->toDateString()]);
            }

            $entry->save();
            $this->saveLines($entry, $data['lines']);
            AccountingAuditLog::record($entry, 'RECURRING_ENTRY_UPDATED', null, ['name' => $entry->name, 'frequency' => $entry->frequency, 'mode' => $entry->mode]);

            return $entry->load('lines');
        });
    }

    public function delete(RecurringEntry $entry): void
    {
        if ($entry->runs()->exists()) {
            throw new AccountingException('This template has generated entries: pause it instead of deleting it.');
        }

        AccountingAuditLog::record($entry, 'RECURRING_ENTRY_DELETED', ['name' => $entry->name], null);
        $entry->delete();
    }

    public function pause(RecurringEntry $entry): RecurringEntry
    {
        $entry->forceFill(['is_active' => false])->save();
        AccountingAuditLog::record($entry, 'RECURRING_ENTRY_PAUSED', null, null);

        return $entry;
    }

    /**
     * Resume a paused template. Occurrences missed while it was paused are skipped (default) or generated.
     */
    public function resume(RecurringEntry $entry, bool $skipMissed = true): RecurringEntry
    {
        if ($entry->next_run_date === null) {
            throw new AccountingException('This template has finished: it has no occurrences left.');
        }

        $next = $entry->next_run_date;
        $today = now()->startOfDay();

        while ($skipMissed && $next->lt($today)) {
            $next = $this->nextDate($entry, $next);
        }

        $entry->forceFill(['is_active' => true, 'next_run_date' => $this->withinLimits($entry, $next)])->save();
        AccountingAuditLog::record($entry, 'RECURRING_ENTRY_RESUMED', null, ['next_run_date' => $entry->next_run_date?->toDateString(), 'skipped_missed' => $skipMissed]);

        return $entry;
    }

    /**
     * The date after $from on the template's schedule (monthly dates keep their day, clamped to short months).
     */
    public function nextDate(RecurringEntry $entry, CarbonInterface $from): CarbonInterface
    {
        $from = $from->copy()->startOfDay();
        $interval = max(1, $entry->interval);

        if ($entry->frequency === 'daily') {
            return $from->addDays($interval);
        }

        if ($entry->frequency === 'weekly') {
            return $from->addWeeks($interval);
        }

        $months = $interval * match ($entry->frequency) {
            'quarterly' => 3, 'yearly' => 12, default => 1
        };
        $next = $from->copy()->startOfMonth()->addMonthsNoOverflow($months);

        return $next->day(min($entry->day_of_month ?? $from->day, $next->daysInMonth));
    }

    /**
     * The next $count scheduled dates (for the preview on the form), from the next run date or a given start.
     *
     * @return list<string>
     */
    public function upcoming(RecurringEntry $entry, int $count = 6): array
    {
        $dates = [];
        $date = $entry->next_run_date;

        while ($date !== null && count($dates) < $count) {
            $date = $this->withinLimits($entry, $date, count($dates));

            if ($date === null) {
                break;
            }

            $dates[] = $date->toDateString();
            $date = $this->nextDate($entry, $date);
        }

        return $dates;
    }

    /**
     * Templates with an occurrence due on or before $asOf.
     *
     * @return Collection<int, RecurringEntry>
     */
    public function due(?CarbonInterface $asOf = null): Collection
    {
        return RecurringEntry::query()->where('is_active', true)->whereNotNull('next_run_date')
            ->whereDate('next_run_date', '<=', ($asOf ?? now())->toDateString())->orderBy('next_run_date')->orderBy('id')->get();
    }

    /**
     * Generate every occurrence of one template that is due ($asOf, default today), up to the catch-up limit.
     *
     * @return list<RecurringEntryRun>
     */
    public function runDue(RecurringEntry $entry, ?CarbonInterface $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $limit = max(1, (int) config('accounting.recurring.max_catch_up', 12));
        $runs = [];

        while (count($runs) < $limit) {
            $run = $this->runNext($entry->refresh(), $asOf);

            if ($run === null) {
                break;
            }

            $runs[] = $run;

            if ($run->status === 'failed') {
                break;
            }
        }

        return $runs;
    }

    /**
     * Generate the next occurrence now, even if it is not due yet (the "Run now" button).
     */
    public function runNow(RecurringEntry $entry): RecurringEntryRun
    {
        $entry->refresh();

        if ($entry->next_run_date === null) {
            throw new AccountingException('This template has finished: it has no occurrences left.');
        }

        return $this->runNext($entry, $entry->next_run_date->copy(), force: true)
            ?? throw new AccountingException('There is nothing to generate.');
    }

    /**
     * One occurrence, if one is due: the template's creator acts as the user (so posting rights, approvals and
     * control accounts apply as if they had made the entry), and the run row is written once per scheduled date.
     */
    private function runNext(RecurringEntry $entry, CarbonInterface $asOf, bool $force = false): ?RecurringEntryRun
    {
        return DB::transaction(function () use ($entry, $asOf, $force): ?RecurringEntryRun {
            $entry = RecurringEntry::query()->lockForUpdate()->with('lines')->findOrFail($entry->id);

            if ((! $entry->is_active && ! $force) || $entry->next_run_date === null || $entry->next_run_date->gt($asOf)) {
                return null;
            }

            $date = $entry->next_run_date->copy();

            if (RecurringEntryRun::query()->where('recurring_entry_id', $entry->id)->whereDate('run_date', $date->toDateString())->where('status', '<>', 'failed')->exists()) {
                // Already generated by another scheduler: just move on.
                $this->advance($entry, $date);

                return null;
            }

            $actor = $this->actor($entry);
            $previous = Auth::user();

            if ($actor !== null) {
                Auth::setUser($actor);
            }

            try {
                [$status, $journal, $error] = $this->generate($entry, $date, $actor);
            } finally {
                if ($actor !== null) {
                    $previous !== null ? Auth::setUser($previous) : Auth::forgetUser();
                }
            }

            // A failed attempt of this date is retried in place (dates are compared as dates: SQLite keeps a time part).
            $run = RecurringEntryRun::query()->where('recurring_entry_id', $entry->id)->whereDate('run_date', $date->toDateString())->first()
                ?? new RecurringEntryRun(['recurring_entry_id' => $entry->id, 'run_date' => $date->toDateString()]);
            $run->fill(['status' => $status, 'journal_entry_id' => $journal?->id, 'error' => $error])->save();

            if ($status !== 'failed') {
                $this->advance($entry, $date);
                AccountingAuditLog::record($entry, 'RECURRING_ENTRY_RUN', null, ['run_date' => $date->toDateString(), 'status' => $status, 'journal_entry_id' => $journal?->id]);
            }

            return $run;
        });
    }

    /**
     * @return array{0: string, 1: JournalEntry|null, 2: string|null} status, the entry, the reason it stopped short
     */
    private function generate(RecurringEntry $entry, CarbonInterface $date, ?Authenticatable $actor): array
    {
        try {
            $journal = DB::transaction(fn () => $this->journals->create([
                'voucher_type_id' => $entry->voucher_type_id,
                'entry_date' => $date->toDateString(),
                'reference' => $entry->reference ?: 'REC-'.$entry->id,
                'description' => $entry->description ?: $entry->name,
                'lines' => $entry->lines->map(fn (RecurringEntryLine $line) => [
                    'chart_of_account_id' => $line->chart_of_account_id,
                    'cost_center_id' => $line->cost_center_id,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'description' => $line->description,
                ])->all(),
            ]));
        } catch (\Throwable $exception) {
            return ['failed', null, $this->message($exception)];
        }

        if ($entry->mode !== 'post') {
            return ['draft', $journal, null];
        }

        if ($actor === null || ! $actor->can('journal-entries.post')) {
            return ['draft', $journal, 'The template\'s creator may not post entries: left as a draft.'];
        }

        try {
            if ($this->approvals->requiresApproval($journal)) {
                $this->approvals->submit($journal);

                return ['submitted', $journal->refresh(), null];
            }

            $this->poster->execute($journal);

            return ['posted', $journal->refresh(), null];
        } catch (AccountingException $exception) {
            return ['draft', $journal->refresh(), $exception->getMessage()];
        }
    }

    private function advance(RecurringEntry $entry, CarbonInterface $from): void
    {
        $entry->runs_count++;
        $entry->last_run_at = now();
        $entry->next_run_date = $this->withinLimits($entry, $this->nextDate($entry, $from));

        if ($entry->next_run_date === null) {
            $entry->is_active = false;
        }

        $entry->save();
    }

    /**
     * The date, or null when the end date or the run limit has passed.
     */
    private function withinLimits(RecurringEntry $entry, CarbonInterface $date, ?int $ahead = null): ?CarbonInterface
    {
        $made = $entry->runs_count + ($ahead ?? 0);

        if (($entry->max_runs !== null && $made >= $entry->max_runs) || ($entry->end_date !== null && $date->gt($entry->end_date))) {
            return null;
        }

        return $date;
    }

    private function actor(RecurringEntry $entry): ?Authenticatable
    {
        $id = $entry->getAttributes()['created_by'] ?? null;
        $model = (string) config('auth.providers.users.model');

        return $id !== null && class_exists($model) ? $model::query()->find($id) : null;
    }

    private function message(\Throwable $exception): string
    {
        return $exception instanceof ValidationException
            ? collect($exception->errors())->flatten()->implode(' ')
            : ($exception instanceof AccountingException ? $exception->getMessage() : 'The entry could not be created: '.class_basename($exception).'.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'voucher_type_id', 'frequency', 'interval', 'day_of_month', 'start_date', 'end_date', 'max_runs', 'mode', 'reference', 'description']));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function saveLines(RecurringEntry $entry, array $lines): void
    {
        $entry->lines()->get()->each->delete();

        foreach ($lines as $index => $line) {
            $entry->lines()->create([...$line, 'line_no' => $index + 1]);
        }
    }
}
