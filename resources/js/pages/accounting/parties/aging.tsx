import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Row = { party_id: number; code: string; name: string; credit_limit: string | null; not_due: string; days_1_30: string; days_31_60: string; days_61_90: string; over_90: string; unapplied: string; total: string };
type Props = {
    report: { as_of: string; side: 'receivable' | 'payable'; rows: Row[]; totals: Record<string, string> };
    reconciliation: { ledger: string; subledger: string; difference: string };
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const columns: Array<[keyof Row, string]> = [
    ['not_due', 'Not due'],
    ['days_1_30', '1–30'],
    ['days_31_60', '31–60'],
    ['days_61_90', '61–90'],
    ['over_90', 'Over 90'],
    ['unapplied', 'Unapplied'],
    ['total', 'Total'],
];

export default function Aging({ report, reconciliation }: Props) {
    const [asOf, setAsOf] = useState(report.as_of);
    const query = `side=${report.side}&as_of=${asOf}`;
    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get('/accounting/receivables/aging', { side: report.side, as_of: asOf }, { preserveScroll: true });
    };
    const receivable = report.side === 'receivable';

    return (
        <>
            <Head title={receivable ? 'Aged Receivables' : 'Aged Payables'} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title={receivable ? 'Aged Receivables by Customer' : 'Aged Payables by Supplier'}
                        description="Open invoices (bills) by days past due, less payments and credits not yet applied."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={`/accounting/receivables/aging?side=${receivable ? 'payable' : 'receivable'}`}>{receivable ? 'Payables' : 'Receivables'}</Link>
                        </Button>
                        {['csv', 'xlsx', 'pdf'].map((format) => (
                            <Button key={format} asChild variant="ghost" size="sm">
                                <a href={`/accounting/receivables/aging/export/${format}?${query}`}>{format.toUpperCase()}</a>
                            </Button>
                        ))}
                    </div>
                </div>

                <form onSubmit={apply} className="flex items-end gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="as_of">As of</Label>
                        <Input id="as_of" type="date" value={asOf} onChange={(event) => setAsOf(event.target.value)} />
                    </div>
                    <Button type="submit" variant="outline">Apply</Button>
                </form>

                {Number(reconciliation.difference) !== 0 ? (
                    <Alert variant="destructive">
                        <AlertTitle>The sub-ledger and the control account differ</AlertTitle>
                        <AlertDescription>
                            Control account {money(reconciliation.ledger)} · sub-ledger {money(reconciliation.subledger)} · difference {money(reconciliation.difference)}. Look for manual entries on the control account.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <div className="text-sm text-muted-foreground">The sub-ledger agrees with the control account ({money(reconciliation.ledger)}).</div>
                )}

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">{receivable ? 'Customer' : 'Supplier'}</th>
                                {columns.map(([, label]) => (
                                    <th key={label} className="px-3 py-2 text-right font-medium">{label}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-3 py-8 text-center text-muted-foreground">
                                        Nothing outstanding.
                                    </td>
                                </tr>
                            ) : null}
                            {report.rows.map((row) => (
                                <tr key={row.party_id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/accounting/parties/${row.party_id}?side=${report.side}`} className="hover:underline">
                                            {row.code} {row.name}
                                        </Link>
                                    </td>
                                    {columns.map(([key]) => (
                                        <td key={key} className={`px-3 py-2 text-right tabular-nums ${key === 'over_90' && Number(row[key]) > 0 ? 'text-red-700' : ''}`}>
                                            {Number(row[key]) === 0 ? '—' : money(row[key] as string)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                            <tr className="border-t bg-muted/30 font-medium">
                                <td className="px-3 py-2">Total</td>
                                {columns.map(([key]) => (
                                    <td key={key} className="px-3 py-2 text-right tabular-nums">{money(report.totals[key])}</td>
                                ))}
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Aging.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Customers & Suppliers', href: '/accounting/parties' },
        { title: 'Ageing', href: '/accounting/receivables/aging' },
    ],
};
