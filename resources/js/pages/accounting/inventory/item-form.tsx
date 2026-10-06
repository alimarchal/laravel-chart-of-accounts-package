import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    item: { id: number; sku: string; name: string; unit: string; category: string | null; reorder_level: string; inventory_account_id: number; cogs_account_id: number; is_active: boolean } | null;
    accounts: Array<{ id: number; account_code: string; account_name: string; type: string }>;
    locked: boolean;
};

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function InventoryItemForm({ item, accounts, locked }: Props) {
    const form = useForm({
        sku: item?.sku ?? '', name: item?.name ?? '', unit: item?.unit ?? 'pcs', category: item?.category ?? '', reorder_level: item?.reorder_level ?? '0',
        inventory_account_id: item ? String(item.inventory_account_id) : '', cogs_account_id: item ? String(item.cogs_account_id) : '', is_active: item?.is_active ?? true,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (item) form.put(`/accounting/inventory/items/${item.id}`);
        else form.post('/accounting/inventory/items');
    };
    const field = (name: 'sku' | 'name' | 'unit' | 'category' | 'reorder_level', label: string, type = 'text') => (
        <div className="space-y-1"><Label htmlFor={name}>{label}</Label><Input id={name} type={type} step="any" value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)} /><InputError message={form.errors[name]} /></div>
    );
    const account = (name: 'inventory_account_id' | 'cogs_account_id', label: string, types: string[]) => (
        <div className="space-y-1"><Label htmlFor={name}>{label}</Label>
            <select id={name} className={selectClass} disabled={locked} value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)}>
                <option value="">Choose…</option>{accounts.filter((row) => types.includes(row.type)).map((row) => (<option key={row.id} value={row.id}>{row.account_code} {row.account_name}</option>))}
            </select><InputError message={form.errors[name]} /></div>
    );

    return (
        <>
            <Head title={item ? 'Edit item' : 'New item'} />
            <form onSubmit={submit} className="space-y-4 p-4">
                <Heading title={item ? `Edit ${item.sku}` : 'New item'} description={locked ? 'Accounts are locked: the item has stock movements.' : 'What you stock and where it is booked'} />
                <Card>
                    <CardHeader><CardTitle>Item</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        {field('sku', 'SKU')}{field('name', 'Name')}{field('unit', 'Unit')}{field('category', 'Category')}{field('reorder_level', 'Reorder level', 'number')}
                        {account('inventory_account_id', 'Inventory account', ['ASSET'])}{account('cogs_account_id', 'Cost of goods sold account', ['EXPENSE'])}
                        <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Active</label>
                    </CardContent>
                </Card>
                <Button type="submit" disabled={form.processing}>Save</Button>
            </form>
        </>
    );
}

InventoryItemForm.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Inventory', href: '/accounting/inventory' }, { title: 'Item', href: '#' }] };
