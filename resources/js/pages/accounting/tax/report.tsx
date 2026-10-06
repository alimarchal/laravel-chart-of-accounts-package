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
    report: {
        date_from: string;
        date_to: string;
        rows: Array<{ tax_code_id: number; code: string; name: string; kind: string; base: string; tax: string; documents: number }>;
        totals: Record<string, string>;
    };
    detail: Array<{
        journal_entry_id: number;
        voucher_number: string | null;
        entry_date: string;
        reference: string | null;
        tax_code: string;
        role: 'base' | 'tax';
        rate: string | null;
        amount: string;
    }>;
    filters: { date_from: string; date_to: string; tax_code_id: number | null };
    taxCodes: Array<{ id: number; code: string; name: string }>;
};

const money = (value: string) =>
    Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function TaxReport({ report, detail, filters, taxCodes }: Props) {
    const { flash } = useAccounting();
    const [range, setRange] = useState({ date_from: filters.date_from, date_to: filters.date_to, tax_code_id: String(filters.tax_code_id ?? '') });
    const query = new URLSearchParams(Object.entries(range).filter(([, value]) => value !== '') as Array<[string, string]>).toString();
    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get('/accounting/tax/returns/report', Object.fromEntries(new URLSearchParams(query)), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Tax Report" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading title="Tax Report" description="Taxable amounts and tax by tax code, from posted entries in the base currency." />
                    <div className="flex gap-2">
                        {['csv', 'xlsx', 'pdf'].map((format) => (
                            <Button key={format} asChild variant="ghost" size="sm">
                                <a href={`/accounting/tax/returns/report/export/${format}?${query}`}>{format.toUpperCase()}</a>
                            </Button>
                        ))}
                        <Button asChild variant="outline">
                            <Link href="/accounting/tax">Back</Link>
                        </Button>
                    </div>
                </div>

                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <form onSubmit={apply} className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="date_from">From</Label>
                        <Input id="date_from" type="date" value={range.date_from} onChange={(event) => setRange({ ...range, date_from: event.target.value })} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="date_to">To</Label>
                        <Input id="date_to" type="date" value={range.date_to} onChange={(event) => setRange({ ...range, date_to: event.target.value })} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="tax_code_id">Tax code</Label>
                        <select id="tax_code_id" className={selectClass} value={range.tax_code_id} onChange={(event) => setRange({ ...range, tax_code_id: event.target.value })}>
                            <option value="">All</option>
                            {taxCodes.map((code) => (
                                <option key={code.id} value={code.id}>
                                    {code.code}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button type="submit" variant="outline">Apply</Button>
                </form>

                <div className="grid gap-3 md:grid-cols-5">
                    {[
                        ['Output tax', report.totals.output_tax],
                        ['Input tax', report.totals.input_tax],
                        ['Net payable', report.totals.net_payable],
                        ['Withheld by us', report.totals.withheld],
                        ['Advance tax', report.totals.advance],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-md border p-3">
                            <div className="text-xs uppercase text-muted-foreground">{label}</div>
                            <div className="mt-1 text-lg tabular-nums">{money(value)}</div>
                        </div>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Tax code</th>
                                <th className="px-3 py-2 font-medium">Kind</th>
                                <th className="px-3 py-2 text-right font-medium">Taxable base</th>
                                <th className="px-3 py-2 text-right font-medium">Tax</th>
                                <th className="px-3 py-2 text-right font-medium">Documents</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                                        No taxed documents in this period.
                                    </td>
                                </tr>
                            ) : null}
                            {report.rows.map((row) => (
                                <tr key={row.tax_code_id} className="border-t">
                                    <td className="px-3 py-2">
                                        {row.code} <span className="text-xs text-muted-foreground">{row.name}</span>
                                    </td>
                                    <td className="px-3 py-2">{row.kind}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(row.base)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(row.tax)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{row.documents}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <h3 className="text-sm font-medium">Documents</h3>
                <div className="max-h-[28rem] overflow-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 bg-muted text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Date</th>
                                <th className="px-3 py-2 font-medium">Entry</th>
                                <th className="px-3 py-2 font-medium">Reference</th>
                                <th className="px-3 py-2 font-medium">Tax code</th>
                                <th className="px-3 py-2 font-medium">Line</th>
                                <th className="px-3 py-2 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {detail.map((row, index) => (
                                <tr key={index} className="border-t">
                                    <td className="px-3 py-2">{row.entry_date}</td>
                                    <td className="px-3 py-2">
                                        <Link href={`/accounting/journal-entries/${row.journal_entry_id}`} className="hover:underline">
                                            {row.voucher_number ?? `#${row.journal_entry_id}`}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{row.reference}</td>
                                    <td className="px-3 py-2">{row.tax_code}</td>
                                    <td className="px-3 py-2">{row.role === 'base' ? 'taxable amount' : `tax${row.rate ? ` @ ${Number(row.rate)}%` : ''}`}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(row.amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

TaxReport.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Tax', href: '/accounting/tax' },
        { title: 'Report', href: '/accounting/tax/returns/report' },
    ],
};
