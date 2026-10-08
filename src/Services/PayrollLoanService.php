<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\Loan;
use Alimarchal\LaravelChartOfAccounts\Models\LoanInstallment;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Loans and salary advances to employees: the schedule of instalments, the payout (booked to the employee loans account),
 * recovery from salary by the payroll run, skipping an instalment, and settling the rest in cash.
 *
 * Instalments of an active loan are taken from the salary of their month (or the first run after it); a run that is
 * recalculated, deleted or voided gives them back.
 */
class PayrollLoanService
{
    public function __construct(private readonly JournalEntryService $journals) {}

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
            'kind' => ['required', Rule::in(['loan', 'advance'])],
            'principal' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'installments' => ['required', 'integer', 'min:1', 'max:120'],
            'start_month' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:200'],
        ])->validate();
    }

    /**
     * Record a loan (a draft until paid out) with its schedule: equal instalments, the last one takes the rounding.
     *
     * @param  array<string, mixed>  $data  validated
     */
    public function create(array $data): Loan
    {
        return DB::transaction(function () use ($data): Loan {
            $loan = Loan::query()->create(['employee_id' => $data['employee_id'], 'kind' => $data['kind'], 'principal' => $data['principal'], 'installments' => $data['installments'], 'start_month' => Carbon::parse($data['start_month'])->startOfMonth()->toDateString(), 'status' => 'draft', 'notes' => $data['notes'] ?? null]);
            $cents = Money::toCents((string) $data['principal']);
            $each = intdiv($cents, (int) $data['installments']);

            for ($i = 0; $i < (int) $data['installments']; $i++) {
                $amount = $i === (int) $data['installments'] - 1 ? $cents - $each * $i : $each;
                LoanInstallment::query()->create(['loan_id' => $loan->id, 'due_month' => $loan->start_month->copy()->addMonths($i)->toDateString(), 'amount' => Money::fromCents($amount), 'status' => 'scheduled']);
            }

            AccountingAuditLog::record($loan, 'LOAN_CREATED', null, null, ['employee_id' => $loan->employee_id, 'principal' => $loan->principal, 'installments' => $loan->installments]);

            return $loan->refresh();
        });
    }

    /**
     * Pay the loan out of a bank or cash account: debit the employee loans account, credit the bank.
     */
    public function disburse(Loan $loan, int $fromAccountId, ?string $date = null): Loan
    {
        return DB::transaction(function () use ($loan, $fromAccountId, $date): Loan {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->status !== 'draft') {
                throw new AccountingException('Only a loan not yet paid out can be paid out.');
            }

            $from = ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->findOrFail($fromAccountId);
            $employee = Employee::query()->findOrFail($loan->employee_id);
            $paid = Carbon::parse($date ?? now())->toDateString();
            $entry = $this->journals->create([
                'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
                'origin_module' => PayrollService::ORIGIN,
                'entry_date' => $paid,
                'reference' => 'LOAN-'.$loan->id,
                'description' => ucfirst($loan->kind).' to '.$employee->name,
                'lines' => [
                    ['chart_of_account_id' => $this->loanAccount()->id, 'debit' => $loan->principal, 'credit' => 0, 'description' => $employee->code.' '.$employee->name],
                    ['chart_of_account_id' => $from->id, 'debit' => 0, 'credit' => $loan->principal],
                ],
                'auto_post' => true,
                'system_generated' => true,
            ]);
            $loan->forceFill(['status' => 'active', 'issued_on' => $paid, 'journal_entry_id' => $entry->id])->save();
            AccountingAuditLog::record($loan, 'LOAN_DISBURSED', null, null, ['principal' => $loan->principal, 'journal_entry_id' => $entry->id]);

            return $loan->refresh();
        });
    }

    /**
     * Cancel a loan that has not been paid out.
     */
    public function cancel(Loan $loan): Loan
    {
        if ($loan->status !== 'draft') {
            throw new AccountingException('A loan that was paid out cannot be cancelled: settle what is left instead.');
        }

        DB::transaction(function () use ($loan): void {
            LoanInstallment::query()->where('loan_id', $loan->id)->update(['status' => 'cancelled']);
            $loan->forceFill(['status' => 'cancelled'])->save();
            AccountingAuditLog::record($loan, 'LOAN_CANCELLED', null, null, ['principal' => $loan->principal]);
        });

        return $loan->refresh();
    }

    /**
     * Skip the next instalment: it moves to the end of the schedule.
     */
    public function skip(Loan $loan): Loan
    {
        if ($loan->status !== 'active') {
            throw new AccountingException('Only an active loan has instalments to skip.');
        }

        return DB::transaction(function () use ($loan): Loan {
            $next = LoanInstallment::query()->where('loan_id', $loan->id)->where('status', 'scheduled')->orderBy('due_month')->orderBy('id')->first();

            if ($next === null) {
                throw new AccountingException('There is no instalment left to skip.');
            }

            $last = LoanInstallment::query()->where('loan_id', $loan->id)->where('status', '<>', 'cancelled')->max('due_month');
            $next->forceFill(['status' => 'cancelled'])->save();
            LoanInstallment::query()->create(['loan_id' => $loan->id, 'due_month' => Carbon::parse($last)->startOfMonth()->addMonth()->toDateString(), 'amount' => $next->amount, 'status' => 'scheduled']);
            AccountingAuditLog::record($loan, 'LOAN_INSTALLMENT_SKIPPED', null, null, ['due_month' => $next->due_month->format('Y-m')]);

            return $loan->refresh();
        });
    }

    /**
     * The employee pays back what is left in cash: debit the bank, credit the employee loans account, close the loan.
     */
    public function settle(Loan $loan, int $toAccountId, ?string $date = null): Loan
    {
        return DB::transaction(function () use ($loan, $toAccountId, $date): Loan {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->status !== 'active') {
                throw new AccountingException('Only an active loan can be settled.');
            }

            if (LoanInstallment::query()->where('loan_id', $loan->id)->where('status', 'included')->whereIn('payroll_run_id', PayrollRun::query()->where('status', 'draft')->select('id'))->exists()) {
                throw new AccountingException('An instalment is part of a draft payroll run: post or delete the run first.');
            }

            $left = Money::toCents($this->outstanding($loan));

            if ($left > 0) {
                $to = ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->findOrFail($toAccountId);
                $employee = Employee::query()->findOrFail($loan->employee_id);
                $this->journals->create([
                    'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
                    'origin_module' => PayrollService::ORIGIN,
                    'entry_date' => Carbon::parse($date ?? now())->toDateString(),
                    'reference' => 'LOAN-'.$loan->id.'-SETTLED',
                    'description' => 'Loan settled by '.$employee->name,
                    'lines' => [
                        ['chart_of_account_id' => $to->id, 'debit' => Money::fromCents($left), 'credit' => 0],
                        ['chart_of_account_id' => $this->loanAccount()->id, 'debit' => 0, 'credit' => Money::fromCents($left), 'description' => $employee->code.' '.$employee->name],
                    ],
                    'auto_post' => true,
                    'system_generated' => true,
                ]);
            }

            LoanInstallment::query()->where('loan_id', $loan->id)->where('status', 'scheduled')->update(['status' => 'cancelled']);
            $loan->forceFill(['status' => 'closed', 'settled_amount' => Money::fromCents(Money::toCents($loan->settled_amount) + $left)])->save();
            AccountingAuditLog::record($loan, 'LOAN_SETTLED', null, null, ['amount' => Money::fromCents($left)]);

            return $loan->refresh();
        });
    }

    /**
     * Principal less what salary has recovered (instalments in a posted or paid run) and what was settled in cash.
     */
    public function outstanding(Loan $loan): string
    {
        $recovered = LoanInstallment::query()->where('loan_id', $loan->id)->where('status', 'included')
            ->whereIn('payroll_run_id', PayrollRun::query()->whereIn('status', ['posted', 'paid'])->select('id'))->get()
            ->sum(fn (LoanInstallment $row): int => Money::toCents($row->amount));

        return Money::fromCents(max(0, Money::toCents($loan->principal) - Money::toCents($loan->settled_amount) - $recovered));
    }

    /**
     * Instalments to take from the salary of a month, by employee, with their position in the schedule.
     *
     * @return array<int, list<array{installment: LoanInstallment, number: int, of: int, kind: string}>>
     */
    public function dueFor(Carbon $periodEnd): array
    {
        $loans = Loan::query()->where('status', 'active')->get()->keyBy('id');

        if ($loans->isEmpty()) {
            return [];
        }

        $position = [];
        $total = [];

        foreach (LoanInstallment::query()->whereIn('loan_id', $loans->keys())->where('status', '<>', 'cancelled')->orderBy('due_month')->orderBy('id')->get() as $row) {
            $total[$row->loan_id] = ($total[$row->loan_id] ?? 0) + 1;
            $position[$row->id] = $total[$row->loan_id];
        }

        $due = [];

        foreach (LoanInstallment::query()->whereIn('loan_id', $loans->keys())->where('status', 'scheduled')->whereDate('due_month', '<=', $periodEnd->toDateString())->orderBy('due_month')->orderBy('id')->get() as $row) {
            $loan = $loans[$row->loan_id];
            $due[$loan->employee_id][] = ['installment' => $row, 'number' => $position[$row->id], 'of' => $total[$row->loan_id], 'kind' => $loan->kind];
        }

        return $due;
    }

    /**
     * @param  list<int>  $installmentIds
     */
    public function attach(array $installmentIds, PayrollRun $run): void
    {
        if ($installmentIds !== []) {
            LoanInstallment::query()->whereIn('id', $installmentIds)->update(['status' => 'included', 'payroll_run_id' => $run->id]);
        }
    }

    /**
     * A recalculated, deleted or voided run gives its instalments back.
     */
    public function release(PayrollRun $run): void
    {
        LoanInstallment::query()->where('payroll_run_id', $run->id)->update(['status' => 'scheduled', 'payroll_run_id' => null]);
    }

    /**
     * Loans whose instalments are all recovered are closed once the run is posted.
     */
    public function closeRecovered(PayrollRun $run): void
    {
        $ids = LoanInstallment::query()->where('payroll_run_id', $run->id)->pluck('loan_id')->unique();

        foreach (Loan::query()->whereIn('id', $ids)->where('status', 'active')->get() as $loan) {
            if (Money::toCents($this->outstanding($loan)) === 0) {
                $loan->forceFill(['status' => 'closed'])->save();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Loan $loan, ?Employee $employee = null): array
    {
        $employee ??= Employee::query()->find($loan->employee_id);
        $rows = LoanInstallment::query()->where('loan_id', $loan->id)->orderBy('due_month')->orderBy('id')->get();

        return [
            'id' => $loan->id, 'employee_id' => $loan->employee_id, 'employee_code' => $employee?->code, 'employee_name' => $employee?->name, 'kind' => $loan->kind, 'principal' => $loan->principal, 'installments' => $loan->installments,
            'start_month' => $loan->start_month->format('Y-m'), 'issued_on' => $loan->issued_on?->toDateString(), 'status' => $loan->status, 'outstanding' => $loan->status === 'cancelled' ? '0.00' : $this->outstanding($loan),
            'journal_entry_id' => $loan->journal_entry_id, 'notes' => $loan->notes,
            'schedule' => $rows->map(fn (LoanInstallment $row): array => ['id' => $row->id, 'due_month' => $row->due_month->format('Y-m'), 'amount' => $row->amount, 'status' => $row->status, 'payroll_run_id' => $row->payroll_run_id])->values()->all(),
        ];
    }

    public function accountId(): int
    {
        return $this->loanAccount()->id;
    }

    private function loanAccount(): ChartOfAccount
    {
        $code = (string) config('accounting.payroll.employee_loans_account', '1105');

        return ChartOfAccount::query()->where('account_code', $code)->where('is_group', false)->first()
            ?? throw new AccountingException("The employee loans account ({$code}) does not exist: set it in accounting.payroll.employee_loans_account.");
    }
}
