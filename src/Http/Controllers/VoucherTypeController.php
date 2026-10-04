<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Services\VoucherTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Voucher types (React): one screen to list the number series, see the next number of each, and add or edit types.
 */
class VoucherTypeController extends Controller
{
    public function __construct(private readonly VoucherTypeService $types) {}

    public function index(): Response
    {
        return Inertia::render('accounting/voucher-types/index', [
            'voucherTypes' => VoucherType::query()->orderBy('code')->get()
                ->map(fn (VoucherType $type) => $this->types->present($type))->values(),
            'resets' => VoucherType::resets(),
            'defaultFormat' => VoucherType::DEFAULT_FORMAT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->types->create($this->types->validate($this->input($request)));

        return back()->with('success', "Voucher type {$type->code} created.");
    }

    public function update(Request $request, VoucherType $record): RedirectResponse
    {
        try {
            $type = $this->types->update($record, $this->types->validate($this->input($request), $record));
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Voucher type {$type->code} updated.");
    }

    public function destroy(VoucherType $record): RedirectResponse
    {
        try {
            $this->types->delete($record);
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Voucher type {$record->code} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        return [...$request->only(['code', 'name', 'prefix', 'format', 'reset', 'description']), 'is_active' => $request->boolean('is_active', true)];
    }
}
