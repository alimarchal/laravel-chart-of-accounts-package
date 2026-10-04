<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Alimarchal\LaravelChartOfAccounts\Services\ControlAccountService;
use Alimarchal\LaravelChartOfAccounts\Support\ControlAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Control accounts: overview with balances and manual postings, the recommended setup, and marking accounts.
 */
class ControlAccountApiController extends Controller
{
    public function index(ControlAccountService $controls): JsonResponse
    {
        return response()->json(['data' => $controls->overview(), 'types' => ControlAccounts::types()]);
    }

    public function recommended(ChartOfAccountService $accounts): JsonResponse
    {
        return $this->attempt(fn () => response()->json(['data' => $accounts->applyRecommendedControlAccounts()]));
    }

    public function setType(Request $request, ChartOfAccount $chartOfAccount, ChartOfAccountService $accounts): JsonResponse
    {
        $data = $request->validate(['control_type' => ['present', 'nullable', 'string', Rule::in(array_keys(ControlAccounts::types()))]]);

        return $this->attempt(fn () => response()->json(['data' => $accounts->setControlType($chartOfAccount, $data['control_type'])->only(['id', 'account_code', 'account_name', 'control_type'])]));
    }

    public function manualPostings(ChartOfAccount $chartOfAccount, ControlAccountService $controls): JsonResponse
    {
        return response()->json(['data' => $controls->manualPostings($chartOfAccount)]);
    }

    private function attempt(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (AccountingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
