import { Head, Link } from '@inertiajs/react';
import { Banknote, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAccounting } from '@/lib/accounting';

type Payment = { id: number; kind: 'receipt' | 'payment'; number: string | null; party: string | null; payment_date: string; amount: string; unapplied: string; method: string | null; reference: string | null; status: 'posted' | 'void' };

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PartyPayments({ payments }: { payments: Payment[] }) {
    const { permissions, flash } = useAccounting();

    return (
        <>
            <Head title="Receipts & Payments" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading title="Receipts & Payments" description="Money received from customers and paid to suppliers, and what it settled." />
                    {permissions['party-payments.create'] ? (
                        <div className="flex gap-2">
                            <Button asChild className="gap-2">
                                <Link href="/accounting/party-payments/create?kind=receipt">
                                    <Plus className="size-4" />
                                    Receipt
                                </Link>
                            </Button>
                            <Button asChild variant="outline" className="gap-2">
                                <Link href="/accounting/party-payments/create?kind=payment">
                                    <Plus className="size-4" />
                                    Payment
                                </Link>
                            </Button>
                        </div>
                    ) : null}
                </div>

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Number</th>
                                <th className="px-3 py-2 font-medium">Party</th>
                                <th className="px-3 py-2 font-medium">Date</th>
                                <th className="px-3 py-2 font-medium">Method</th>
                                <th className="px-3 py-2 text-right font-medium">Amount</th>
                                <th className="px-3 py-2 text-right font-medium">Not applied</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {payments.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-3 py-8 text-center text-muted-foreground">
                                        <Banknote className="mx-auto mb-2 size-5" />
                                        No receipts or payments yet.
                                    </td>
                                </tr>
                            ) : null}
                            {payments.map((payment) => (
                                <tr key={payment.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/accounting/party-payments/${payment.id}`} className="font-medium hover:underline">
                                            {payment.number}
                                        </Link>
                                        <span className="ml-2 text-xs text-muted-foreground">{payment.kind}</span>
                                    </td>
                                    <td className="px-3 py-2">{payment.party}</td>
                                    <td className="px-3 py-2">{payment.payment_date}</td>
                                    <td className="px-3 py-2">{payment.method ?? '—'}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(payment.amount)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{Number(payment.unapplied) ? money(payment.unapplied) : '—'}</td>
                                    <td className="px-3 py-2">
                                        <Badge className={payment.status === 'posted' ? 'bg-emerald-100 text-emerald-800' : 'bg-muted text-muted-foreground'}>{payment.status}</Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

PartyPayments.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Receipts & Payments', href: '/accounting/party-payments' },
    ],
};
