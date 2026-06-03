import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type TrialBalanceRow = {
    account_code: string;
    account_name: string;
    account_type: string;
    normal_balance: 'debit' | 'credit';
    report_group: string;
    total_debits: number | string;
    total_credits: number | string;
    balance: number | string;
};

function money(value: number | string | null | undefined): string {
    return Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting' },
    { title: 'Trial Balance', href: '/accounting/reports/trial-balance' },
];

export default function TrialBalance({ rows, totals }: { rows: TrialBalanceRow[]; totals: Record<string, number> }) {
    return (
        <>
            <Head title="Trial Balance" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">Trial Balance</h1>
                        <p className="text-sm text-muted-foreground">Debit and credit totals from posted ledger activity.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {['csv', 'xlsx', 'pdf'].map((format) => (
                            <Button key={format} asChild variant="outline">
                                <Link href={`/accounting/reports/trial-balance/export/${format}`}>
                                    <Download className="size-4" />
                                    {format.toUpperCase()}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-3">
                    <Card className="rounded-lg"><CardHeader><CardTitle className="text-sm">Total Debit</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{money(totals.total_debit)}</CardContent></Card>
                    <Card className="rounded-lg"><CardHeader><CardTitle className="text-sm">Total Credit</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{money(totals.total_credit)}</CardContent></Card>
                    <Card className="rounded-lg"><CardHeader><CardTitle className="text-sm">Difference</CardTitle></CardHeader><CardContent className="flex items-center gap-2 text-2xl font-semibold">{money(totals.difference)} {Number(totals.difference) === 0 ? <Badge variant="secondary">Balanced</Badge> : <Badge variant="destructive">Review</Badge>}</CardContent></Card>
                </div>

                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full min-w-[920px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Code</th>
                                <th className="p-3 font-medium">Account</th>
                                <th className="p-3 font-medium">Type</th>
                                <th className="p-3 font-medium">Normal</th>
                                <th className="p-3 text-right font-medium">Debit</th>
                                <th className="p-3 text-right font-medium">Credit</th>
                                <th className="p-3 text-right font-medium">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row) => (
                                <tr key={row.account_code} className="border-t">
                                    <td className="p-3 font-medium">{row.account_code}</td>
                                    <td className="p-3">{row.account_name}</td>
                                    <td className="p-3">{row.account_type}</td>
                                    <td className="p-3 capitalize">{row.normal_balance}</td>
                                    <td className="p-3 text-right tabular-nums">{money(row.total_debits)}</td>
                                    <td className="p-3 text-right tabular-nums">{money(row.total_credits)}</td>
                                    <td className="p-3 text-right tabular-nums">{money(row.balance)}</td>
                                </tr>
                            )) : (
                                <tr><td className="p-6 text-center text-muted-foreground" colSpan={7}>No ledger rows found.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

TrialBalance.layout = {
    breadcrumbs,
};
