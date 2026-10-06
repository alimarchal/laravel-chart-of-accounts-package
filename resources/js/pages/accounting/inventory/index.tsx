import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAccounting } from '@/lib/accounting';

type Row = {
    item_id: number; sku: string; name: string; unit: string; quantity: string; average_cost: string; value: string; reorder_level: string; low: boolean;
    warehouses: Array<{ warehouse_id: number; name: string; quantity: string }>;
};
type Props = {
    valuation: { as_of: string; rows: Row[]; totals: { value: string }; reconcile: { ledger: string; stock: string; difference: string } };
    warehouses: Array<{ id: number; code: string; name: string }>;
    filters: { as_of: string; warehouse_id: string | number };
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const qty = (value: string) => Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 });
const selectClass = 'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function InventoryIndex({ valuation, warehouses, filters }: Props) {
    const { permissions, flash } = useAccounting();
    const [asOf, setAsOf] = useState(filters.as_of);
    const [warehouse, setWarehouse] = useState(String(filters.warehouse_id ?? ''));
    const apply = () => router.get('/accounting/inventory', { as_of: asOf, warehouse_id: warehouse || undefined }, { preserveState: true });

    return (
        <>
            <Head title="Inventory" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Inventory" description="Stock on hand and its value at moving average cost" />
                    <div className="flex flex-wrap items-center gap-2">
                        <Input type="date" value={asOf} onChange={(event) => setAsOf(event.target.value)} className="w-40" />
                        <select className={selectClass} value={warehouse} onChange={(event) => setWarehouse(event.target.value)}>
                            <option value="">All warehouses</option>
                            {warehouses.map((row) => (<option key={row.id} value={row.id}>{row.name}</option>))}
                        </select>
                        <Button variant="outline" onClick={apply}>Update</Button>
                        <Button asChild variant="outline"><a href={`/accounting/inventory/export/xlsx?as_of=${valuation.as_of}`}>Export</a></Button>
                        <Button asChild variant="outline"><Link href="/accounting/inventory/movements">Movements</Link></Button>
                        <Button asChild variant="outline"><Link href="/accounting/inventory/warehouses">Warehouses</Link></Button>
                        {permissions['inventory.move'] && <Button asChild variant="outline"><Link href="/accounting/inventory/movements/create">Move stock</Link></Button>}
                        {permissions['inventory.manage'] && <Button asChild><Link href="/accounting/inventory/items/create">New item</Link></Button>}
                    </div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                <Card>
                    <CardHeader>
                        <CardTitle>Stock value {money(valuation.totals.value)}</CardTitle>
                        <CardDescription>
                            Ledger vs stock difference <span className={Number(valuation.reconcile.difference) !== 0 ? 'font-medium text-destructive' : ''}>{money(valuation.reconcile.difference)}</span> (ledger {money(valuation.reconcile.ledger)}, stock {money(valuation.reconcile.stock)})
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="text-left text-muted-foreground"><th className="py-2">SKU</th><th>Item</th><th className="text-right">Quantity</th><th className="text-right">Average cost</th><th className="text-right">Value</th><th className="pl-6">Where</th></tr></thead>
                            <tbody>
                                {valuation.rows.map((row) => (
                                    <tr key={row.item_id} className="border-t">
                                        <td className="py-2"><Link href={`/accounting/inventory/items/${row.item_id}`} className="font-medium hover:underline">{row.sku}</Link></td>
                                        <td>{row.name} {row.low && <Badge variant="destructive">low</Badge>}</td>
                                        <td className="text-right tabular-nums">{qty(row.quantity)} {row.unit}</td>
                                        <td className="text-right tabular-nums">{money(row.average_cost)}</td>
                                        <td className="text-right tabular-nums">{money(row.value)}</td>
                                        <td className="pl-6 text-muted-foreground">{row.warehouses.map((where) => `${where.name}: ${qty(where.quantity)}`).join(', ')}</td>
                                    </tr>
                                ))}
                                {valuation.rows.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-muted-foreground">No stock yet.</td></tr>}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

InventoryIndex.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Inventory', href: '/accounting/inventory' }] };
