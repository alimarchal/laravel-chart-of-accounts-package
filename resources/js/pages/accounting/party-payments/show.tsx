import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccounting } from '@/lib/accounting';

type Props = {
    payment: {
        id: number;
        kind: string;
        number: string | null;
        party: string | null;
        party_id: number;
        payment_date: string;
        amount: string;
        unapplied: string;
        account: string | null;
        method: string | null;
        reference: string | null;
        notes: string | null;
        status: 'posted' | 'void';
        journal_entry_id: number | null;
        voucher_number: string | null;
        allocations: Array<{ id: number; document_id: number; document: string; amount: string; allocated_on: string }>;
    };
    openDocuments: Array<{ id: number; number: string; due_date: string; open: string }>;
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PartyPaymentShow({ payment, openDocuments }: Props) {
    const { permissions, flash } = useAccounting();
    const [amounts, setAmounts] = useState<Record<number, string>>({});
    const base = `/accounting/party-payments/${payment.id}`;
    const allocate = (auto: boolean) =>
        router.post(
            `${base}/allocate`,
            (auto ? { auto_allocate: true } : { allocations: Object.entries(amounts).filter(([, value]) => Number(value) > 0).map(([id, amount]) => ({ document_id: Number(id), amount })) }) as never,
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={payment.number ?? 'Payment'} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title={`${payment.kind} ${payment.number}`}
                        description={`${payment.party} · ${payment.payment_date} · ${payment.account ?? ''}${payment.method ? ` · ${payment.method}` : ''}${payment.reference ? ` · ${payment.reference}` : ''}`}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge className={payment.status === 'posted' ? 'bg-emerald-100 text-emerald-800' : 'bg-muted text-muted-foreground'}>{payment.status}</Badge>
                        {payment.status === 'posted' && permissions['party-payments.void'] ? (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    if (confirm('Void this payment? Its entry is reversed and its allocations released.')) router.post(`${base}/void`, {}, { preserveScroll: true });
                                }}
                            >
                                Void
                            </Button>
                        ) : null}
                        {payment.journal_entry_id ? (
                            <Button asChild variant="ghost">
                                <Link href={`/accounting/journal-entries/${payment.journal_entry_id}`}>{payment.voucher_number ?? 'Entry'}</Link>
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
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="text-sm tabular-nums" data-testid="payment-amounts">
                    Amount {money(payment.amount)} · not applied {money(payment.unapplied)}
                </div>

                <h3 className="text-sm font-medium">Settled documents</h3>
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <tbody>
                            {payment.allocations.length === 0 ? (
                                <tr>
                                    <td className="px-3 py-6 text-center text-muted-foreground">Not applied to any document yet.</td>
                                </tr>
                            ) : null}
                            {payment.allocations.map((allocation) => (
                                <tr key={allocation.id} className="border-t first:border-t-0">
                                    <td className="px-3 py-2">
                                        <Link href={`/accounting/party-documents/${allocation.document_id}`} className="hover:underline">
                                            {allocation.document}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{allocation.allocated_on}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(allocation.amount)}</td>
                                    <td className="px-3 py-2 text-right">
                                        {permissions['party-payments.create'] && payment.status === 'posted' ? (
                                            <Button size="sm" variant="ghost" onClick={() => router.delete(`/accounting/party-allocations/${allocation.id}`, { preserveScroll: true })}>
                                                Undo
                                            </Button>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {payment.status === 'posted' && Number(payment.unapplied) > 0 && openDocuments.length > 0 && permissions['party-payments.create'] ? (
                    <>
                        <h3 className="text-sm font-medium">Apply the rest to</h3>
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <tbody>
                                    {openDocuments.map((document) => (
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
                                                    value={amounts[document.id] ?? ''}
                                                    onChange={(event) => setAmounts({ ...amounts, [document.id]: event.target.value })}
                                                    aria-label={`Amount for ${document.number}`}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex gap-2">
                            <Button variant="outline" onClick={() => allocate(false)}>Apply</Button>
                            <Button variant="ghost" onClick={() => allocate(true)}>Oldest first</Button>
                        </div>
                    </>
                ) : null}
            </div>
        </>
    );
}

PartyPaymentShow.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Receipts & Payments', href: '/accounting/party-payments' },
    ],
};
