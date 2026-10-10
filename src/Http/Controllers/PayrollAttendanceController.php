<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns\RespondsForPayroll;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\Leave;
use Alimarchal\LaravelChartOfAccounts\Models\LeaveType;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollAttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Inertia\Response;

/**
 * Leave types, leaves with their balances, and the monthly attendance sheet.
 */
class PayrollAttendanceController extends Controller
{
    use RespondsForPayroll;

    public function __construct(private readonly PayrollAttendanceService $attendance) {}

    // -- attendance sheet --------------------------------------------------------------------------------------

    public function sheet(Request $request): Response|View|JsonResponse
    {
        $month = Carbon::parse($request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m'))->startOfMonth();
        $rows = $this->attendance->sheet($month);

        return $request->expectsJson()
            ? response()->json(['data' => $rows, 'month' => $month->format('Y-m')])
            : $this->render('attendance', ['rows' => $rows, 'month' => $month->format('Y-m')]);
    }

    public function sheetStore(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->attendance->validateSheet($request->all());

        return $this->guard($request, function () use ($request, $data) {
            $saved = $this->attendance->saveSheet($data);
            $month = Carbon::parse($data['month'])->format('Y-m');

            return $request->expectsJson()
                ? response()->json(['message' => $saved.' attendance rows saved.', 'data' => $this->attendance->sheet(Carbon::parse($data['month']))])
                : to_route($this->routeName('payroll.attendance.index'), ['month' => $month])->with('success', 'Attendance saved.');
        });
    }

    // -- leave types -------------------------------------------------------------------------------------------

    public function leaveTypes(Request $request): JsonResponse
    {
        return response()->json(['data' => LeaveType::query()->orderBy('code')->get()->map(fn (LeaveType $type): array => $this->presentType($type))->values()]);
    }

    public function leaveTypeStore(Request $request): RedirectResponse|JsonResponse
    {
        $type = LeaveType::query()->create($this->attendance->validateLeaveType($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $this->presentType($type)], 201) : back()->with('success', 'Leave type added.');
    }

    public function leaveTypeUpdate(Request $request, LeaveType $leaveType): RedirectResponse|JsonResponse
    {
        $leaveType->update($this->attendance->validateLeaveType($request->all(), $leaveType));

        return $request->expectsJson() ? response()->json(['data' => $this->presentType($leaveType->refresh())]) : back()->with('success', 'Leave type updated.');
    }

    public function leaveTypeDestroy(Request $request, LeaveType $leaveType): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $leaveType) {
            $this->attendance->deleteLeaveType($leaveType);

            return $request->expectsJson() ? response()->json(null, 204) : back()->with('success', 'Leave type deleted.');
        });
    }

    // -- leaves ------------------------------------------------------------------------------------------------

    public function leaves(Request $request): Response|View|JsonResponse
    {
        $year = (int) ($request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? now()->format('Y'));
        $names = Employee::query()->get(['id', 'code', 'name'])->keyBy('id');
        $types = LeaveType::query()->orderBy('code')->get()->keyBy('id');
        $leaves = Leave::query()->orderByDesc('from_date')->orderByDesc('id')->limit(300)->get()->map(fn (Leave $leave): array => [
            'id' => $leave->id, 'employee_id' => $leave->employee_id, 'employee_code' => $names[$leave->employee_id]->code ?? '', 'employee_name' => $names[$leave->employee_id]->name ?? '',
            'leave_type_id' => $leave->leave_type_id, 'leave_type' => $types[$leave->leave_type_id]->name ?? '', 'is_paid' => $types[$leave->leave_type_id]->is_paid ?? true,
            'from_date' => $leave->from_date->toDateString(), 'to_date' => $leave->to_date->toDateString(), 'days' => $leave->days, 'status' => $leave->status, 'notes' => $leave->notes,
        ])->values();

        if ($request->expectsJson()) {
            return response()->json(['data' => $leaves]);
        }

        return $this->render('leaves', [
            'leaves' => $leaves, 'types' => $types->values()->map(fn (LeaveType $type): array => $this->presentType($type))->values(),
            'employees' => $names->values(), 'balances' => $this->attendance->balances($year), 'year' => $year, 'today' => now()->toDateString(),
        ]);
    }

    public function balances(Request $request): JsonResponse
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'employee_id' => ['nullable', 'integer']]);

        return response()->json(['data' => $this->attendance->balances((int) ($data['year'] ?? now()->format('Y')), isset($data['employee_id']) ? (int) $data['employee_id'] : null)]);
    }

    public function leaveStore(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->attendance->validateLeave($request->all());

        return $this->guard($request, function () use ($request, $data) {
            $leave = $this->attendance->createLeave($data);

            return $request->expectsJson() ? response()->json(['data' => ['id' => $leave->id, 'days' => $leave->days, 'status' => $leave->status]], 201) : back()->with('success', 'Leave recorded.');
        });
    }

    public function leaveCancel(Request $request, Leave $leave): RedirectResponse|JsonResponse
    {
        $leave = $this->attendance->cancelLeave($leave);

        return $request->expectsJson() ? response()->json(['data' => ['id' => $leave->id, 'status' => $leave->status]]) : back()->with('success', 'Leave cancelled.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentType(LeaveType $type): array
    {
        return ['id' => $type->id, 'code' => $type->code, 'name' => $type->name, 'is_paid' => $type->is_paid, 'annual_days' => $type->annual_days, 'is_active' => $type->is_active];
    }
}
