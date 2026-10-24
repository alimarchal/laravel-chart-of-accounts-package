<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Services\OpeningBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OpeningBalanceApiController extends Controller
{
    public function __construct(private readonly OpeningBalanceService $service) {}

    public function show(): JsonResponse
    {
        $entry = $this->service->existing();

        return response()->json(['data' => $entry === null ? null : ['id' => $entry->id, 'voucher_number' => $entry->voucher_number, 'entry_date' => $entry->entry_date->toDateString()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:255'],
            'balances' => ['required', 'array', 'min:1', 'max:2000'],
            'balances.*.chart_of_account_id' => ['required', 'integer', 'exists:accounting_chart_of_accounts,id'],
            'balances.*.debit' => ['nullable', 'numeric', 'min:0'],
            'balances.*.credit' => ['nullable', 'numeric', 'min:0'],
            'balances.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->service->post($data['date'], $data['balances'], $data['description'] ?? null);

        return response()->json(['message' => 'Opening balances posted.', 'data' => ['id' => $entry->id, 'voucher_number' => $entry->voucher_number]], 201);
    }
}
