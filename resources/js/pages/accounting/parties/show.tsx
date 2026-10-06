import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Props = {
    party: { id: number; type: string; code: string; name: string; email: string | null; phone: string | null; tax_number: string | null; payment_terms_days: number; credit_limit: string | null; is_active: boolean };
    side: 'receivable' | 'payable';
    openItems: {
        items: Array<{ id: number; kind: string; number: string; issue_date: string; due_date: string; reference: string | null; total: string; open: string; days_overdue: number }>;
        credits: Array<{ id: number; kind: string; number: string; date: string; open: string }>;
        balance: string;
    };
    statement: { opening: string; rows: Array<{ date: string; type: string; number: string; reference: string | null; debit: string; credit: string; balance: string }>; closing: string };
    range: { date_from: string; date_to: string };
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PartyShow({ party, side, openItems, statement, range }: Props) {
    const { permissions, flash } = useAccounting();
    const [dates, setDates] = useState(range);
    const base = `/accounting/parties/${party.id}`;
    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get(base, { side, ...dates }, { preserveScroll: true });
    };
    const over = party.credit_limit !== null && side === 'receivable' && Number(openItems.balance) > Number(party.credit_limit);

    return (
        <>
            <Head title={party.name} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title={`${party.code} · ${party.name}`}
                        description={`${party.type}${party.tax_number ? ` · tax no. ${party.tax_number}` : ''} · terms ${party.payment_terms_days} days${party.credit_limit ? ` · credit limit ${money(party.credit_limit)}` : ''}${party.is_active ? '' : ' · inactive'}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        {party.type === 'both' ? (
                            <Button asChild variant="outline">
                                <Link href={`${base}?side=${side === 'receivable' ? 'payable' : 'receivable'}`}>Show {side === 'receivable' ? 'payables' : 'receivables'}</Link>
                            </Button>
                        ) : null}
                        {permissions['party-documents.create'] ? (
                            <Button asChild variant="outline">
                                <Link href={`/accounting/party-documents/create?kind=${side === 'receivable' ? 'invoice' : 'bill'}`}>{side === 'receivable' ? 'New invoice' : 'New bill'}</Link>
                            </Button>
                        ) : null}
                        {permissions['party-payments.create'] ? (
                            <Button asChild variant="outline">
                                <Link href={`/accounting/party-payments/create?kind=${side === 'receivable' ? 'receipt' : 'payment'}&party_id=${party.id}`}>{side === 'receivable' ? 'Receive payment' : 'Pay supplier'}</Link>
                            </Button>
                        ) : null}
                        {permissions['parties.update'] ? (
                            <Button asChild variant="ghost">
                                <Link href={`${base}/edit`}>Edit</Link>
                            </Button>
                        ) : null}
                    </div>
                </div>

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                {over ? (
                    <Alert variant="destructive">
                        <AlertTitle>Over the credit limit</AlertTitle>
                        <AlertDescription>
                            The balance {money(openItems.balance)} is above the limit of {money(party.credit_limit ?? '0')}.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="rounded-md border p-3">
                    <div className="text-xs uppercase text-muted-foreground">{side === 'receivable' ? 'They owe you' : 'You owe them'}</div>
                    <div className="mt-1 text-2xl tabular-nums">{money(openItems.balance)}</div>
                </div>

                <h3 className="text-sm font-medium">Open items</h3>
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Document</th>
                                <th className="px-3 py-2 font-medium">Issued</th>
                                <th className="px-3 py-2 font-medium">Due</th>
                                <th className="px-3 py-2 text-right font-medium">Total</th>
                                <th className="px-3 py-2 text-right font-medium">Open</th>
                                <th className="px-3 py-2 text-right font-medium">Days overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            {openItems.items.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-3 py-6 text-center text-muted-foreground">
                                        Nothing open.
                                    </td>
                                </tr>
                            ) : null}
                            {openItems.items.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/accounting/party-documents/${item.id}`} className="hover:underline">
                                            {item.number}
                                        </Link>
                                        {item.reference ? <span className="ml-2 text-xs text-muted-foreground">{item.reference}</span> : null}
                                    </td>
                                    <td className="px-3 py-2">{item.issue_date}</td>
                                    <td className="px-3 py-2">{item.due_date}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(item.total)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(item.open)}</td>
                                    <td className={`px-3 py-2 text-right tabular-nums ${item.days_overdue > 0 ? 'text-red-700' : ''}`}>{item.days_overdue || '—'}</td>
                                </tr>
                            ))}
                            {openItems.credits.map((credit) => (
                                <tr key={`c${credit.kind}${credit.id}`} className="border-t text-muted-foreground">
                                    <td className="px-3 py-2" colSpan={4}>
                                        {credit.number} ({credit.kind.replace('_', ' ')}, not yet applied)
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">-{money(credit.open)}</td>
                                    <td />
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <h3 className="text-sm font-medium">Statement</h3>
                <form onSubmit={apply} className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="date_from">From</Label>
                        <Input id="date_from" type="date" value={dates.date_from} onChange={(event) => setDates({ ...dates, date_from: event.target.value })} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="date_to">To</Label>
                        <Input id="date_to" type="date" value={dates.date_to} onChange={(event) => setDates({ ...dates, date_to: event.target.value })} />
                    </div>
                    <Button type="submit" variant="outline">Apply</Button>
                </form>
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Date</th>
                                <th className="px-3 py-2 font-medium">Document</th>
                                <th className="px-3 py-2 text-right font-medium">Debit</th>
                                <th className="px-3 py-2 text-right font-medium">Credit</th>
                                <th className="px-3 py-2 text-right font-medium">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="border-t bg-muted/30">
                                <td className="px-3 py-2" colSpan={4}>Opening balance</td>
                                <td className="px-3 py-2 text-right tabular-nums">{money(statement.opening)}</td>
                            </tr>
                            {statement.rows.map((row, index) => (
                                <tr key={index} className="border-t">
                                    <td className="px-3 py-2">{row.date}</td>
                                    <td className="px-3 py-2">
                                        {row.number} <span className="text-xs text-muted-foreground">{row.type.replace('_', ' ')}</span>
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">{Number(row.debit) ? money(row.debit) : ''}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{Number(row.credit) ? money(row.credit) : ''}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(row.balance)}</td>
                                </tr>
                            ))}
                            <tr className="border-t bg-muted/30 font-medium">
                                <td className="px-3 py-2" colSpan={4}>Closing balance</td>
                                <td className="px-3 py-2 text-right tabular-nums">{money(statement.closing)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

PartyShow.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Customers & Suppliers', href: '/accounting/parties' },
    ],
};
