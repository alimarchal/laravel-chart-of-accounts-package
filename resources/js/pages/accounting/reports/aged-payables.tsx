import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type AgedRow = {
    account_code: string;
    account_name: string;
    balance: number | string;
    current_balance: number | string;
    days_31_60: number | string;
    days_61_90: number | string;
    over_90: number | string;
};

function money(value: number | string | null | undefined): string {
    return Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function sum(rows: AgedRow[], key: keyof AgedRow): number {
    return rows.reduce((acc, row) => acc + Number(row[key] ?? 0), 0);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting' },
    { title: 'Aged Payables', href: '/accounting/reports/aged-payables' },
];

export default function AgedPayables({ rows }: { rows: AgedRow[] }) {
    return (
        <>
            <Head title="Aged Payables" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">Aged Payables</h1>
                        <p className="text-sm text-muted-foreground">Outstanding amounts owed to suppliers, grouped by age.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {['csv', 'xlsx', 'pdf'].map((format) => (
                            <Button key={format} asChild variant="outline">
                                <Link href={`/accounting/reports/aged-payables/export/${format}`}>
                                    <Download className="size-4" />
                                    {format.toUpperCase()}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        { label: '0–30 Days', value: sum(rows, 'current_balance'), color: 'text-green-600' },
                        { label: '31–60 Days', value: sum(rows, 'days_31_60'), color: 'text-yellow-600' },
                        { label: '61–90 Days', value: sum(rows, 'days_61_90'), color: 'text-orange-600' },
                        { label: 'Over 90 Days', value: sum(rows, 'over_90'), color: 'text-red-600' },
                    ].map(({ label, value, color }) => (
                        <Card key={label} className="rounded-lg">
                            <CardHeader className="pb-1"><CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle></CardHeader>
                            <CardContent className={`text-xl font-semibold tabular-nums ${color}`}>{money(value)}</CardContent>
                        </Card>
                    ))}
                </div>

                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full min-w-[900px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Code</th>
                                <th className="p-3 font-medium">Account</th>
                                <th className="p-3 text-right font-medium">Total Balance</th>
                                <th className="p-3 text-right font-medium text-green-700">0–30 Days</th>
                                <th className="p-3 text-right font-medium text-yellow-700">31–60 Days</th>
                                <th className="p-3 text-right font-medium text-orange-700">61–90 Days</th>
                                <th className="p-3 text-right font-medium text-red-700">Over 90</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row) => (
                                <tr key={row.account_code} className="border-t hover:bg-muted/30">
                                    <td className="p-3 font-medium">{row.account_code}</td>
                                    <td className="p-3">{row.account_name}</td>
                                    <td className="p-3 text-right font-semibold tabular-nums">{money(row.balance)}</td>
                                    <td className="p-3 text-right tabular-nums text-green-700">{money(row.current_balance)}</td>
                                    <td className="p-3 text-right tabular-nums text-yellow-700">{money(row.days_31_60)}</td>
                                    <td className="p-3 text-right tabular-nums text-orange-700">{money(row.days_61_90)}</td>
                                    <td className="p-3 text-right tabular-nums text-red-700">{money(row.over_90)}</td>
                                </tr>
                            )) : (
                                <tr><td className="p-6 text-center text-muted-foreground" colSpan={7}>No outstanding payables found.</td></tr>
                            )}
                        </tbody>
                        {rows.length > 0 && (
                            <tfoot className="border-t bg-muted/50 font-semibold">
                                <tr>
                                    <td className="p-3" colSpan={2}>Total</td>
                                    <td className="p-3 text-right tabular-nums">{money(sum(rows, 'balance'))}</td>
                                    <td className="p-3 text-right tabular-nums text-green-700">{money(sum(rows, 'current_balance'))}</td>
                                    <td className="p-3 text-right tabular-nums text-yellow-700">{money(sum(rows, 'days_31_60'))}</td>
                                    <td className="p-3 text-right tabular-nums text-orange-700">{money(sum(rows, 'days_61_90'))}</td>
                                    <td className="p-3 text-right tabular-nums text-red-700">{money(sum(rows, 'over_90'))}</td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
        </>
    );
}

AgedPayables.layout = { breadcrumbs };
