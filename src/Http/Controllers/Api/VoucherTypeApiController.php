<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Services\VoucherNumberService;
use Alimarchal\LaravelChartOfAccounts\Services\VoucherTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Voucher types and their number series. Every response carries the next number the series will issue.
 */
class VoucherTypeApiController extends Controller
{
    public function __construct(private readonly VoucherTypeService $types) {}

    public function index(Request $request): JsonResponse
    {
        $types = VoucherType::query()
            ->when($request->has('filter.is_active'), fn ($query) => $query->where('is_active', $request->boolean('filter.is_active')))
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $types->map(fn (VoucherType $type) => $this->types->present($type))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $type = $this->types->create($this->types->validate($request->all()));

        return response()->json(['data' => $this->types->present($type)], 201);
    }

    public function show(VoucherType $record): JsonResponse
    {
        return response()->json(['data' => $this->types->present($record)]);
    }

    public function update(Request $request, VoucherType $record): JsonResponse
    {
        $data = $this->types->validate([...$record->only(['code', 'name', 'prefix', 'format', 'reset', 'description']), ...$request->all()], $record);

        try {
            $type = $this->types->update($record, $data);
        } catch (AccountingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->types->present($type)]);
    }

    public function destroy(VoucherType $record): JsonResponse
    {
        try {
            $this->types->delete($record);
        } catch (AccountingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(null, 204);
    }

    /**
     * The number a posting on a date would get, without reserving it: GET /voucher-types/{id}/next-number?date=.
     */
    public function nextNumber(Request $request, VoucherType $record, VoucherNumberService $numbers): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date']]);

        return response()->json(['data' => [
            'voucher_type' => $record->code,
            'date' => $data['date'] ?? now()->toDateString(),
            'next_number' => $numbers->preview($record, $data['date'] ?? null),
        ]]);
    }
}
