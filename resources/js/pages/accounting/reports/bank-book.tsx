import { Head, Link, router } from '@inertiajs/react';
import { Download, Filter } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type LedgerEntry = {
    entry_date: string;
    reference: string;
    journal_description: string;
    debit_amount: number | string;
    credit_amount: number | string;
    running_balance: number | string;
};

type Totals = Record<string, number>;
type PaginatedEntries = { data: LedgerEntry[]; current_page: number; last_page: number; total: number };

function money(value: number | string | null | undefined): string {
    return Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting' },
    { title: 'Bank Book', href: '/accounting/reports/bank-book' },
];

export default function BankBook({ entries, totals, filters }: { entries: PaginatedEntries; totals: Totals; filters: Record<string, string> }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    function applyFilters() {
        router.get('/accounting/reports/bank-book', { date_from: dateFrom, date_to: dateTo }, { preserveState: true });
    }

    return (
        <>
            <Head title="Bank Book" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">Bank Book</h1>
                        <p className="text-sm text-muted-foreground">All bank account transactions for the selected period.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {['csv', 'xlsx', 'pdf'].map((format) => (
                            <Button key={format} asChild variant="outline">
                                <Link href={`/accounting/reports/bank-book/export/${format}?date_from=${dateFrom}&date_to=${dateTo}`}>
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

                {Object.keys(totals).length > 0 && (
                    <div className="grid gap-3 sm:grid-cols-3">
                        {Object.entries(totals).map(([key, value]) => (
                            <Card key={key} className="rounded-lg">
                                <CardHeader className="pb-1"><CardTitle className="text-sm font-medium text-muted-foreground capitalize">{key.replace(/_/g, ' ')}</CardTitle></CardHeader>
                                <CardContent className="text-xl font-semibold tabular-nums">{money(value)}</CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full min-w-[800px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Date</th>
                                <th className="p-3 font-medium">Reference</th>
                                <th className="p-3 font-medium">Description</th>
                                <th className="p-3 text-right font-medium text-green-700">Debit</th>
                                <th className="p-3 text-right font-medium text-red-700">Credit</th>
                                <th className="p-3 text-right font-medium">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.data.length ? entries.data.map((row, i) => (
                                <tr key={i} className="border-t hover:bg-muted/30">
                                    <td className="p-3 tabular-nums">{row.entry_date}</td>
                                    <td className="p-3 font-medium">{row.reference}</td>
                                    <td className="p-3 text-muted-foreground">{row.journal_description}</td>
                                    <td className="p-3 text-right tabular-nums text-green-700">{Number(row.debit_amount) > 0 ? money(row.debit_amount) : '–'}</td>
                                    <td className="p-3 text-right tabular-nums text-red-700">{Number(row.credit_amount) > 0 ? money(row.credit_amount) : '–'}</td>
                                    <td className={`p-3 text-right tabular-nums font-medium ${Number(row.running_balance) >= 0 ? '' : 'text-red-600'}`}>{money(row.running_balance)}</td>
                                </tr>
                            )) : (
                                <tr><td className="p-6 text-center text-muted-foreground" colSpan={6}>No bank transactions found for this period.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {entries.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{entries.total} entries</span>
                        <span>Page {entries.current_page} of {entries.last_page}</span>
                    </div>
                )}
            </div>
        </>
    );
}

BankBook.layout = { breadcrumbs };
