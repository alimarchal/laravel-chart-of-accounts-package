import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Period = { income: string; expense: string; net: string };
type Overview = {
    as_of: string;
    months: number;
    performance: {
        this_month: Period;
        last_month: Period;
        year_to_date: Period;
        trend: Array<{ month: string; income: string; expense: string }>;
        top_expenses: Array<{ label: string; amount: string }>;
    } | null;
    cash: { total: string; accounts: Array<{ bank_account_id: number; name: string; balance: string }> } | null;
    receivables: Side | null;
    payables: Side | null;
    alerts: Array<{ key: string; level: string; count: number; label: string; amount: string | null }>;
    recent_entries: Array<{ id: number; voucher_number: string | null; entry_date: string; description: string | null; status: string; amount: string }> | null;
};
type Side = {
    total: string;
    overdue: string;
    buckets: Array<{ bucket: string; amount: string }>;
    top: Array<{ party_id: number; name: string; total: string }>;
    difference: string;
};

const money = (value: string | number) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const compact = (value: number) => Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(value);
const bucketLabels: Record<string, string> = { not_due: 'Not due', days_1_30: '1–30', days_31_60: '31–60', days_61_90: '61–90', over_90: '90+' };
const alertLinks: Record<string, string> = {
    pending_approval: '/accounting/journal-entries?filter[approval_status]=pending',
    drafts: '/accounting/journal-entries?filter[status]=draft',
    overdue_receivables: '/accounting/receivables/aging',
    unmatched_bank_lines: '/accounting/bank-statements',
    budget_over: '/accounting/budgets',
    budget_warning: '/accounting/budgets',
    tax_unfiled: '/accounting/tax',
};
const levelIcon: Record<string, string> = { critical: '▲', warning: '●', info: '○' };

// Series colours come from CSS custom properties so light and dark each use their own validated step.
const vizStyle = `
.viz-root{--viz-1:#2a78d6;--viz-2:#eb6834;--viz-grid:rgba(0,0,0,.08);--viz-axis:rgba(0,0,0,.45)}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]) .viz-root{--viz-1:#3987e5;--viz-2:#d95926;--viz-grid:rgba(255,255,255,.1);--viz-axis:rgba(255,255,255,.55)}}
:root[data-theme="dark"] .viz-root{--viz-1:#3987e5;--viz-2:#d95926;--viz-grid:rgba(255,255,255,.1);--viz-axis:rgba(255,255,255,.55)}
.dark .viz-root{--viz-1:#3987e5;--viz-2:#d95926;--viz-grid:rgba(255,255,255,.1);--viz-axis:rgba(255,255,255,.55)}
`;

function Kpi({ label, value, hint, tone }: { label: string; value: string; hint?: string; tone?: 'bad' }) {
    return (
        <Card>
            <CardContent className="pt-6">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className={`mt-1 text-2xl font-semibold tabular-nums ${tone === 'bad' && Number(value) > 0 ? 'text-destructive' : ''}`}>{money(value)}</p>
                {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
            </CardContent>
        </Card>
    );
}

function TrendChart({ trend }: { trend: Array<{ month: string; income: string; expense: string }> }) {
    const [hover, setHover] = useState<number | null>(null);
    const [table, setTable] = useState(false);
    const width = 640;
    const height = 220;
    const left = 48;
    const bottom = 24;
    const top = 8;
    const max = Math.max(1, ...trend.flatMap((row) => [Number(row.income), Number(row.expense)]));
    const step = (width - left) / trend.length;
    const barWidth = Math.min(24, (step - 12) / 2);
    const y = (value: number) => top + (height - top - bottom) * (1 - Math.max(0, value) / max);
    const ticks = [0, 0.5, 1].map((fraction) => max * fraction);

    return (
        <div className="viz-root">
            <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-4 text-sm">
                    <span className="inline-flex items-center gap-1.5"><span className="inline-block size-2.5 rounded-sm" style={{ background: 'var(--viz-1)' }} />Income</span>
                    <span className="inline-flex items-center gap-1.5"><span className="inline-block size-2.5 rounded-sm" style={{ background: 'var(--viz-2)' }} />Expense</span>
                </div>
                <Button variant="ghost" size="sm" onClick={() => setTable(!table)}>{table ? 'Show chart' : 'Show table'}</Button>
            </div>
            {table ? (
                <table className="w-full text-sm">
                    <thead><tr className="text-left text-muted-foreground"><th className="py-1">Month</th><th className="py-1 text-right">Income</th><th className="py-1 text-right">Expense</th></tr></thead>
                    <tbody>{trend.map((row) => (<tr key={row.month} className="border-t"><td className="py-1">{row.month}</td><td className="py-1 text-right tabular-nums">{money(row.income)}</td><td className="py-1 text-right tabular-nums">{money(row.expense)}</td></tr>))}</tbody>
                </table>
            ) : (
                <div className="relative">
                    <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Income and expense by month" className="w-full">
                        {ticks.map((tick) => (
                            <g key={tick}>
                                <line x1={left} x2={width} y1={y(tick)} y2={y(tick)} stroke="var(--viz-grid)" strokeWidth={1} />
                                <text x={left - 6} y={y(tick) + 4} textAnchor="end" fontSize={11} fill="var(--viz-axis)">{compact(tick)}</text>
                            </g>
                        ))}
                        {trend.map((row, index) => {
                            const x = left + index * step + (step - (barWidth * 2 + 2)) / 2;
                            const income = Number(row.income);
                            const expense = Number(row.expense);
                            return (
                                <g key={row.month} onMouseEnter={() => setHover(index)} onMouseLeave={() => setHover(null)}>
                                    <rect x={left + index * step} y={top} width={step} height={height - top - bottom} fill="transparent" />
                                    <rect x={x} y={y(income)} width={barWidth} height={Math.max(0, height - bottom - y(income))} rx={4} fill="var(--viz-1)" />
                                    <rect x={x + barWidth + 2} y={y(expense)} width={barWidth} height={Math.max(0, height - bottom - y(expense))} rx={4} fill="var(--viz-2)" />
                                    <text x={left + index * step + step / 2} y={height - 6} textAnchor="middle" fontSize={11} fill="var(--viz-axis)">{row.month.slice(2)}</text>
                                </g>
                            );
                        })}
                    </svg>
                    {hover !== null && (
                        <div className="pointer-events-none absolute right-2 top-2 rounded-md border bg-popover px-3 py-2 text-xs shadow">
                            <p className="font-medium">{trend[hover].month}</p>
                            <p>Income <span className="tabular-nums">{money(trend[hover].income)}</span></p>
                            <p>Expense <span className="tabular-nums">{money(trend[hover].expense)}</span></p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function AgingBars({ side }: { side: Side }) {
    const max = Math.max(1, ...side.buckets.map((bucket) => Number(bucket.amount)));
    return (
        <div className="viz-root space-y-2">
            {side.buckets.map((bucket) => (
                <div key={bucket.bucket} className="flex items-center gap-3 text-sm">
                    <span className="w-14 text-muted-foreground">{bucketLabels[bucket.bucket]}</span>
                    <div className="h-4 flex-1">
                        <div className="h-4 rounded-r-[4px]" style={{ width: `${(Math.max(0, Number(bucket.amount)) / max) * 100}%`, background: 'var(--viz-1)' }} title={money(bucket.amount)} />
                    </div>
                    <span className="w-28 text-right tabular-nums">{money(bucket.amount)}</span>
                </div>
            ))}
        </div>
    );
}

export default function AccountingOverview({ overview }: { overview: Overview }) {
    const [asOf, setAsOf] = useState(overview.as_of);
    const { performance, cash, receivables, payables, alerts, recent_entries: recent } = overview;

    return (
        <>
            <Head title="Overview" />
            <style>{vizStyle}</style>
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Overview" description={`Position as of ${overview.as_of}`} />
                    <form className="flex items-center gap-2" onSubmit={(event) => { event.preventDefault(); router.get('/accounting/overview', { as_of: asOf }, { preserveState: true }); }}>
                        <Input type="date" value={asOf} onChange={(event) => setAsOf(event.target.value)} className="w-40" />
                        <Button type="submit" variant="outline">Update</Button>
                        <Button asChild variant="ghost"><Link href="/accounting">All modules</Link></Button>
                    </form>
                </div>

                {alerts.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle>Needs attention</CardTitle></CardHeader>
                        <CardContent className="space-y-2">
                            {alerts.map((alert) => (
                                <Link key={alert.key} href={alertLinks[alert.key] ?? '/accounting'} className="flex items-center justify-between rounded-md border px-3 py-2 text-sm hover:bg-muted">
                                    <span><span aria-hidden className="mr-2">{levelIcon[alert.level]}</span><span className="sr-only">{alert.level}: </span>{alert.label}</span>
                                    <span className="tabular-nums">{alert.amount !== null ? money(alert.amount) : alert.count}</span>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {cash && <Kpi label="Cash and bank" value={cash.total} hint={`${cash.accounts.length} bank account${cash.accounts.length === 1 ? '' : 's'}`} />}
                    {receivables && <Kpi label="Receivable" value={receivables.total} hint={`${money(receivables.overdue)} overdue`} />}
                    {payables && <Kpi label="Payable" value={payables.total} hint={`${money(payables.overdue)} overdue`} />}
                    {performance && <Kpi label="Net this month" value={performance.this_month.net} hint={`Last month ${money(performance.last_month.net)}`} />}
                </div>

                {performance && (
                    <div className="grid gap-4 lg:grid-cols-3">
                        <Card className="lg:col-span-2">
                            <CardHeader><CardTitle>Income and expense</CardTitle><CardDescription>Last {overview.months} months, closing entries excluded</CardDescription></CardHeader>
                            <CardContent><TrendChart trend={performance.trend} /></CardContent>
                        </Card>
                        <Card>
                            <CardHeader><CardTitle>Year to date</CardTitle></CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div className="flex justify-between"><span>Income</span><span className="tabular-nums">{money(performance.year_to_date.income)}</span></div>
                                <div className="flex justify-between"><span>Expense</span><span className="tabular-nums">{money(performance.year_to_date.expense)}</span></div>
                                <div className="flex justify-between border-t pt-2 font-medium"><span>Net</span><span className="tabular-nums">{money(performance.year_to_date.net)}</span></div>
                                {performance.top_expenses.length > 0 && <p className="pt-3 text-xs font-medium text-muted-foreground">Top expenses this month</p>}
                                {performance.top_expenses.map((row) => (<div key={row.label} className="flex justify-between"><span className="truncate pr-2">{row.label}</span><span className="tabular-nums">{money(row.amount)}</span></div>))}
                            </CardContent>
                        </Card>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    {([['Receivables ageing', receivables], ['Payables ageing', payables]] as const).map(([title, side]) =>
                        side ? (
                            <Card key={title}>
                                <CardHeader><CardTitle>{title}</CardTitle><CardDescription>Ledger vs sub-ledger difference {money(side.difference)}</CardDescription></CardHeader>
                                <CardContent className="space-y-4">
                                    <AgingBars side={side} />
                                    {side.top.map((row) => (<div key={row.party_id} className="flex justify-between text-sm"><Link href={`/accounting/parties/${row.party_id}`} className="hover:underline">{row.name}</Link><span className="tabular-nums">{money(row.total)}</span></div>))}
                                </CardContent>
                            </Card>
                        ) : null,
                    )}
                </div>

                {cash && cash.accounts.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle>Bank balances</CardTitle></CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            {cash.accounts.map((account) => (<div key={account.bank_account_id} className="flex justify-between"><span>{account.name}</span><span className="tabular-nums">{money(account.balance)}</span></div>))}
                        </CardContent>
                    </Card>
                )}

                {recent && (
                    <Card>
                        <CardHeader><CardTitle>Recent journal entries</CardTitle></CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <tbody>
                                    {recent.map((entry) => (
                                        <tr key={entry.id} className="border-t first:border-0">
                                            <td className="py-2"><Link href={`/accounting/journal-entries/${entry.id}`} className="hover:underline">{entry.voucher_number ?? `#${entry.id}`}</Link></td>
                                            <td className="py-2 text-muted-foreground">{entry.entry_date}</td>
                                            <td className="py-2">{entry.description}</td>
                                            <td className="py-2"><Badge variant="outline">{entry.status}</Badge></td>
                                            <td className="py-2 text-right tabular-nums">{money(entry.amount)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

AccountingOverview.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Overview', href: '/accounting/overview' },
    ],
};
