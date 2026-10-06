import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccountingI18n } from '@/lib/i18n';

type Props = {
    items: Array<{ id: number; sku: string; name: string; is_active: boolean }>;
    warehouses: Array<{ id: number; code: string; name: string }>;
    accounts: Array<{ id: number; account_code: string; account_name: string; type: string }>;
    today: string;
    preset: { item_id: string | number; type: string };
};

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const TYPES: Record<string, string> = { receipt: 'Receive stock', issue: 'Issue stock (cost of goods sold)', adjustment: 'Adjust (count difference)', transfer: 'Transfer between warehouses' };

export default function InventoryMovementForm({ items, warehouses, accounts, today, preset }: Props) {
    useAccountingI18n();
    const form = useForm({
        type: preset.type in TYPES ? preset.type : 'receipt', item_id: String(preset.item_id ?? ''), warehouse_id: '', to_warehouse_id: '', movement_date: today, quantity: '', unit_cost: '', offset_account_id: '', reference: '', notes: '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/accounting/inventory/movements');
    };
    const input = (name: 'movement_date' | 'quantity' | 'unit_cost' | 'reference' | 'notes', label: string, type = 'text') => (
        <div className="space-y-1"><Label htmlFor={name}>{label}</Label><Input id={name} type={type} step="any" value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)} /><InputError message={form.errors[name]} /></div>
    );
    const select = (name: 'item_id' | 'warehouse_id' | 'to_warehouse_id' | 'offset_account_id', label: string, options: Array<[number, string]>) => (
        <div className="space-y-1"><Label htmlFor={name}>{label}</Label>
            <select id={name} className={selectClass} value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)}><option value="">Choose…</option>{options.map(([id, text]) => (<option key={id} value={id}>{text}</option>))}</select>
            <InputError message={form.errors[name]} /></div>
    );
    const offsetLabel = form.data.type === 'receipt' ? 'Paid from / owed to' : form.data.type === 'issue' ? 'Cost account (default: the item\'s)' : 'Gain / loss account';

    return (
        <>
            <Head title="Move stock" />
            <form onSubmit={submit} className="space-y-4 p-4">
                <Heading title="Move stock" description="Receipts, issues, count adjustments and transfers; the ledger entry is made with the movement" />
                <Card>
                    <CardHeader><CardTitle>Movement</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <div className="space-y-1"><Label htmlFor="type">What happened</Label>
                            <select id="type" className={selectClass} value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>{Object.entries(TYPES).map(([key, label]) => (<option key={key} value={key}>{label}</option>))}</select></div>
                        {select('item_id', 'Item', items.filter((row) => row.is_active).map((row) => [row.id, `${row.sku} ${row.name}`]))}
                        {select('warehouse_id', form.data.type === 'transfer' ? 'From warehouse' : 'Warehouse', warehouses.map((row) => [row.id, row.name]))}
                        {form.data.type === 'transfer' && select('to_warehouse_id', 'To warehouse', warehouses.map((row) => [row.id, row.name]))}
                        {input('movement_date', 'Date', 'date')}
                        {input('quantity', form.data.type === 'adjustment' ? 'Quantity (negative = lost)' : 'Quantity', 'number')}
                        {(form.data.type === 'receipt' || form.data.type === 'adjustment') && input('unit_cost', form.data.type === 'receipt' ? 'Unit cost' : 'Unit cost (blank = average)', 'number')}
                        {form.data.type !== 'transfer' && select('offset_account_id', offsetLabel, accounts.map((row) => [row.id, `${row.account_code} ${row.account_name}`]))}
                        {input('reference', 'Reference')}{input('notes', 'Notes')}
                    </CardContent>
                </Card>
                <Button type="submit" disabled={form.processing}>Record</Button>
            </form>
        </>
    );
}

InventoryMovementForm.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Inventory', href: '/accounting/inventory' }, { title: 'Move stock', href: '#' }] };
