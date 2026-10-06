import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Props = {
    item: { id: number; sku: string; name: string; unit: string; category: string | null; reorder_level: string; on_hand_quantity: string; on_hand_value: string; is_active: boolean };
    card: Array<{ id: number; date: string; type: string; warehouse_id: number; quantity: string; unit_cost: string; value: string; balance_quantity: string; balance_value: string; reference: string | null; journal_entry_id: number | null }>;
    warehouses: Array<{ id: number; code: string; name: string }>;
    locked: boolean;
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const qty = (value: string) => Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 });

export default function InventoryItemShow({ item, card, warehouses, locked }: Props) {
    const { permissions, flash } = useAccounting();
    const where = Object.fromEntries(warehouses.map((row) => [row.id, row.name]));

    return (
        <>
            <Head title={item.sku} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title={`${item.sku} · ${item.name}`} description={item.category ?? undefined} />
                    <div className="flex items-center gap-2">
                        {!item.is_active && <Badge variant="outline">inactive</Badge>}
                        {permissions['inventory.move'] && <Button asChild><Link href={`/accounting/inventory/movements/create?item_id=${item.id}`}>Move stock</Link></Button>}
                        {permissions['inventory.manage'] && <Button asChild variant="outline"><Link href={`/accounting/inventory/items/${item.id}/edit`}>Edit</Link></Button>}
                        {permissions['inventory.manage'] && !locked && <Button variant="ghost" onClick={() => confirm('Delete this item?') && router.delete(`/accounting/inventory/items/${item.id}`)}>Delete</Button>}
                    </div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">On hand</p><p className="text-2xl font-semibold tabular-nums">{qty(item.on_hand_quantity)} {item.unit}</p></CardContent></Card>
                    <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Value</p><p className="text-2xl font-semibold tabular-nums">{money(item.on_hand_value)}</p></CardContent></Card>
                    <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Reorder level</p><p className="text-2xl font-semibold tabular-nums">{qty(item.reorder_level)}</p></CardContent></Card>
                </div>
                <Card>
                    <CardHeader><CardTitle>Stock card</CardTitle></CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="text-left text-muted-foreground"><th className="py-1">Date</th><th>Type</th><th>Warehouse</th><th className="text-right">Quantity</th><th className="text-right">Unit cost</th><th className="text-right">Value</th><th className="text-right">Balance</th><th className="text-right">Entry</th></tr></thead>
                            <tbody>
                                {card.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="py-1">{row.date}</td><td>{row.type.replace('_', ' ')}</td><td>{where[row.warehouse_id]}</td>
                                        <td className="text-right tabular-nums">{qty(row.quantity)}</td><td className="text-right tabular-nums">{money(row.unit_cost)}</td><td className="text-right tabular-nums">{money(row.value)}</td>
                                        <td className="text-right tabular-nums">{qty(row.balance_quantity)} / {money(row.balance_value)}</td>
                                        <td className="text-right">{row.journal_entry_id ? <Link className="hover:underline" href={`/accounting/journal-entries/${row.journal_entry_id}`}>#{row.journal_entry_id}</Link> : '—'}</td>
                                    </tr>
                                ))}
                                {card.length === 0 && <tr><td colSpan={8} className="py-4 text-center text-muted-foreground">No movements yet.</td></tr>}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

InventoryItemShow.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Inventory', href: '/accounting/inventory' }, { title: 'Item', href: '#' }] };
