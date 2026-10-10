<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns\RespondsForPayroll;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\Settlement;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollSettlementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;

/**
 * Final settlements of employees who leave: work out, save, post, pay, void.
 */
class PayrollSettlementController extends Controller
{
    use RespondsForPayroll;

    public function __construct(private readonly PayrollSettlementService $settlements) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $rows = $this->settlements->present();

        if ($request->expectsJson()) {
            return response()->json(['data' => $rows]);
        }

        return $this->render('settlements', [
            'settlements' => $rows, 'preview' => session('settlement_preview'), 'today' => now()->toDateString(),
            'employees' => Employee::query()->orderBy('code')->get(['id', 'code', 'name']),
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ]);
    }

    public function show(Settlement $settlement): JsonResponse
    {
        return response()->json(['data' => $this->settlements->present(collect([$settlement]))[0]]);
    }

    public function preview(Request $request): RedirectResponse|JsonResponse
    {
        $work = $this->settlements->calculate($this->settlements->validate($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $work]) : back()->withInput()->with('settlement_preview', $work);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->settlements->validate($request->all());

        return $this->guard($request, function () use ($request, $data) {
            $settlement = $this->settlements->create($data);

            return $request->expectsJson() ? response()->json(['data' => $this->settlements->present(collect([$settlement]))[0]], 201) : back()->with('success', 'Settlement saved as a draft.');
        });
    }

    public function destroy(Request $request, Settlement $settlement): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $settlement) {
            $this->settlements->delete($settlement);

            return $request->expectsJson() ? response()->json(null, 204) : back()->with('success', 'Settlement deleted.');
        });
    }

    public function post(Request $request, Settlement $settlement): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['payable_account_id' => ['nullable', 'integer'], 'date' => ['nullable', 'date']]);

        return $this->guard($request, fn () => $this->answer($request, $this->settlements->post($settlement, isset($data['payable_account_id']) ? (int) $data['payable_account_id'] : null, $data['date'] ?? null), 'Settlement posted.'));
    }

    public function pay(Request $request, Settlement $settlement): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['account_id' => ['required', 'integer'], 'date' => ['nullable', 'date']]);

        return $this->guard($request, fn () => $this->answer($request, $this->settlements->pay($settlement, (int) $data['account_id'], $data['date'] ?? null), 'Settlement paid.'));
    }

    public function void(Request $request, Settlement $settlement): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->settlements->void($settlement), 'Settlement voided.'));
    }

    private function answer(Request $request, Settlement $settlement, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $this->settlements->present(collect([$settlement]))[0]]) : back()->with('success', $message);
    }
}
