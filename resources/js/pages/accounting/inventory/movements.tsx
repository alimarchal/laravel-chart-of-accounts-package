import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Props = {
    movements: Array<{ id: number; date: string; type: string; item_id: number; warehouse_id: number; quantity: string; unit_cost: string; value: string; reference: string | null; journal_entry_id: number | null }>;
    items: Array<{ id: number; sku: string; name: string }>;
    warehouses: Array<{ id: number; name: string }>;
    filters: { item_id: string | number; warehouse_id: string | number; type: string };
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const qty = (value: string) => Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 });
const selectClass = 'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function InventoryMovements({ movements, items, warehouses, filters }: Props) {
    const { permissions } = useAccounting();
    const [state, setState] = useState({ item_id: String(filters.item_id ?? ''), warehouse_id: String(filters.warehouse_id ?? ''), type: filters.type ?? '' });
    const itemName = Object.fromEntries(items.map((row) => [row.id, row.sku]));
    const whName = Object.fromEntries(warehouses.map((row) => [row.id, row.name]));
    const apply = () => router.get('/accounting/inventory/movements', Object.fromEntries(Object.entries(state).filter(([, value]) => value)), { preserveState: true });

    return (
        <>
            <Head title="Stock movements" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Stock movements" description="The latest 200 movements" />
                    <div className="flex flex-wrap items-center gap-2">
                        <select className={selectClass} value={state.item_id} onChange={(event) => setState({ ...state, item_id: event.target.value })}><option value="">All items</option>{items.map((row) => (<option key={row.id} value={row.id}>{row.sku}</option>))}</select>
                        <select className={selectClass} value={state.warehouse_id} onChange={(event) => setState({ ...state, warehouse_id: event.target.value })}><option value="">All warehouses</option>{warehouses.map((row) => (<option key={row.id} value={row.id}>{row.name}</option>))}</select>
                        <select className={selectClass} value={state.type} onChange={(event) => setState({ ...state, type: event.target.value })}><option value="">All types</option>{['receipt', 'issue', 'adjustment', 'transfer_in', 'transfer_out'].map((type) => (<option key={type} value={type}>{type.replace('_', ' ')}</option>))}</select>
                        <Button variant="outline" onClick={apply}>Filter</Button>
                        {permissions['inventory.move'] && <Button asChild><Link href="/accounting/inventory/movements/create">Move stock</Link></Button>}
                    </div>
                </div>
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
