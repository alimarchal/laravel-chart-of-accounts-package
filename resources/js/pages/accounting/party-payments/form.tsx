import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Props = {
    kind: 'receipt' | 'payment';
    partyId: number | null;
    parties: Array<{ id: number; code: string; name: string }>;
    bankAccounts: Array<{ id: number; account_code: string; account_name: string }>;
    openDocuments: Array<{ id: number; party_id: number; number: string; due_date: string; open: string }>;
    today: string;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PartyPaymentForm({ kind, partyId, parties, bankAccounts, openDocuments, today }: Props) {
    const { flash } = useAccounting();
    const form = useForm<{
        party_id: string;
        kind: string;
        payment_date: string;
        amount: string;
        account_id: string;
        method: string;
        reference: string;
        notes: string;
        auto_allocate: boolean;
        allocations: Record<number, string>;
    }>({
        party_id: partyId ? String(partyId) : '',
        kind,
        payment_date: today,
        amount: '',
        account_id: '',
        method: '',
        reference: '',
        notes: '',
        auto_allocate: true,
        allocations: {},
    });
    const documents = openDocuments.filter((document) => String(document.party_id) === form.data.party_id);
    const receipt = kind === 'receipt';
    const chosen = Object.values(form.data.allocations).reduce((sum, value) => sum + Number(value || 0), 0);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => {
            const allocations = Object.entries(data.allocations).filter(([, value]) => Number(value) > 0).map(([id, amount]) => ({ document_id: Number(id), amount }));

            return { ...data, allocations: allocations.length > 0 ? allocations : undefined, auto_allocate: allocations.length === 0 && data.auto_allocate };
        });
        form.post('/accounting/party-payments');
    };

    return (
        <>
            <Head title={receipt ? 'New Receipt' : 'New Payment'} />
            <form onSubmit={submit} className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <Heading title={receipt ? 'Receive Payment from a Customer' : 'Pay a Supplier'} description="Posted straight away: the bank account against the party's control account." />
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}
                <Card>
                    <CardHeader>
                        <CardTitle>Payment</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="party_id">{receipt ? 'Customer' : 'Supplier'}</Label>
                            <select id="party_id" className={selectClass} value={form.data.party_id} onChange={(event) => form.setData({ ...form.data, party_id: event.target.value, allocations: {} })}>
                                <option value="">Select</option>
                                {parties.map((party) => (
                                    <option key={party.id} value={party.id}>
                                        {party.code} - {party.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.party_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payment_date">Date</Label>
                            <Input id="payment_date" type="date" value={form.data.payment_date} onChange={(event) => form.setData('payment_date', event.target.value)} />
                            <InputError message={form.errors.payment_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="amount">Amount</Label>
                            <Input id="amount" type="number" step="0.01" min="0" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} />
                            <InputError message={form.errors.amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="account_id">{receipt ? 'Deposited to' : 'Paid from'}</Label>
                            <select id="account_id" className={selectClass} value={form.data.account_id} onChange={(event) => form.setData('account_id', event.target.value)}>
                                <option value="">Select a bank or cash account</option>
                                {bankAccounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.account_code} - {account.account_name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.account_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="method">Method</Label>
                            <Input id="method" placeholder="cash, cheque, transfer …" value={form.data.method} onChange={(event) => form.setData('method', event.target.value)} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reference">Reference (cheque / transfer no.)</Label>
                            <Input id="reference" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Settles</CardTitle>
                        <CardDescription>
                            Enter amounts against specific {receipt ? 'invoices' : 'bills'}, or leave them empty to settle the oldest first. What is not allocated stays on the account.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {documents.length === 0 ? (
                            <div className="text-sm text-muted-foreground">{form.data.party_id === '' ? 'Choose a party first.' : 'Nothing open for this party.'}</div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <tbody>
                                        {documents.map((document) => (
                                            <tr key={document.id} className="border-t first:border-t-0">
                                                <td className="px-3 py-2">{document.number}</td>
                                                <td className="px-3 py-2">due {document.due_date}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">open {money(document.open)}</td>
                                                <td className="px-3 py-2 text-right">
                                                    <Input
                                                        className="ml-auto w-32"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        placeholder="Amount"
                                                        value={form.data.allocations[document.id] ?? ''}
                                                        onChange={(event) => form.setData('allocations', { ...form.data.allocations, [document.id]: event.target.value })}
                                                        aria-label={`Amount for ${document.number}`}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {chosen > 0 ? <div className="text-sm tabular-nums">Allocated {money(String(chosen))} of {money(form.data.amount || '0')}</div> : null}
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" className="gap-2" disabled={form.processing}>
                        <Save className="size-4" />
                        Record
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href="/accounting/party-payments">Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

PartyPaymentForm.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Receipts & Payments', href: '/accounting/party-payments' },
    ],
};
