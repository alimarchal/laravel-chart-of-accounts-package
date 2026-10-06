import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Row = { document_id: number; number: string; kind: string; issue_date: string; customer: string; total: string; status: string; fbr_invoice_number: string | null; error: string | null; attempts: number; submitted_at: string | null; mode: string | null };
type Props = { documents: Row[]; enabled: boolean; mode: string; filters: { status: string } };

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const selectClass = 'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const label: Record<string, string> = { none: 'not sent', pending: 'pending', accepted: 'accepted', failed: 'failed' };

export default function FbrIndex({ documents, enabled, mode, filters }: Props) {
    const { permissions, flash } = useAccounting();
    const [status, setStatus] = useState(filters.status);

    return (
        <>
            <Head title="FBR invoices" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="FBR invoices" description="Sales invoices and credit notes sent to FBR as digital invoices" />
                    <div className="flex items-center gap-2">
                        <select className={selectClass} value={status} onChange={(event) => { setStatus(event.target.value); router.get('/accounting/fbr', event.target.value ? { status: event.target.value } : {}, { preserveState: true }); }}>
                            <option value="">All</option><option value="none">Not sent</option><option value="accepted">Accepted</option><option value="failed">Failed</option>
                        </select>
                    </div>
                </div>
                {!enabled && <Alert><AlertTitle>FBR integration is switched off</AlertTitle><AlertDescription>Set ACCOUNTING_FBR_ENABLED=true and the seller details in the accounting config to send invoices.</AlertDescription></Alert>}
                {enabled && mode === 'fake' && <Alert><AlertTitle>Test mode</AlertTitle><AlertDescription>Invoices are accepted locally and nothing is sent to FBR. Set ACCOUNTING_FBR_MODE=live and the service URL and token to go live.</AlertDescription></Alert>}
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <Card><CardContent className="overflow-x-auto pt-6">
                    <table className="w-full text-sm">
                        <thead><tr className="text-left text-muted-foreground"><th className="py-1">Document</th><th>Date</th><th>Customer</th><th className="text-right">Total</th><th>FBR</th><th>FBR invoice number</th><th /></tr></thead>
                        <tbody>
                            {documents.map((row) => (
                                <tr key={row.document_id} className="border-t align-top">
                                    <td className="py-2 font-medium">{row.number} <span className="text-xs font-normal text-muted-foreground">{row.kind.replace('_', ' ')}</span></td><td>{row.issue_date}</td><td>{row.customer}</td><td className="text-right tabular-nums">{money(row.total)}</td>
                                    <td><Badge variant={row.status === 'failed' ? 'destructive' : 'outline'}>{label[row.status] ?? row.status}</Badge>{row.error && <p className="mt-1 max-w-xs text-xs text-destructive">{row.error}</p>}</td>
                                    <td className="font-mono text-xs">{row.fbr_invoice_number}</td>
                                    <td className="text-right">
                                        {enabled && permissions['fbr.submit'] && row.status !== 'accepted' && (
                                            <Button size="sm" variant="outline" onClick={() => router.post(`/accounting/fbr/documents/${row.document_id}/submit`, {}, { preserveScroll: true })}>{row.status === 'failed' ? 'Retry' : 'Send'}</Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {documents.length === 0 && <tr><td colSpan={7} className="py-6 text-center text-muted-foreground">No posted sales invoices yet.</td></tr>}
                        </tbody>
                    </table>
                </CardContent></Card>
            </div>
        </>
    );
}

FbrIndex.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'FBR invoices', href: '/accounting/fbr' }] };
