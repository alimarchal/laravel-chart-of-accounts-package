import { Head, Link, router } from '@inertiajs/react';
import { Download, Filter } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type CashFlowRow = {
    entry_date: string;
    reference: string;
    account_code: string;
    account_name: string;
    journal_description: string;
    cash_in: number | string;
    cash_out: number | string;
    net_cash_flow: number | string;
};

function money(value: number | string | null | undefined): string {
    return Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting' },
    { title: 'Cash Flow', href: '/accounting/reports/cash-flow' },
];

export default function CashFlow({ rows, filters }: { rows: CashFlowRow[]; filters: Record<string, string> }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const totalIn = rows.reduce((acc, r) => acc + Number(r.cash_in ?? 0), 0);
    const totalOut = rows.reduce((acc, r) => acc + Number(r.cash_out ?? 0), 0);
    const netFlow = totalIn - totalOut;

    function applyFilters() {
        router.get('/accounting/reports/cash-flow', { date_from: dateFrom, date_to: dateTo }, { preserveState: true });
    }

    return (
        <>
            <Head title="Cash Flow" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">Cash Flow</h1>
                        <p className="text-sm text-muted-foreground">Cash and bank movements for the selected period.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {['csv', 'xlsx', 'pdf'].map((format) => (
                            <Button key={format} asChild variant="outline">
                                <Link href={`/accounting/reports/cash-flow/export/${format}?date_from=${dateFrom}&date_to=${dateTo}`}>
                                    <Download className="size-4" />
                                    {format.toUpperCase()}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-lg border p-4">
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="date_from">From</Label>
                        <Input id="date_from" type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="w-40" />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="date_to">To</Label>
                        <Input id="date_to" type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="w-40" />
                    </div>
                    <Button onClick={applyFilters} variant="secondary">
                        <Filter className="size-4" />
                        Apply
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <Card className="rounded-lg">
                        <CardHeader className="pb-1"><CardTitle className="text-sm text-muted-foreground">Total Cash In</CardTitle></CardHeader>
                        <CardContent className="text-xl font-semibold tabular-nums text-green-600">{money(totalIn)}</CardContent>
                    </Card>
                    <Card className="rounded-lg">
                        <CardHeader className="pb-1"><CardTitle className="text-sm text-muted-foreground">Total Cash Out</CardTitle></CardHeader>
                        <CardContent className="text-xl font-semibold tabular-nums text-red-600">{money(totalOut)}</CardContent>
                    </Card>
                    <Card className="rounded-lg">
                        <CardHeader className="pb-1"><CardTitle className="text-sm text-muted-foreground">Net Cash Flow</CardTitle></CardHeader>
                        <CardContent className={`text-xl font-semibold tabular-nums ${netFlow >= 0 ? 'text-green-600' : 'text-red-600'}`}>{money(netFlow)}</CardContent>
                    </Card>
                </div>

                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full min-w-[900px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Date</th>
                                <th className="p-3 font-medium">Reference</th>
                                <th className="p-3 font-medium">Account</th>
                                <th className="p-3 font-medium">Description</th>
                                <th className="p-3 text-right font-medium text-green-700">Cash In</th>
                                <th className="p-3 text-right font-medium text-red-700">Cash Out</th>
                                <th className="p-3 text-right font-medium">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, i) => (
                                <tr key={i} className="border-t hover:bg-muted/30">
                                    <td className="p-3 tabular-nums">{row.entry_date}</td>
                                    <td className="p-3 font-medium">{row.reference}</td>
                                    <td className="p-3">{row.account_code} – {row.account_name}</td>
                                    <td className="p-3 text-muted-foreground">{row.journal_description}</td>
                                    <td className="p-3 text-right tabular-nums text-green-700">{Number(row.cash_in) > 0 ? money(row.cash_in) : '–'}</td>
                                    <td className="p-3 text-right tabular-nums text-red-700">{Number(row.cash_out) > 0 ? money(row.cash_out) : '–'}</td>
                                    <td className={`p-3 text-right tabular-nums font-medium ${Number(row.net_cash_flow) >= 0 ? 'text-green-600' : 'text-red-600'}`}>{money(row.net_cash_flow)}</td>
                                </tr>
                            )) : (
                                <tr><td className="p-6 text-center text-muted-foreground" colSpan={7}>No cash flow entries found for this period.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

CashFlow.layout = { breadcrumbs };
