import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Account = { id: number; account_code: string; account_name: string };
type Props = {
    party: {
        id: number;
        type: string;
        code: string;
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        tax_number: string | null;
        payment_terms_days: number;
        credit_limit: string | null;
        receivable_account_id: number | null;
        payable_account_id: number | null;
        is_active: boolean;
        notes: string | null;
    } | null;
    receivableAccounts: Account[];
    payableAccounts: Account[];
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function PartyForm({ party, receivableAccounts, payableAccounts }: Props) {
    const { flash } = useAccounting();
    const form = useForm({
        type: party?.type ?? 'customer',
        code: party?.code ?? '',
        name: party?.name ?? '',
        email: party?.email ?? '',
        phone: party?.phone ?? '',
        address: party?.address ?? '',
        tax_number: party?.tax_number ?? '',
        payment_terms_days: String(party?.payment_terms_days ?? 30),
        credit_limit: party?.credit_limit ?? '',
        receivable_account_id: party?.receivable_account_id ? String(party.receivable_account_id) : '',
        payable_account_id: party?.payable_account_id ? String(party.payable_account_id) : '',
        is_active: party?.is_active ?? true,
        notes: party?.notes ?? '',
    });
    const field = (name: keyof typeof form.data, label: string, type = 'text') => (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input id={name} type={type} value={String(form.data[name] ?? '')} onChange={(event) => form.setData(name, event.target.value as never)} />
            <InputError message={form.errors[name]} />
        </div>
    );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (party) {
            form.put(`/accounting/parties/${party.id}`);
        } else {
            form.post('/accounting/parties');
        }
    };

    return (
        <>
            <Head title={party ? 'Edit Customer or Supplier' : 'New Customer or Supplier'} />
            <form onSubmit={submit} className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <Heading title={party ? `Edit ${party.name}` : 'New Customer or Supplier'} description="Invoices and receipts post to the receivables control account, bills and payments to the payables one, unless you set accounts here." />
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}
                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="type">Type</Label>
                            <select id="type" className={selectClass} value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>
                                <option value="customer">Customer</option>
                                <option value="supplier">Supplier</option>
                                <option value="both">Both</option>
                            </select>
                            <InputError message={form.errors.type} />
                        </div>
                        {field('code', 'Code')}
                        {field('name', 'Name')}
                        {field('email', 'Email', 'email')}
                        {field('phone', 'Phone')}
                        {field('tax_number', 'Tax number (NTN / STRN)')}
                        {field('payment_terms_days', 'Payment terms (days)', 'number')}
                        {field('credit_limit', 'Credit limit', 'number')}
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="address">Address</Label>
                            <Input id="address" value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="receivable_account_id">Receivable account (optional)</Label>
                            <select id="receivable_account_id" className={selectClass} value={form.data.receivable_account_id} onChange={(event) => form.setData('receivable_account_id', event.target.value)}>
                                <option value="">Control account</option>
                                {receivableAccounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.account_code} - {account.account_name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.receivable_account_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payable_account_id">Payable account (optional)</Label>
                            <select id="payable_account_id" className={selectClass} value={form.data.payable_account_id} onChange={(event) => form.setData('payable_account_id', event.target.value)}>
                                <option value="">Control account</option>
                                {payableAccounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.account_code} - {account.account_name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.payable_account_id} />
                        </div>
                        <label className="flex items-center gap-2 pt-7 text-sm">
                            <input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} />
                            Active
                        </label>
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="notes">Notes</Label>
                            <Input id="notes" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                        </div>
                    </CardContent>
                </Card>
                <div className="flex gap-2">
                    <Button type="submit" className="gap-2" disabled={form.processing}>
                        <Save className="size-4" />
                        Save
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href="/accounting/parties">Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

PartyForm.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Customers & Suppliers', href: '/accounting/parties' },
    ],
};
