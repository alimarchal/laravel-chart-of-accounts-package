<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\InventoryItem;
use Alimarchal\LaravelChartOfAccounts\Models\StockMovement;
use Alimarchal\LaravelChartOfAccounts\Models\Warehouse;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Services\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Items, warehouses, stock movements and stock valuation for the React and Blade screens and the API.
 */
class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $filters = $request->validate(['as_of' => ['nullable', 'date'], 'warehouse_id' => ['nullable', 'integer']]);
        $valuation = $this->inventory->valuation($filters['as_of'] ?? null, isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null);

        return $request->expectsJson()
            ? response()->json(['data' => $valuation])
            : $this->render('index', ['valuation' => $valuation, 'warehouses' => $this->warehouseOptions(), 'filters' => ['as_of' => $valuation['as_of'], 'warehouse_id' => $filters['warehouse_id'] ?? '']]);
    }

    public function export(Request $request, string $format, AccountingReportExporter $exporter): HttpResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        $filters = $request->validate(['as_of' => ['nullable', 'date'], 'warehouse_id' => ['nullable', 'integer']]);
        $valuation = $this->inventory->valuation($filters['as_of'] ?? null, isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null);
        $rows = collect($valuation['rows'])->map(fn (array $row): array => collect($row)->only(['sku', 'name', 'unit', 'quantity', 'average_cost', 'value'])->all());

        return $exporter->download($rows, 'stock-valuation', $format, ['title' => 'Stock valuation', 'filters' => ['As of' => $valuation['as_of']]]);
    }

    // -- items -------------------------------------------------------------------------------------------------

    public function itemCreate(): Response|View
    {
        return $this->render('item-form', $this->itemFormProps(null));
    }

    public function itemEdit(InventoryItem $item): Response|View
    {
        return $this->render('item-form', $this->itemFormProps($item));
    }

    public function itemShow(Request $request, InventoryItem $item): Response|View|JsonResponse
    {
        $card = $this->inventory->card($item);
        $props = ['item' => $this->presentItem($item), 'card' => $card, 'warehouses' => $this->warehouseOptions(), 'locked' => StockMovement::query()->where('item_id', $item->id)->exists()];

        return $request->expectsJson() ? response()->json(['data' => ['item' => $props['item'], 'card' => $card]]) : $this->render('item-show', $props);
    }

    public function itemStore(Request $request): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request) {
            $item = $this->inventory->createItem($this->inventory->validateItem($request->all()));

            return $request->expectsJson()
                ? response()->json(['data' => $this->presentItem($item)], 201)
                : to_route($this->routeName('inventory.items.show'), $item)->with('success', 'Item created.');
        });
    }

    public function itemUpdate(Request $request, InventoryItem $item): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $item) {
            $item = $this->inventory->updateItem($item, $this->inventory->validateItem($request->all(), $item));

            return $request->expectsJson()
                ? response()->json(['data' => $this->presentItem($item)])
                : to_route($this->routeName('inventory.items.show'), $item)->with('success', 'Item updated.');
        });
    }

    public function itemDestroy(Request $request, InventoryItem $item): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $item) {
            $this->inventory->deleteItem($item);

            return $request->expectsJson() ? response()->json(null, 204) : to_route($this->routeName('inventory.index'))->with('success', 'Item deleted.');
        });
    }

    // -- warehouses --------------------------------------------------------------------------------------------

    public function warehouses(Request $request): Response|View|JsonResponse
    {
        $warehouses = Warehouse::query()->orderBy('code')->get(['id', 'code', 'name', 'address', 'is_active']);

        return $request->expectsJson() ? response()->json(['data' => $warehouses]) : $this->render('warehouses', ['warehouses' => $warehouses]);
    }

    public function warehouseStore(Request $request): RedirectResponse|JsonResponse
    {
        $warehouse = Warehouse::query()->create($this->inventory->validateWarehouse($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $warehouse], 201) : back()->with('success', 'Warehouse added.');
    }

    public function warehouseUpdate(Request $request, Warehouse $warehouse): RedirectResponse|JsonResponse
    {
        $warehouse->update($this->inventory->validateWarehouse($request->all(), $warehouse));

        return $request->expectsJson() ? response()->json(['data' => $warehouse->refresh()]) : back()->with('success', 'Warehouse updated.');
    }

    public function warehouseDestroy(Request $request, Warehouse $warehouse): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $warehouse) {
            $this->inventory->deleteWarehouse($warehouse);

            return $request->expectsJson() ? response()->json(null, 204) : back()->with('success', 'Warehouse deleted.');
        });
    }

    // -- movements ---------------------------------------------------------------------------------------------

    public function movements(Request $request): Response|View|JsonResponse
    {
        $filters = $request->validate(['item_id' => ['nullable', 'integer'], 'warehouse_id' => ['nullable', 'integer'], 'type' => ['nullable', 'in:'.implode(',', StockMovement::TYPES)]]);
        $rows = StockMovement::query()->when($filters['item_id'] ?? null, fn ($query, $id) => $query->where('item_id', $id))->when($filters['warehouse_id'] ?? null, fn ($query, $id) => $query->where('warehouse_id', $id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))->orderByDesc('movement_date')->orderByDesc('id')->limit(200)->get()
            ->map(fn (StockMovement $move): array => ['id' => $move->id, 'date' => $move->movement_date->toDateString(), 'type' => $move->type, 'item_id' => $move->item_id, 'warehouse_id' => $move->warehouse_id, 'quantity' => $move->quantity, 'unit_cost' => $move->unit_cost, 'value' => $move->value, 'reference' => $move->reference, 'journal_entry_id' => $move->journal_entry_id])->values();

        return $request->expectsJson()
            ? response()->json(['data' => $rows])
            : $this->render('movements', ['movements' => $rows, 'items' => $this->itemOptions(), 'warehouses' => $this->warehouseOptions(), 'filters' => ['item_id' => $filters['item_id'] ?? '', 'warehouse_id' => $filters['warehouse_id'] ?? '', 'type' => $filters['type'] ?? '']]);
    }

    public function movementCreate(Request $request): Response|View
    {
        return $this->render('movement-form', ['items' => $this->itemOptions(), 'warehouses' => $this->warehouseOptions(), 'accounts' => $this->accountOptions(), 'today' => now()->toDateString(), 'preset' => ['item_id' => $request->query('item_id', ''), 'type' => $request->query('type', 'receipt')]]);
    }

    public function movementStore(Request $request): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request) {
            $moves = $this->inventory->move($request->all());

            return $request->expectsJson()
                ? response()->json(['data' => array_map(fn (StockMovement $move): array => ['id' => $move->id, 'type' => $move->type, 'quantity' => $move->quantity, 'unit_cost' => $move->unit_cost, 'value' => $move->value, 'journal_entry_id' => $move->journal_entry_id], $moves)], 201)
                : to_route($this->routeName('inventory.items.show'), $moves[0]->item_id)->with('success', 'Stock movement recorded.');
        });
    }

    /**
     * @param  \Closure(): (RedirectResponse|JsonResponse)  $action
     */
    private function guard(Request $request, \Closure $action): RedirectResponse|JsonResponse
    {
        try {
            return $action();
        } catch (AccountingException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $page, array $props): Response|View
    {
        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::inventory.'.$page, $props)
            : Inertia::render('accounting/inventory/'.$page, $props);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function warehouseOptions(): array
    {
        return Warehouse::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->map(fn (Warehouse $warehouse): array => ['id' => $warehouse->id, 'code' => $warehouse->code, 'name' => $warehouse->name])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemOptions(): array
    {
        return InventoryItem::query()->orderBy('sku')->get(['id', 'sku', 'name', 'unit', 'is_active'])->map(fn (InventoryItem $item): array => ['id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'unit' => $item->unit, 'is_active' => $item->is_active])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountOptions(): array
    {
        return ChartOfAccount::query()->with('accountType:id,code')->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_type_id'])
            ->map(fn (ChartOfAccount $account): array => ['id' => $account->id, 'account_code' => $account->account_code, 'account_name' => $account->account_name, 'type' => $account->accountType->code])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function itemFormProps(?InventoryItem $item): array
    {
        return ['item' => $item ? $this->presentItem($item) : null, 'accounts' => $this->accountOptions(), 'locked' => $item !== null && StockMovement::query()->where('item_id', $item->id)->exists()];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(InventoryItem $item): array
    {
        return [
            'id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'unit' => $item->unit, 'category' => $item->category, 'reorder_level' => $item->reorder_level,
            'inventory_account_id' => $item->inventory_account_id, 'cogs_account_id' => $item->cogs_account_id, 'on_hand_quantity' => $item->on_hand_quantity, 'on_hand_value' => $item->on_hand_value, 'is_active' => $item->is_active,
        ];
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
