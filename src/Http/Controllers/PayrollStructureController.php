<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns\RespondsForPayroll;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGrade;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGradeComponent;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollStructureService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;

/**
 * Salary grades, giving components to groups of employees, and salary revisions (one at a time or for a group).
 */
class PayrollStructureController extends Controller
{
    use RespondsForPayroll;

    public function __construct(private readonly PayrollStructureService $structure) {}

    // -- grades ------------------------------------------------------------------------------------------------

    public function grades(Request $request): Response|View|JsonResponse
    {
        $grades = SalaryGrade::query()->orderBy('code')->get()->map(fn (SalaryGrade $grade): array => $this->presentGrade($grade))->values();

        if ($request->expectsJson()) {
            return response()->json(['data' => $grades]);
        }

        return $this->render('grades', [
            'grades' => $grades,
            'editing' => $request->integer('edit') ? $grades->firstWhere('id', $request->integer('edit')) : null,
            'components' => PayComponent::query()->where('is_active', true)->orderBy('kind')->orderBy('code')->get(['id', 'code', 'name', 'kind', 'method', 'value', 'rate', 'unit']),
        ]);
    }

    public function gradeShow(SalaryGrade $grade): JsonResponse
    {
        return response()->json(['data' => $this->presentGrade($grade)]);
    }

    public function gradeStore(Request $request): RedirectResponse|JsonResponse
    {
        $grade = $this->structure->saveGrade($this->structure->validateGrade($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $this->presentGrade($grade)], 201) : to_route($this->routeName('payroll.grades.index'))->with('success', 'Grade saved.');
    }

    public function gradeUpdate(Request $request, SalaryGrade $grade): RedirectResponse|JsonResponse
    {
        $grade = $this->structure->saveGrade($this->structure->validateGrade($request->all(), $grade), $grade);

        return $request->expectsJson() ? response()->json(['data' => $this->presentGrade($grade)]) : to_route($this->routeName('payroll.grades.index'))->with('success', 'Grade saved.');
    }

    public function gradeDestroy(Request $request, SalaryGrade $grade): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $grade) {
            $this->structure->deleteGrade($grade);

            return $request->expectsJson() ? response()->json(null, 204) : to_route($this->routeName('payroll.grades.index'))->with('success', 'Grade deleted.');
        });
    }

    public function gradeAssign(Request $request, SalaryGrade $grade): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $grade) {
            $result = $this->structure->assignGrade($grade, $request->all());

            return $request->expectsJson() ? response()->json(['message' => $result['changed'].' employees moved to the grade.', 'data' => $result]) : back()->with('success', $result['changed'].' employees moved to '.$grade->code.'.');
        });
    }

    // -- groups ------------------------------------------------------------------------------------------------

    public function bulk(Request $request): Response|View
    {
        return $this->render('bulk', [
            'employees' => Employee::query()->orderBy('code')->get(['id', 'code', 'name', 'salary_grade_id', 'base_salary', 'is_active'])->map(fn (Employee $employee): array => [
                'id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'salary_grade_id' => $employee->salary_grade_id, 'base_salary' => $employee->base_salary, 'is_active' => $employee->is_active,
            ])->values(),
            'components' => PayComponent::query()->where('is_active', true)->orderBy('kind')->orderBy('code')->get(['id', 'code', 'name', 'kind', 'method', 'value']),
            'grades' => SalaryGrade::query()->orderBy('code')->get(['id', 'code', 'name', 'base_salary']),
            'preview' => session('revision_preview'),
            'today' => now()->toDateString(),
        ]);
    }

    public function bulkComponent(Request $request): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request) {
            $result = $this->structure->bulkComponent($request->all());

            return $request->expectsJson() ? response()->json(['message' => $result['changed'].' employees changed.', 'data' => $result]) : back()->with('success', $result['changed'].' employees changed.');
        });
    }

    // -- salary revisions --------------------------------------------------------------------------------------

    public function employeeRevisions(Employee $employee): JsonResponse
    {
        return response()->json(['data' => $this->structure->history($employee)]);
    }

    public function employeeRevise(Request $request, Employee $employee): RedirectResponse|JsonResponse
    {
        $data = $this->structure->validateRevision($request->all());

        return $this->guard($request, function () use ($request, $employee, $data) {
            $revision = $this->structure->revise($employee, $data['new_salary'], $data['effective_from'], $data['reason'] ?? null);

            return $request->expectsJson()
                ? response()->json(['message' => $revision ? 'Salary revised.' : 'The salary is already that amount.', 'data' => $this->structure->history($employee->refresh())], $revision ? 201 : 200)
                : back()->with('success', $revision ? 'Salary revised.' : 'The salary is already that amount.');
        });
    }

    public function revisionPreview(Request $request): RedirectResponse|JsonResponse
    {
        $rows = $this->structure->previewBulkRevision($this->structure->validateBulkRevision($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $rows]) : back()->withInput()->with('revision_preview', $rows);
    }

    public function revisionApply(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->structure->validateBulkRevision($request->all());

        return $this->guard($request, function () use ($request, $data) {
            $result = $this->structure->applyBulkRevision($data);

            return $request->expectsJson()
                ? response()->json(['message' => $result['changed'].' salaries revised.', 'data' => $result['rows']])
                : to_route($this->routeName('payroll.bulk'))->with('success', $result['changed'].' salaries revised. Use Arrears to pay the difference for past months.');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function presentGrade(SalaryGrade $grade): array
    {
        return [
            'id' => $grade->id, 'code' => $grade->code, 'name' => $grade->name, 'base_salary' => $grade->base_salary, 'is_active' => $grade->is_active,
            'employees' => Employee::query()->where('salary_grade_id', $grade->id)->count(),
            'components' => SalaryGradeComponent::query()->where('salary_grade_id', $grade->id)->get(['pay_component_id', 'value'])->map(fn (SalaryGradeComponent $link): array => ['pay_component_id' => $link->pay_component_id, 'value' => $link->value])->values()->all(),
        ];
    }
}
