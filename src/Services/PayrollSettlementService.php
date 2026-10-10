<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\LeaveType;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryRevision;
use Alimarchal\LaravelChartOfAccounts\Models\Settlement;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Support\SalaryHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Final settlement of an employee who leaves: gratuity for the years served, payment for unused leave, a signed adjustment
 * (notice pay, a recovery, a bonus), less the loans still owed, as one net amount. A draft is worked out and can be adjusted;
 * posting books it (and closes the loans it recovers), paying clears the liability against a bank, voiding reverses all of it.
 */
class PayrollSettlementService
{
    public function __construct(private readonly JournalEntryService $journals, private readonly PayrollLoanService $loans, private readonly PayrollAttendanceService $attendance) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        return Validator::make($input, [
            'employee_id' => ['required', 'integer', CompanyRule::exists('accounting_employees', 'id')],
            'leave_date' => ['required', 'date'],
            'gratuity' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'leave_days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'adjustment' => ['nullable', 'numeric', 'min:-999999999999', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:300'],
        ])->validate();
    }

    /**
     * Work a settlement out (nothing is saved). gratuity and leave_days may be given to override the working.
     *
     * @param  array<string, mixed>  $data  validated
     * @return array<string, mixed>
     */
    public function calculate(array $data): array
    {
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $leave = Carbon::parse($data['leave_date'])->startOfDay();

        if ($leave->lt(Carbon::parse($employee->join_date)->startOfDay())) {
            throw ValidationException::withMessages(['leave_date' => 'The leaving date is before the employee joined.']);
        }

        $settings = (array) config('accounting.payroll.gratuity', []);
        $basic = SalaryHistory::salaryOn($employee, $leave, SalaryRevision::query()->where('employee_id', $employee->id)->orderBy('effective_from')->orderBy('id')->get());
        $years = round(Carbon::parse($employee->join_date)->startOfDay()->diffInDays($leave) / 365.25, 2);
        $minYears = (float) ($settings['min_years'] ?? 1);
        $gratuity = isset($data['gratuity']) && $data['gratuity'] !== ''
            ? Money::toCents((string) $data['gratuity'])
            : ($years >= $minYears ? (int) round($basic / 30 * (float) ($settings['days_per_year'] ?? 30) * $years) : 0);

        $leaveDays = isset($data['leave_days']) && $data['leave_days'] !== '' ? (float) $data['leave_days'] : $this->unusedLeaveDays($employee, $leave);
        $encashment = (int) round($basic / max(1.0, (float) ($settings['days_per_month'] ?? 30)) * $leaveDays);
        $adjustment = Money::toCents((string) ($data['adjustment'] ?? 0));
        $recoveries = $this->loans->activeOf($employee->id);
        $recovery = array_sum(array_map(fn (array $row): int => Money::toCents($row['outstanding']), $recoveries));
        $net = $gratuity + $encashment + $adjustment - $recovery;

        return [
            'employee_id' => $employee->id, 'leave_date' => $leave->toDateString(), 'service_years' => number_format($years, 2, '.', ''), 'basic' => Money::fromCents($basic),
            'gratuity' => Money::fromCents($gratuity), 'leave_days' => number_format($leaveDays, 2, '.', ''), 'leave_encashment' => Money::fromCents($encashment),
            'adjustment' => Money::fromCents($adjustment), 'loan_recovery' => Money::fromCents($recovery), 'net' => Money::fromCents($net),
            'loans' => array_map(fn (array $row): array => ['loan_id' => $row['loan']->id, 'kind' => $row['loan']->kind, 'outstanding' => $row['outstanding']], $recoveries), 'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * The days of the configured leave type the employee has left in the year of leaving.
     */
    private function unusedLeaveDays(Employee $employee, Carbon $leave): float
    {
        $code = config('accounting.payroll.gratuity.leave_type');
        $type = $code ? LeaveType::query()->where('code', (string) $code)->first() : null;

        return $type === null ? 0.0 : max(0.0, $this->attendance->balance($employee->id, $type, (int) $leave->format('Y'))['balance']);
    }

    /**
     * Save a draft settlement (a draft of the same employee is replaced).
     *
     * @param  array<string, mixed>  $data  validated
     */
    public function create(array $data): Settlement
    {
        $work = $this->calculate($data);

        if (Money::toCents($work['net']) < 0) {
            throw new AccountingException('The loans owed ('.$work['loan_recovery'].') are more than the settlement: add an adjustment or settle the loans first.');
        }

        if (Settlement::query()->where('employee_id', $work['employee_id'])->whereIn('status', ['posted', 'paid'])->exists()) {
            throw new AccountingException('This employee already has a settlement.');
        }

        return DB::transaction(function () use ($work): Settlement {
            Settlement::query()->where('employee_id', $work['employee_id'])->where('status', 'draft')->delete();
            $settlement = Settlement::query()->create(collect($work)->only(['employee_id', 'leave_date', 'service_years', 'basic', 'gratuity', 'leave_days', 'leave_encashment', 'adjustment', 'loan_recovery', 'net', 'notes'])->all() + ['status' => 'draft']);
            AccountingAuditLog::record($settlement, 'SETTLEMENT_PREPARED', null, null, ['employee_id' => $settlement->employee_id, 'net' => $settlement->net]);

            return $settlement->refresh();
        });
    }

    public function delete(Settlement $settlement): void
    {
        $this->assertStatus($settlement, 'draft', 'Only a draft settlement can be deleted; void a posted one instead.');
        $settlement->delete();
    }

    /**
     * Book it: gratuity, leave pay and the adjustment as an expense; the loans recovered and the net owed as liabilities.
     */
    public function post(Settlement $settlement, ?int $payableAccountId = null, ?string $date = null): Settlement
    {
        return DB::transaction(function () use ($settlement, $payableAccountId, $date): Settlement {
            $settlement = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            $this->assertStatus($settlement, 'draft', 'Only a draft settlement can be posted.');
            $work = $this->calculate(['employee_id' => $settlement->employee_id, 'leave_date' => $settlement->leave_date->toDateString(), 'gratuity' => $settlement->gratuity, 'leave_days' => $settlement->leave_days, 'adjustment' => $settlement->adjustment]);

            if (Money::toCents($work['net']) < 0) {
                throw new AccountingException('The loans owed are more than the settlement.');
            }

            $employee = Employee::query()->findOrFail($settlement->employee_id);
            $expense = $this->account((string) (config('accounting.payroll.gratuity.expense_account') ?: config('accounting.payroll.salary_expense_account')), 'gratuity expense');
            $payable = $payableAccountId
                ? ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->findOrFail($payableAccountId)
                : $this->account((string) (config('accounting.payroll.gratuity.payable_account') ?: config('accounting.payroll.net_payable_account')), 'net salary payable');
            $debit = Money::toCents($work['gratuity']) + Money::toCents($work['leave_encashment']) + Money::toCents($work['adjustment']);

            if ($debit < 0) {
                throw new AccountingException('The adjustment cannot be more than the amounts it is taken from.');
            }

            $recovered = [];
            $lines = [['chart_of_account_id' => $expense->id, 'cost_center_id' => $employee->cost_center_id, 'debit' => Money::fromCents($debit), 'credit' => 0, 'description' => 'Final settlement '.$employee->code]];

            foreach ($this->loans->activeOf($employee->id) as $row) {
                $recovered[] = $this->loans->recoverFromSettlement($row['loan']);
            }

            if (Money::toCents($work['loan_recovery']) > 0) {
                $lines[] = ['chart_of_account_id' => $this->loans->accountId(), 'debit' => 0, 'credit' => $work['loan_recovery'], 'description' => 'Loans recovered '.$employee->code];
            }

            $lines[] = ['chart_of_account_id' => $payable->id, 'debit' => 0, 'credit' => $work['net'], 'description' => 'Net settlement '.$employee->name];
            $posted = Carbon::parse($date ?? $settlement->leave_date)->toDateString();
            $entry = $this->journals->create([
                'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
                'origin_module' => PayrollService::ORIGIN,
                'entry_date' => $posted,
                'reference' => 'SETTLEMENT-'.$employee->code,
                'description' => 'Final settlement of '.$employee->name,
                'lines' => $lines,
                'auto_post' => true,
                'system_generated' => true,
            ]);
            $settlement->forceFill([
                'status' => 'posted', 'payable_account_id' => $payable->id, 'journal_entry_id' => $entry->id, 'posted_on' => $posted, 'breakdown' => json_encode($recovered),
                'service_years' => $work['service_years'], 'basic' => $work['basic'], 'leave_encashment' => $work['leave_encashment'], 'loan_recovery' => $work['loan_recovery'], 'net' => $work['net'],
            ])->save();

            if ($employee->leave_date === null || $employee->leave_date->gt($settlement->leave_date)) {
                $employee->forceFill(['leave_date' => $settlement->leave_date])->save();
            }

            AccountingAuditLog::record($settlement, 'SETTLEMENT_POSTED', null, null, ['net' => $settlement->net, 'journal_entry_id' => $entry->id]);

            return $settlement->refresh();
        });
    }

    public function pay(Settlement $settlement, int $fromAccountId, ?string $date = null): Settlement
    {
        return DB::transaction(function () use ($settlement, $fromAccountId, $date): Settlement {
            $settlement = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            $this->assertStatus($settlement, 'posted', 'Only a posted settlement can be paid.');
            $from = ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->findOrFail($fromAccountId);
            $paid = Carbon::parse($date ?? now())->toDateString();
            $entry = $this->journals->create([
                'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
                'origin_module' => PayrollService::ORIGIN,
                'entry_date' => $paid,
                'reference' => 'SETTLEMENT-PAID-'.$settlement->id,
                'description' => 'Final settlement paid',
                'lines' => [['chart_of_account_id' => $settlement->payable_account_id, 'debit' => $settlement->net, 'credit' => 0], ['chart_of_account_id' => $from->id, 'debit' => 0, 'credit' => $settlement->net]],
                'auto_post' => true,
                'system_generated' => true,
            ]);
            $settlement->forceFill(['status' => 'paid', 'payment_entry_id' => $entry->id, 'paid_on' => $paid])->save();
            AccountingAuditLog::record($settlement, 'SETTLEMENT_PAID', null, null, ['net' => $settlement->net]);

            return $settlement->refresh();
        });
    }

    /**
     * Reverse a posted or paid settlement; the loans it recovered are open again.
     */
    public function void(Settlement $settlement): Settlement
    {
        return DB::transaction(function () use ($settlement): Settlement {
            $settlement = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);

            if (! in_array($settlement->status, ['posted', 'paid'], true)) {
                throw new AccountingException('Only a posted or paid settlement can be voided.');
            }

            foreach ([$settlement->payment_entry_id, $settlement->journal_entry_id] as $entryId) {
                if ($entryId !== null) {
                    $this->journals->reverse(JournalEntry::query()->findOrFail($entryId), 'Void of final settlement');
                }
            }

            foreach ((array) json_decode((string) $settlement->breakdown, true) as $recovered) {
                $this->loans->restoreRecovery($recovered);
            }

            $settlement->forceFill(['status' => 'void'])->save();
            AccountingAuditLog::record($settlement, 'SETTLEMENT_VOIDED', null, null, ['employee_id' => $settlement->employee_id]);

            return $settlement->refresh();
        });
    }

    /**
     * @param  Collection<int, Settlement>|null  $rows
     * @return list<array<string, mixed>>
     */
    public function present(?Collection $rows = null): array
    {
        $rows ??= Settlement::query()->orderByDesc('id')->limit(300)->get();
        $employees = Employee::query()->whereIn('id', $rows->pluck('employee_id'))->get(['id', 'code', 'name'])->keyBy('id');

        return $rows->map(fn (Settlement $row): array => [
            'id' => $row->id, 'employee_id' => $row->employee_id, 'employee_code' => $employees[$row->employee_id]->code ?? '', 'employee_name' => $employees[$row->employee_id]->name ?? '',
            'leave_date' => $row->leave_date->toDateString(), 'status' => $row->status, 'service_years' => $row->service_years, 'basic' => $row->basic, 'gratuity' => $row->gratuity, 'leave_days' => $row->leave_days,
            'leave_encashment' => $row->leave_encashment, 'adjustment' => $row->adjustment, 'loan_recovery' => $row->loan_recovery, 'net' => $row->net,
            'journal_entry_id' => $row->journal_entry_id, 'payment_entry_id' => $row->payment_entry_id, 'posted_on' => $row->posted_on?->toDateString(), 'paid_on' => $row->paid_on?->toDateString(), 'notes' => $row->notes,
        ])->values()->all();
    }

    private function assertStatus(Settlement $settlement, string $status, string $message): void
    {
        if ($settlement->status !== $status) {
            throw new AccountingException($message);
        }
    }

    private function account(string $code, string $label): ChartOfAccount
    {
        return ChartOfAccount::query()->where('account_code', $code)->where('is_group', false)->first()
            ?? throw new AccountingException("The {$label} account ({$code}) does not exist: set it in accounting.payroll.");
    }
}
