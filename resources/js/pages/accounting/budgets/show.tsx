import { Head, Link, router, useForm } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Row = {
    account_id: number;
    account_code: string;
    account_name: string;
    type: 'INCOME' | 'EXPENSE';
    budget: string;
    actual: string;
    variance: string;
    used_percent: number | null;
    status: 'ok' | 'warning' | 'over' | 'behind' | 'unbudgeted';
    monthly: Array<{ month: string; budget: string; actual: string }>;
};
type Props = {
    budget: { id: number; name: string; status: 'draft' | 'approved' | 'closed'; start_date: string; end_date: string; notes: string | null };
    report: {
        date_from: string;
        date_to: string;
        rows: Row[];
        totals: Record<string, string>;
    };
    filters: { date_from: string | null; date_to: string | null; cost_center_id: string | number | null };
    costCenters: Array<{ id: number; code: string; name: string }>;
};

const money = (value: string) =>
    Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const style: Record<Row['status'], string> = {
    ok: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    over: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    behind: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    unbudgeted: 'bg-muted text-muted-foreground',
};
const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function BudgetShow({ budget, report, filters, costCenters }: Props) {
    const { permissions, flash } = useAccounting();
    const [open, setOpen] = useState<number | null>(null);
    const [range, setRange] = useState({
        date_from: filters.date_from ?? report.date_from,
        date_to: filters.date_to ?? report.date_to,
        cost_center_id: String(filters.cost_center_id ?? ''),
    });
    const copy = useForm({ name: `${budget.name} (copy)`, start_date: '', uplift_percent: '' });
    const act = (action: string) => router.post(`/accounting/budgets/${budget.id}/${action}`, {}, { preserveScroll: true });
    const query = new URLSearchParams(
        Object.entries(range).filter(([, value]) => value !== '') as Array<[string, string]>,
    ).toString();
    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get(`/accounting/budgets/${budget.id}`, Object.fromEntries(new URLSearchParams(query)), { preserveScroll: true });
    };

    return (
        <>
            <Head title={budget.name} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title={budget.name}
                        description={`${budget.start_date} → ${budget.end_date} · ${budget.status}${budget.notes ? ` · ${budget.notes}` : ''}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        {budget.status === 'draft' && permissions['budgets.update'] ? (
                            <Button asChild variant="outline">
                                <Link href={`/accounting/budgets/${budget.id}/edit`}>Edit</Link>
                            </Button>
                        ) : null}
                        {budget.status === 'draft' && permissions['budgets.approve'] ? (
                            <Button onClick={() => act('approve')}>Approve</Button>
                        ) : null}
                        {budget.status === 'approved' && permissions['budgets.approve'] ? (
                            <Button variant="outline" onClick={() => act('close')}>Close</Button>
                        ) : null}
                        {budget.status !== 'draft' && permissions['budgets.update'] ? (
                            <Button variant="outline" onClick={() => act('reopen')}>Reopen</Button>
                        ) : null}
                        {budget.status !== 'approved' && permissions['budgets.delete'] ? (
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    if (confirm('Delete this budget?')) router.delete(`/accounting/budgets/${budget.id}`);
                                }}
                            >
                                Delete
                            </Button>
                        ) : null}
                        {['csv', 'xlsx', 'pdf'].map((format) => (
                            <Button key={format} asChild variant="ghost" size="sm">
                                <a href={`/accounting/budgets/${budget.id}/export/${format}?${query}`}>{format.toUpperCase()}</a>
                            </Button>
                        ))}
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
                        <Label htmlFor="cost_center_id">Cost center</Label>
                        <select id="cost_center_id" className={selectClass} value={range.cost_center_id} onChange={(event) => setRange({ ...range, cost_center_id: event.target.value })}>
                            <option value="">All</option>
                            {costCenters.map((center) => (
                                <option key={center.id} value={center.id}>
                                    {center.code} - {center.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button type="submit" variant="outline">Apply</Button>
                </form>

                <div className="grid gap-3 md:grid-cols-3">
                    {[
                        ['Income', report.totals.income_budget, report.totals.income_actual],
                        ['Expenses', report.totals.expense_budget, report.totals.expense_actual],
                        ['Net', report.totals.net_budget, report.totals.net_actual],
                    ].map(([label, planned, actual]) => (
                        <div key={label} className="rounded-md border p-3">
                            <div className="text-xs uppercase text-muted-foreground">{label}</div>
                            <div className="mt-1 text-sm tabular-nums">
                                Budget {money(planned)} · Actual {money(actual)}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Account</th>
                                <th className="px-3 py-2 text-right font-medium">Budget</th>
                                <th className="px-3 py-2 text-right font-medium">Actual</th>
                                <th className="px-3 py-2 text-right font-medium">Variance</th>
                                <th className="px-3 py-2 text-right font-medium">Used</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">
                                        Nothing budgeted or posted in this range.
                                    </td>
                                </tr>
                            ) : null}
                            {report.rows.map((row) => (
                                <Fragment key={row.account_id}>
                                    <tr className="cursor-pointer border-t" onClick={() => setOpen(open === row.account_id ? null : row.account_id)}>
                                        <td className="px-3 py-2">
                                            {row.account_code} {row.account_name}
                                            <span className="ml-2 text-xs text-muted-foreground">{row.type.toLowerCase()}</span>
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">{money(row.budget)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{money(row.actual)}</td>
                                        <td className={`px-3 py-2 text-right tabular-nums ${Number(row.variance) < 0 ? 'text-red-700' : 'text-emerald-700'}`}>
                                            {money(row.variance)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">{row.used_percent === null ? '—' : `${row.used_percent}%`}</td>
                                        <td className="px-3 py-2">
                                            <Badge className={style[row.status]}>{row.status}</Badge>
                                        </td>
                                    </tr>
                                    {open === row.account_id ? (
                                        <tr className="border-t bg-muted/30">
                                            <td colSpan={6} className="px-3 py-2">
                                                <div className="flex flex-wrap gap-4 text-xs tabular-nums">
                                                    {row.monthly.map((month) => (
                                                        <div key={month.month}>
                                                            <div className="font-medium">{month.month}</div>
                                                            <div>B {money(month.budget)}</div>
                                                            <div>A {money(month.actual)}</div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </td>
                                        </tr>
                                    ) : null}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p className="text-xs text-muted-foreground">
                    Variance is favourable (green) when income is above plan or expenses below it. Click a row for the months.
                </p>

                {permissions['budgets.create'] ? (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            copy.post(`/accounting/budgets/${budget.id}/copy`);
                        }}
                        className="flex flex-wrap items-end gap-3 rounded-md border p-3"
                    >
                        <div className="grid gap-1">
                            <Label htmlFor="copy_name">Copy as</Label>
                            <Input id="copy_name" value={copy.data.name} onChange={(event) => copy.setData('name', event.target.value)} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="copy_start">Starting</Label>
                            <Input id="copy_start" type="date" value={copy.data.start_date} onChange={(event) => copy.setData('start_date', event.target.value)} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="copy_uplift">Change %</Label>
                            <Input id="copy_uplift" type="number" step="any" className="w-24" value={copy.data.uplift_percent} onChange={(event) => copy.setData('uplift_percent', event.target.value)} />
                        </div>
                        <Button type="submit" variant="outline" disabled={copy.processing}>Copy budget</Button>
                    </form>
                ) : null}
            </div>
        </>
    );
}

BudgetShow.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Budgets', href: '/accounting/budgets' },
    ],
};
