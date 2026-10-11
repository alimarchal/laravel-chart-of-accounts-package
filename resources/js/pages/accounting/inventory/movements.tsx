import { Head, Link } from '@inertiajs/react';
import FilterBar from '@/components/accounting/filter-bar';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Props = {
    movements: Array<{ id: number; date: string; type: string; item_id: number; warehouse_id: number; quantity: string; unit_cost: string; value: string; reference: string | null; journal_entry_id: number | null }>;
    items: Array<{ id: number; sku: string; name: string }>;
    warehouses: Array<{ id: number; name: string }>;
    filters: Record<string, string | number | null>;
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const qty = (value: string) => Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 });

export default function InventoryMovements({ movements, items, warehouses, filters }: Props) {
    const { permissions } = useAccounting();
    const itemName = Object.fromEntries(items.map((row) => [row.id, row.sku]));
    const whName = Object.fromEntries(warehouses.map((row) => [row.id, row.name]));

    return (
        <>
            <Head title="Stock movements" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Stock movements" description="The latest 200 movements" />
                    <div className="flex flex-wrap items-center gap-2">
                        {permissions['inventory.move'] && <Button asChild><Link href="/accounting/inventory/movements/create">Move stock</Link></Button>}
                    </div>
                </div>
                <FilterBar
                    path="/accounting/inventory/movements"
                    values={filters}
                    fields={[
                        { name: 'search', label: 'Search', placeholder: 'Reference or notes' },
                        { name: 'item_id', label: 'Item', type: 'select', options: Object.fromEntries(items.map((row) => [String(row.id), row.sku])) },
                        { name: 'warehouse_id', label: 'Warehouse', type: 'select', options: Object.fromEntries(warehouses.map((row) => [String(row.id), row.name])) },
                        { name: 'type', label: 'Type', type: 'select', options: { receipt: 'receipt', issue: 'issue', adjustment: 'adjustment', transfer_in: 'transfer in', transfer_out: 'transfer out' } },
                        { name: 'date_from', label: 'From', type: 'date' },
                        { name: 'date_to', label: 'To', type: 'date' },
                    ]}
                />
                <Card><CardContent className="overflow-x-auto pt-6">
                    <table className="w-full text-sm">
                        <thead><tr className="text-left text-muted-foreground"><th className="py-1">Date</th><th>Type</th><th>Item</th><th>Warehouse</th><th className="text-right">Quantity</th><th className="text-right">Unit cost</th><th className="text-right">Value</th><th>Reference</th><th className="text-right">Entry</th></tr></thead>
                        <tbody>
                            {movements.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="py-1">{row.date}</td><td>{row.type.replace('_', ' ')}</td><td>{itemName[row.item_id]}</td><td>{whName[row.warehouse_id]}</td>
                                    <td className="text-right tabular-nums">{qty(row.quantity)}</td><td className="text-right tabular-nums">{money(row.unit_cost)}</td><td className="text-right tabular-nums">{money(row.value)}</td><td>{row.reference}</td>
                                    <td className="text-right">{row.journal_entry_id ? <Link className="hover:underline" href={`/accounting/journal-entries/${row.journal_entry_id}`}>#{row.journal_entry_id}</Link> : '—'}</td>
                                </tr>
                            ))}
                            {movements.length === 0 && <tr><td colSpan={9} className="py-4 text-center text-muted-foreground">No movements.</td></tr>}
                        </tbody>
                    </table>
                </CardContent></Card>
            </div>
        </>
    );
}

InventoryMovements.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Inventory', href: '/accounting/inventory' }, { title: 'Movements', href: '/accounting/inventory/movements' }] };
