import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAccounting } from '@/lib/accounting';

type Document = {
    id: number;
    kind: string;
    number: string | null;
    party: string | null;
    party_code: string | null;
    issue_date: string;
    due_date: string;
    reference: string | null;
    total: string;
    open?: string;
    status: 'draft' | 'posted' | 'void';
};

const style: Record<Document['status'], string> = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    posted: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    void: 'bg-muted text-muted-foreground',
};
const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const label = (kind: string) => kind.replace('_', ' ');

export default function PartyDocuments({ documents, filters }: { documents: Document[]; filters: { kind: string | null; status: string | null } }) {
    const { permissions, flash } = useAccounting();
    const [kind, setKind] = useState(filters.kind ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get('/accounting/party-documents', { kind: kind || undefined, status: status || undefined }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Invoices & Bills" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading title="Invoices & Bills" description="Sales invoices and credit notes, purchase bills and debit notes." />
                    {permissions['party-documents.create'] ? (
                        <div className="flex flex-wrap gap-2">
                            {[
                                ['invoice', 'Invoice'],
                                ['bill', 'Bill'],
                                ['credit_note', 'Credit note'],
                                ['debit_note', 'Debit note'],
                            ].map(([value, text]) => (
                                <Button key={value} asChild variant={value === 'invoice' ? 'default' : 'outline'} className="gap-2">
                                    <Link href={`/accounting/party-documents/create?kind=${value}`}>
                                        <Plus className="size-4" />
                                        {text}
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    ) : null}
                </div>

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                <form onSubmit={apply} className="flex flex-wrap items-end gap-3">
                    <select className={selectClass} value={kind} onChange={(event) => setKind(event.target.value)} aria-label="Kind">
                        <option value="">All kinds</option>
                        <option value="invoice">Invoices</option>
                        <option value="bill">Bills</option>
                        <option value="credit_note">Credit notes</option>
                        <option value="debit_note">Debit notes</option>
                    </select>
                    <select className={selectClass} value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Status">
                        <option value="">Any status</option>
                        <option value="draft">Draft</option>
                        <option value="posted">Posted</option>
                        <option value="void">Void</option>
                    </select>
                    <Button type="submit" variant="outline">Filter</Button>
                </form>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Number</th>
                                <th className="px-3 py-2 font-medium">Party</th>
                                <th className="px-3 py-2 font-medium">Issued</th>
                                <th className="px-3 py-2 font-medium">Due</th>
                                <th className="px-3 py-2 text-right font-medium">Total</th>
                                <th className="px-3 py-2 text-right font-medium">Open</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {documents.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-3 py-8 text-center text-muted-foreground">
                                        <FileText className="mx-auto mb-2 size-5" />
                                        No documents yet.
                                    </td>
                                </tr>
                            ) : null}
                            {documents.map((document) => (
                                <tr key={document.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/accounting/party-documents/${document.id}`} className="font-medium hover:underline">
                                            {document.number ?? `Draft #${document.id}`}
                                        </Link>
                                        <span className="ml-2 text-xs capitalize text-muted-foreground">{label(document.kind)}</span>
                                    </td>
                                    <td className="px-3 py-2">{document.party}</td>
                                    <td className="px-3 py-2">{document.issue_date}</td>
                                    <td className="px-3 py-2">{document.due_date}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(document.total)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{document.open === undefined ? '—' : money(document.open)}</td>
                                    <td className="px-3 py-2">
                                        <Badge className={style[document.status]}>{document.status}</Badge>
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

PartyDocuments.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Invoices & Bills', href: '/accounting/party-documents' },
    ],
};
