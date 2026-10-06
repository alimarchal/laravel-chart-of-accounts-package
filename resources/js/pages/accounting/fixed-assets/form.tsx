import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Account = { id: number; account_code: string; account_name: string; type: string };
type Asset = {
    id: number; code: string; name: string; category: string | null; description: string | null; acquisition_date: string; in_service_date: string; cost: string; salvage_value: string;
    useful_life_months: number; method: string; declining_rate: string | null; asset_account_id: number; accumulated_account_id: number; expense_account_id: number; locked: boolean;
};
type Props = { asset: Asset | null; accounts: Account[]; methods: Record<string, string>; today: string };

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function FixedAssetForm({ asset, accounts, methods, today }: Props) {
    const form = useForm({
        code: asset?.code ?? '', name: asset?.name ?? '', category: asset?.category ?? '', description: asset?.description ?? '',
        acquisition_date: asset?.acquisition_date ?? today, in_service_date: asset?.in_service_date ?? '', cost: asset?.cost ?? '', salvage_value: asset?.salvage_value ?? '0',
        useful_life_months: String(asset?.useful_life_months ?? 60), method: asset?.method ?? 'straight_line', declining_rate: asset?.declining_rate ?? '',
        asset_account_id: asset ? String(asset.asset_account_id) : '', accumulated_account_id: asset ? String(asset.accumulated_account_id) : '',
        expense_account_id: asset ? String(asset.expense_account_id) : '', offset_account_id: '',
    });
    const locked = asset?.locked ?? false;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (asset) form.put(`/accounting/fixed-assets/${asset.id}`);
        else form.post('/accounting/fixed-assets');
    };
    const field = (name: keyof typeof form.data, label: string, type = 'text', disabled = false) => (
        <div className="space-y-1">
            <Label htmlFor={name}>{label}</Label>
            <Input id={name} type={type} value={form.data[name]} disabled={disabled} onChange={(event) => form.setData(name, event.target.value)} />
            <InputError message={form.errors[name]} />
        </div>
    );
    const accountSelect = (name: 'asset_account_id' | 'accumulated_account_id' | 'expense_account_id' | 'offset_account_id', label: string, types: string[] | null, disabled = false) => (
        <div className="space-y-1">
            <Label htmlFor={name}>{label}</Label>
            <select id={name} className={selectClass} value={form.data[name]} disabled={disabled} onChange={(event) => form.setData(name, event.target.value)}>
                <option value="">{name === 'offset_account_id' ? 'Do not book the purchase' : 'Choose…'}</option>
                {accounts.filter((account) => types === null || types.includes(account.type)).map((account) => (<option key={account.id} value={account.id}>{account.account_code} {account.account_name}</option>))}
            </select>
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <>
            <Head title={asset ? 'Edit asset' : 'New asset'} />
            <form onSubmit={submit} className="space-y-4 p-4">
                <Heading title={asset ? `Edit ${asset.code}` : 'New asset'} description={locked ? 'Cost, life, method, dates and accounts are locked: the asset already has entries.' : 'Register what the company bought'} />
                <Card>
                    <CardHeader><CardTitle>Asset</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        {field('code', 'Code')}{field('name', 'Name')}{field('category', 'Category')}
                        {field('acquisition_date', 'Acquisition date', 'date', locked)}{field('in_service_date', 'In service from (default: acquisition date)', 'date', locked)}
                        {field('cost', 'Cost', 'number', locked)}{field('salvage_value', 'Salvage value', 'number', locked)}{field('useful_life_months', 'Useful life (months)', 'number', locked)}
                        <div className="space-y-1">
                            <Label htmlFor="method">Method</Label>
                            <select id="method" className={selectClass} value={form.data.method} disabled={locked} onChange={(event) => form.setData('method', event.target.value)}>
                                {Object.entries(methods).map(([key, label]) => (<option key={key} value={key}>{label}</option>))}
                            </select>
                            <InputError message={form.errors.method} />
                        </div>
                        {form.data.method === 'declining_balance' && field('declining_rate', 'Annual rate % (blank = double the straight-line rate)', 'number', locked)}
                        {field('description', 'Description')}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Accounts</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        {accountSelect('asset_account_id', 'Asset account (cost)', ['ASSET'], locked)}
                        {accountSelect('accumulated_account_id', 'Accumulated depreciation account', ['ASSET'], locked)}
                        {accountSelect('expense_account_id', 'Depreciation expense account', ['EXPENSE'], locked)}
                        {!asset && accountSelect('offset_account_id', 'Paid from / owed to (books the purchase)', null)}
                    </CardContent>
                </Card>
                <Button type="submit" disabled={form.processing}>Save</Button>
            </form>
        </>
    );
}

FixedAssetForm.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Fixed assets', href: '/accounting/fixed-assets' }, { title: 'Asset', href: '#' }] };
