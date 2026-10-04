<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Actions\CloseAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReopenAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Create / update / delete accounting periods with integrity rules:
 *  - periods may not overlap;
 *  - dates of a period that already contains journal entries cannot change;
 *  - status changes go through the close / reopen actions and need periods.close / periods.reopen;
 *  - periods that contain journal entries cannot be deleted.
 */
class AccountingPeriodService
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(['open', 'closed', 'archived'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AccountingPeriod
    {
        return DB::transaction(function () use ($data): AccountingPeriod {
            $this->assertNoOverlap($data['start_date'], $data['end_date']);

            if (($data['status'] ?? 'open') !== 'open') {
                throw new AccountingException('New accounting periods must be created as open. Close them via the close action.');
            }

            $data['status'] = 'open';

            return AccountingPeriod::query()->create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AccountingPeriod $period, array $data): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $data): AccountingPeriod {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $status = $data['status'] ?? $period->status;
            unset($data['status']);

            $datesChanged = ! Carbon::parse($data['start_date'])->isSameDay($period->start_date)
                || ! Carbon::parse($data['end_date'])->isSameDay($period->end_date);

            if ($datesChanged) {
                if ($this->hasJournalEntries($period)) {
                    throw new AccountingException('The dates of a period that contains journal entries cannot be changed.');
                }

                $this->assertNoOverlap($data['start_date'], $data['end_date'], $period->id);
            }

            $period->update($data);

            return $this->transition($period->refresh(), $status);
        });
    }

    public function delete(AccountingPeriod $period): void
    {
        if ($this->hasJournalEntries($period)) {
            throw new AccountingException('Accounting periods that contain journal entries cannot be deleted.');
        }

        if ($period->status !== 'open') {
            throw new AccountingException('Only open accounting periods can be deleted.');
        }

        $period->delete();
    }

    private function transition(AccountingPeriod $period, string $status): AccountingPeriod
    {
        if ($status === $period->status) {
            return $period;
        }

        if ($period->status === 'open' && $status === 'closed') {
            $this->authorize('periods.close');

            return app(CloseAccountingPeriodAction::class)->execute($period);
        }

        if ($period->status === 'closed' && $status === 'open') {
            $this->authorize('periods.reopen');

            return app(ReopenAccountingPeriodAction::class)->execute($period);
        }

        if ($period->status === 'closed' && $status === 'archived') {
            $this->authorize('periods.close');
            $period->forceFill(['status' => 'archived'])->save();

            return $period->refresh();
        }

        throw new AccountingException("An accounting period cannot move from {$period->status} to {$status}.");
    }

    private function authorize(string $ability): void
    {
        $user = Auth::user();

        abort_if($user !== null && ! $user->can($ability), 403, "Changing the period status requires the {$ability} permission.");
    }

    private function assertNoOverlap(string $start, string $end, ?int $ignoreId = null): void
    {
        $overlap = AccountingPeriod::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereDate('start_date', '<=', Carbon::parse($end)->toDateString())
            ->whereDate('end_date', '>=', Carbon::parse($start)->toDateString())
            ->first();

        if ($overlap) {
            throw new AccountingException("The period overlaps with \"{$overlap->name}\" ({$overlap->start_date->toDateString()} – {$overlap->end_date->toDateString()}).");
        }
    }

    private function hasJournalEntries(AccountingPeriod $period): bool
    {
        return JournalEntry::query()
            ->where(function ($query) use ($period): void {
                $query->where('accounting_period_id', $period->id)
                    ->orWhere(fn ($q) => $q
                        ->whereDate('entry_date', '>=', $period->start_date)
                        ->whereDate('entry_date', '<=', $period->end_date));
            })
            ->exists();
    }
}
