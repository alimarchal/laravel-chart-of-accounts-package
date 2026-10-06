import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccounting } from '@/lib/accounting';

type Props = {
    document: {
        id: number;
        kind: string;
        number: string | null;
        party_id: number;
        party: string | null;
        issue_date: string;
        due_date: string;
        reference: string | null;
        prices_include_tax: boolean;
        subtotal: string;
        tax_total: string;
        total: string;
        open?: string;
        status: 'draft' | 'posted' | 'void';
        notes: string | null;
        journal_entry_id: number | null;
        voucher_number: string | null;
        lines: Array<{ account: string | null; description: string | null; quantity: string; unit_price: string; tax_code: string | null; tax_rate: string | null; net_amount: string; tax_amount: string }>;
    };
    allocations: Array<{ id: number; document: string; source: string | null; amount: string; allocated_on: string }>;
    openInvoices: Array<{ id: number; number: string; due_date: string; open: string }>;
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const style = { draft: 'bg-amber-100 text-amber-800', posted: 'bg-emerald-100 text-emerald-800', void: 'bg-muted text-muted-foreground' };

export default function PartyDocumentShow({ document, allocations, openInvoices }: Props) {
    const { permissions, flash } = useAccounting();
    const [amounts, setAmounts] = useState<Record<number, string>>({});
    const base = `/accounting/party-documents/${document.id}`;
    const act = (action: string) => router.post(`${base}/${action}`, {}, { preserveScroll: true });
    const apply = () =>
        router.post(
            `${base}/apply`,
            { allocations: Object.entries(amounts).filter(([, value]) => Number(value) > 0).map(([id, amount]) => ({ document_id: Number(id), amount })) } as never,
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={document.number ?? `Draft #${document.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title={`${document.kind.replace('_', ' ')} ${document.number ?? `(draft #${document.id})`}`}
                        description={`${document.party} · issued ${document.issue_date} · due ${document.due_date}${document.reference ? ` · ${document.reference}` : ''}`}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge className={style[document.status]}>{document.status}</Badge>
                        {document.status === 'draft' && permissions['party-documents.update'] ? (
                            <Button asChild variant="outline">
                                <Link href={`${base}/edit`}>Edit</Link>
                            </Button>
                        ) : null}
                        {document.status === 'draft' && permissions['party-documents.post'] ? <Button onClick={() => act('post')}>Post</Button> : null}
                        {document.status === 'draft' && permissions['party-documents.delete'] ? (
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    if (confirm('Delete this draft?')) router.delete(base);
                                }}
                            >
                                Delete
                            </Button>
                        ) : null}
                        {document.status === 'posted' && permissions['party-documents.void'] ? (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    if (confirm('Void this document? Its entry is reversed.')) act('void');
                                }}
                            >
                                Void
                            </Button>
                        ) : null}
                        {document.journal_entry_id ? (
                            <Button asChild variant="ghost">
                                <Link href={`/accounting/journal-entries/${document.journal_entry_id}`}>{document.voucher_number ?? 'Entry'}</Link>
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

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Account</th>
                                <th className="px-3 py-2 font-medium">Description</th>
                                <th className="px-3 py-2 text-right font-medium">Qty</th>
                                <th className="px-3 py-2 text-right font-medium">Price</th>
                                <th className="px-3 py-2 font-medium">Tax</th>
                                <th className="px-3 py-2 text-right font-medium">Net</th>
                                <th className="px-3 py-2 text-right font-medium">Tax amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {document.lines.map((line, index) => (
                                <tr key={index} className="border-t">
                                    <td className="px-3 py-2">{line.account}</td>
                                    <td className="px-3 py-2">{line.description}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{Number(line.quantity)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(line.unit_price)}</td>
                                    <td className="px-3 py-2">{line.tax_code ? `${line.tax_code} ${Number(line.tax_rate)}%` : '—'}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(line.net_amount)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(line.tax_amount)}</td>
                                </tr>
                            ))}
                            <tr className="border-t bg-muted/30 font-medium">
                                <td className="px-3 py-2" colSpan={5}>Total</td>
                                <td className="px-3 py-2 text-right tabular-nums">{money(document.subtotal)}</td>
                                <td className="px-3 py-2 text-right tabular-nums">{money(document.tax_total)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div className="text-sm tabular-nums">
                    Total {money(document.total)}
                    {document.open !== undefined ? ` · open ${money(document.open)}` : ''}
                </div>

                {allocations.length > 0 ? (
                    <>
                        <h3 className="text-sm font-medium">Settled with</h3>
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <tbody>
                                    {allocations.map((allocation) => (
                                        <tr key={allocation.id} className="border-t first:border-t-0">
                                            <td className="px-3 py-2">{allocation.source}</td>
                                            <td className="px-3 py-2">{allocation.document}</td>
                                            <td className="px-3 py-2">{allocation.allocated_on}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{money(allocation.amount)}</td>
                                            <td className="px-3 py-2 text-right">
                                                {permissions['party-payments.create'] ? (
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
                    </>
                ) : null}

                {openInvoices.length > 0 && permissions['party-payments.create'] ? (
                    <>
                        <h3 className="text-sm font-medium">Apply this {document.kind.replace('_', ' ')} to</h3>
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <tbody>
                                    {openInvoices.map((open) => (
                                        <tr key={open.id} className="border-t first:border-t-0">
                                            <td className="px-3 py-2">{open.number}</td>
                                            <td className="px-3 py-2">due {open.due_date}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">open {money(open.open)}</td>
                                            <td className="px-3 py-2 text-right">
                                                <Input
                                                    className="ml-auto w-32"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="Amount"
                                                    value={amounts[open.id] ?? ''}
                                                    onChange={(event) => setAmounts({ ...amounts, [open.id]: event.target.value })}
                                                    aria-label={`Amount for ${open.number}`}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <Button variant="outline" onClick={apply}>Apply</Button>
                        </div>
                    </>
                ) : null}
            </div>
        </>
    );
}

PartyDocumentShow.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Invoices & Bills', href: '/accounting/party-documents' },
    ],
};
