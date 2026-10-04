<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Services\VoucherTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Voucher types (Blade): the number series with their next numbers, an add form, and an edit form (?edit={id}).
 */
class VoucherTypeBladeController extends Controller
{
    public function __construct(private readonly VoucherTypeService $types) {}

    public function index(Request $request): View
    {
        $voucherTypes = VoucherType::query()->orderBy('code')->get();

        return view('accounting::voucher-types.index', [
            'voucherTypes' => $voucherTypes->map(fn (VoucherType $type) => $this->types->present($type)),
            'editing' => $request->filled('edit') ? $voucherTypes->firstWhere('id', (int) $request->input('edit')) : null,
            'resets' => VoucherType::resets(),
            'defaultFormat' => VoucherType::DEFAULT_FORMAT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->types->create($this->types->validate($this->input($request)));

        return $this->indexRoute()->with('success', "Voucher type {$type->code} created.");
    }

    public function update(Request $request, VoucherType $record): RedirectResponse
    {
        try {
            $type = $this->types->update($record, $this->types->validate($this->input($request), $record));
        } catch (AccountingException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return $this->indexRoute()->with('success', "Voucher type {$type->code} updated.");
    }

    public function destroy(VoucherType $record): RedirectResponse
    {
        try {
            $this->types->delete($record);
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return $this->indexRoute()->with('success', "Voucher type {$record->code} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        return [...$request->only(['code', 'name', 'prefix', 'format', 'reset', 'description']), 'is_active' => $request->boolean('is_active')];
    }

    private function indexRoute(): RedirectResponse
    {
        return to_route(config('accounting.route_name_prefix', 'accounting').'.voucher-types.index');
    }
}
