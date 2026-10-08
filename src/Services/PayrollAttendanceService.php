<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Attendance;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\Leave;
use Alimarchal\LaravelChartOfAccounts\Models\LeaveType;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Leave types and leaves with their yearly balances, and the monthly attendance sheet (unpaid absent days and overtime
 * hours) that payroll reads: unpaid days, absent days and unpaid leave alike, come off the salary by the day.
 */
class PayrollAttendanceService
{
    // -- leave types -------------------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateLeaveType(array $input, ?LeaveType $type = null): array
    {
        return Validator::make($input, [
            'code' => ['required', 'string', 'max:30', CompanyRule::unique('accounting_leave_types', 'code')->ignore($type?->id)],
            'name' => ['required', 'string', 'max:120'],
            'is_paid' => ['nullable', 'boolean'],
            'annual_days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();
    }

    public function deleteLeaveType(LeaveType $type): void
    {
        if (Leave::query()->where('leave_type_id', $type->id)->exists()) {
            throw new AccountingException('A leave type with leaves recorded cannot be deleted; deactivate it instead.');
        }

        $type->delete();
    }

    // -- leaves ------------------------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateLeave(array $input): array
    {
        return Validator::make($input, [
            'employee_id' => ['required', 'integer', CompanyRule::exists('accounting_employees', 'id')],
            'leave_type_id' => ['required', 'integer', CompanyRule::exists('accounting_leave_types', 'id')->where('is_active', true)],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'days' => ['nullable', 'numeric', 'gt:0', 'max:366'],
            'notes' => ['nullable', 'string', 'max:200'],
        ])->validate();
    }

    /**
     * Record leave. The days default to the calendar days from the first to the last date (a half day is entered as days = 0.5).
     * A paid leave type with a yearly entitlement refuses what the balance cannot cover.
     *
     * @param  array<string, mixed>  $data  validated
     */
    public function createLeave(array $data): Leave
    {
        $from = Carbon::parse($data['from_date'])->startOfDay();
        $to = Carbon::parse($data['to_date'])->startOfDay();
        $span = (int) round($from->diffInDays($to)) + 1;
        $days = (float) ($data['days'] ?? $span);

        if ($days > $span) {
            throw ValidationException::withMessages(['days' => "That is more days than the dates cover ({$span})."]);
        }

        if (Leave::query()->where('employee_id', $data['employee_id'])->where('status', 'approved')->whereDate('from_date', '<=', $to->toDateString())->whereDate('to_date', '>=', $from->toDateString())->exists()) {
            throw new AccountingException('The employee already has leave in those dates.');
        }

        $type = LeaveType::query()->findOrFail($data['leave_type_id']);

        if ($type->is_paid && (float) $type->annual_days > 0) {
            $year = (int) $from->format('Y');
            $balance = $this->balance((int) $data['employee_id'], $type, $year);

            if ($days > $balance['balance'] + 0.0001) {
                throw new AccountingException("Not enough {$type->name} balance in {$year}: {$balance['balance']} days left, {$days} asked.");
            }
        }

        return DB::transaction(function () use ($data, $from, $to, $days, $type): Leave {
            $leave = Leave::query()->create(['employee_id' => $data['employee_id'], 'leave_type_id' => $type->id, 'from_date' => $from->toDateString(), 'to_date' => $to->toDateString(), 'days' => $days, 'status' => 'approved', 'notes' => $data['notes'] ?? null]);
            AccountingAuditLog::record($leave, 'LEAVE_RECORDED', null, null, ['employee_id' => $leave->employee_id, 'type' => $type->code, 'days' => $days]);

            return $leave;
        });
    }

    public function cancelLeave(Leave $leave): Leave
    {
        if ($leave->status === 'cancelled') {
            return $leave;
        }

        $leave->forceFill(['status' => 'cancelled'])->save();
        AccountingAuditLog::record($leave, 'LEAVE_CANCELLED', null, null, ['employee_id' => $leave->employee_id, 'days' => $leave->days]);

        return $leave->refresh();
    }

    /**
     * Days of a leave that fall between two dates (a leave of several days is spread evenly over its dates).
     */
    private function overlapDays(Leave $leave, CarbonInterface $from, CarbonInterface $to): float
    {
        $start = $leave->from_date->copy()->startOfDay();
        $end = $leave->to_date->copy()->startOfDay();
        $span = (int) round($start->diffInDays($end)) + 1;
        $inside = (int) round(max($start, $from->copy()->startOfDay())->diffInDays(min($end, $to->copy()->startOfDay()), false)) + 1;

        return $inside <= 0 ? 0.0 : (float) $leave->days * min($inside, $span) / $span;
    }

    /**
     * @return array{entitlement: float, taken: float, balance: float}
     */
    public function balance(int $employeeId, LeaveType $type, int $year): array
    {
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->startOfDay();
        $taken = Leave::query()->where('employee_id', $employeeId)->where('leave_type_id', $type->id)->where('status', 'approved')
            ->whereDate('from_date', '<=', $to->toDateString())->whereDate('to_date', '>=', $from->toDateString())->get()
            ->sum(fn (Leave $leave): float => $this->overlapDays($leave, $from, $to));

        return ['entitlement' => (float) $type->annual_days, 'taken' => round($taken, 2), 'balance' => round((float) $type->annual_days - $taken, 2)];
    }

    /**
     * Every active employee against every active leave type for a year.
     *
     * @return list<array<string, mixed>>
     */
    public function balances(int $year, ?int $employeeId = null): array
    {
        $types = LeaveType::query()->where('is_active', true)->orderBy('code')->get();
        $rows = [];

        foreach (Employee::query()->where('is_active', true)->when($employeeId, fn ($query, $id) => $query->whereKey($id))->orderBy('code')->get(['id', 'code', 'name']) as $employee) {
            foreach ($types as $type) {
                $rows[] = ['employee_id' => $employee->id, 'employee_code' => $employee->code, 'employee_name' => $employee->name, 'leave_type_id' => $type->id, 'leave_type' => $type->name, 'is_paid' => $type->is_paid, ...$this->balance($employee->id, $type, $year)];
            }
        }

        return $rows;
    }

    // -- attendance sheet --------------------------------------------------------------------------------------

    /**
     * The sheet of a month: every active employee employed in it, with what is entered and the unpaid leave that counts too.
     *
     * @return list<array<string, mixed>>
     */
    public function sheet(CarbonInterface $month): array
    {
        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $entered = Attendance::query()->whereDate('month', $start->toDateString())->get()->keyBy('employee_id');
        $leave = $this->unpaidLeaveDays($start);
        $rows = [];

        foreach (Employee::query()->where('is_active', true)->whereDate('join_date', '<=', $end->toDateString())->where(fn ($query) => $query->whereNull('leave_date')->orWhereDate('leave_date', '>=', $start->toDateString()))->orderBy('code')->get() as $employee) {
            $row = $entered[$employee->id] ?? null;
            $rows[] = [
                'employee_id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'overtime_eligible' => $employee->overtime_eligible,
                'absent_days' => $row->absent_days ?? '0.00', 'overtime_hours' => $row->overtime_hours ?? '0.00', 'holiday_overtime_hours' => $row->holiday_overtime_hours ?? '0.00', 'notes' => $row->notes ?? null,
                'unpaid_leave_days' => round($leave[$employee->id] ?? 0, 2),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{month: string, rows: list<array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public function validateSheet(array $input): array
    {
        return Validator::make($input, [
            'month' => ['required', 'date'],
            'rows' => ['required', 'array', 'max:5000'],
            'rows.*.employee_id' => ['required', 'integer', CompanyRule::exists('accounting_employees', 'id')],
            'rows.*.absent_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:744'],
            'rows.*.holiday_overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:744'],
            'rows.*.notes' => ['nullable', 'string', 'max:200'],
        ])->validate();
    }

    /**
     * Save the sheet. A row of nothing but zeros removes the entry. Months of a posted or paid payroll are not changed.
     *
     * @param  array{month: string, rows: list<array<string, mixed>>}  $data  validated
     */
    public function saveSheet(array $data): int
    {
        $month = Carbon::parse($data['month'])->startOfMonth();
        $closed = \Alimarchal\LaravelChartOfAccounts\Models\PayrollRun::query()->whereDate('period_month', $month->toDateString())->whereIn('status', ['posted', 'paid'])->exists();

        if ($closed) {
            throw new AccountingException('Payroll of '.$month->format('F Y').' is already posted: void it before changing the attendance.');
        }

        return DB::transaction(function () use ($data, $month): int {
            $saved = 0;

            foreach ($data['rows'] as $row) {
                $values = ['absent_days' => $row['absent_days'] ?? 0, 'overtime_hours' => $row['overtime_hours'] ?? 0, 'holiday_overtime_hours' => $row['holiday_overtime_hours'] ?? 0, 'notes' => $row['notes'] ?? null];
                $query = Attendance::query()->where('employee_id', $row['employee_id'])->whereDate('month', $month->toDateString());

                if ((float) $values['absent_days'] === 0.0 && (float) $values['overtime_hours'] === 0.0 && (float) $values['holiday_overtime_hours'] === 0.0 && $values['notes'] === null) {
                    $query->delete();

                    continue;
                }

                $existing = $query->first();
                $existing ? $existing->update($values) : Attendance::query()->create(['employee_id' => $row['employee_id'], 'month' => $month->toDateString(), ...$values]);
                $saved++;
            }

            return $saved;
        });
    }

    // -- what payroll reads ------------------------------------------------------------------------------------

    /**
     * Unpaid leave days by employee that fall in a month.
     *
     * @return array<int, float>
     */
    private function unpaidLeaveDays(CarbonInterface $month): array
    {
        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $unpaid = LeaveType::query()->where('is_paid', false)->pluck('id');
        $days = [];

        foreach (Leave::query()->where('status', 'approved')->whereIn('leave_type_id', $unpaid)->whereDate('from_date', '<=', $end->toDateString())->whereDate('to_date', '>=', $start->toDateString())->get() as $leave) {
            $days[$leave->employee_id] = ($days[$leave->employee_id] ?? 0) + $this->overlapDays($leave, $start, $end);
        }

        return $days;
    }

    /**
     * What payroll needs of a month: days not paid (absent plus unpaid leave) and overtime hours, by employee.
     *
     * @return array<int, array{unpaid: float, overtime: float, holiday_overtime: float}>
     */
    public function monthFacts(CarbonInterface $month): array
    {
        $start = Carbon::parse($month)->startOfMonth();
        $facts = [];

        foreach (Attendance::query()->whereDate('month', $start->toDateString())->get() as $row) {
            $facts[$row->employee_id] = ['unpaid' => (float) $row->absent_days, 'overtime' => (float) $row->overtime_hours, 'holiday_overtime' => (float) $row->holiday_overtime_hours];
        }

        foreach ($this->unpaidLeaveDays($start) as $employeeId => $days) {
            $facts[$employeeId] ??= ['unpaid' => 0.0, 'overtime' => 0.0, 'holiday_overtime' => 0.0];
            $facts[$employeeId]['unpaid'] += $days;
        }

        return $facts;
    }
}
