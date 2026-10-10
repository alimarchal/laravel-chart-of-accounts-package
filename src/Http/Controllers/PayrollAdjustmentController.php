<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns\RespondsForPayroll;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollAdjustment;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollAdjustmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;

/**
 * Bonuses and other one-off pay of a month, one employee or many at once.
 */
class PayrollAdjustmentController extends Controller
{
    use RespondsForPayroll;

    public function __construct(private readonly PayrollAdjustmentService $adjustments) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'status' => ['nullable', 'in:open,included,cancelled']]);
        $month = $data['month'] ?? now()->format('Y-m');
        $rows = $this->adjustments->present($month, $data['status'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['data' => $rows, 'month' => $month]);
        }

        return $this->render('adjustments', [
            'adjustments' => $rows, 'month' => $month, 'status' => $data['status'] ?? '',
            'employees' => Employee::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'components' => PayComponent::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'kind']),
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->adjustments->validate($request->all());

        return $this->guard($request, function () use ($request, $data) {
            $row = $this->adjustments->create($data);

            return $request->expectsJson() ? response()->json(['data' => $this->adjustments->present($row->month->format('Y-m'))[0] ?? null, 'id' => $row->id], 201) : back()->with('success', 'Added to '.$row->month->format('F Y').'.');
        });
    }

    public function bulk(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->adjustments->validateBulk($request->all());

        return $this->guard($request, function () use ($request, $data) {
            $result = $this->adjustments->createBulk($data);
            $message = "{$result['created']} added, {$result['total']} in all".($result['skipped'] === [] ? '.' : '; left out: '.implode(', ', $result['skipped']).'.');

            return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $result], 201) : back()->with('success', $message);
        });
    }

    public function cancel(Request $request, PayrollAdjustment $adjustment): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $adjustment) {
            $this->adjustments->cancel($adjustment);

            return $request->expectsJson() ? response()->json(['message' => 'Cancelled.']) : back()->with('success', 'Cancelled.');
        });
    }
}
