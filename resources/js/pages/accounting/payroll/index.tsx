import { StatusPill } from '@/components/accounting/status-pill';
import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FilterBar from '@/components/accounting/filter-bar';
import { PayrollHeader } from '@/components/accounting/payroll-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Run = { id: number; period_month: string; status: string; gross: string; deductions: string; tax: string; net: string; employees: number };
type Props = { filters: Record<string, string | null>; runs: Run[]; summary: Array<{ month: string; gross: string; tax: string; net: string }>; year: number; defaultMonth: string };

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PayrollIndex({ runs, summary, year, defaultMonth, filters }: Props) {
    const { permissions, flash } = useAccounting();
    const form = useForm({ period_month: defaultMonth, notes: '' });
    const create = (event: FormEvent) => {
        event.preventDefault();
        form.post('/accounting/payroll/runs');
    };
    const total = (key: 'gross' | 'tax' | 'net') => summary.reduce((sum, row) => sum + Number(row[key]), 0).toFixed(2);

    return (
        <>
            <Head title="Payroll" />
            <div className="space-y-6 p-4">
                <PayrollHeader title="Payroll" description="Monthly salary runs, from calculation to payment" />
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <div className="grid gap-4 sm:grid-cols-3">
                    {([['Gross ' + year, total('gross')], ['Tax withheld ' + year, total('tax')], ['Net paid ' + year, total('net')]] as const).map(([label, value]) => (
                        <Card key={label}><CardContent className="pt-6"><p className="text-sm text-muted-foreground">{label}</p><p className="mt-1 text-2xl font-semibold tabular-nums">{money(value)}</p></CardContent></Card>
                    ))}
                </div>
                {permissions['payroll.run'] && (
                    <form onSubmit={create}>
                        <Card>
                            <CardHeader><CardTitle>New payroll run</CardTitle></CardHeader>
                            <CardContent className="flex flex-wrap items-end gap-3">
                                <div className="space-y-1"><Label htmlFor="period_month">Month</Label><Input id="period_month" type="date" value={form.data.period_month} onChange={(event) => form.setData('period_month', event.target.value)} /><InputError message={form.errors.period_month} /></div>
                                <div className="space-y-1"><Label htmlFor="notes">Notes</Label><Input id="notes" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div>
                                <Button type="submit" disabled={form.processing}>Calculate</Button>
                            </CardContent>
                        </Card>
                    </form>
                )}
                <FilterBar
                    path="/accounting/payroll"
                    values={filters}
                    fields={[
                        { name: 'search', label: 'Search', placeholder: 'Notes' },
                        { name: 'status', label: 'Status', type: 'select', options: { draft: 'Draft', submitted: 'Submitted', approved: 'Approved', posted: 'Posted', paid: 'Paid', void: 'Void' } },
                        { name: 'period_from', label: 'Month from', type: 'date' },
                        { name: 'period_to', label: 'Month to', type: 'date' },
                    ]}
                />
                <Card>
                <CardHeader><CardTitle>Runs</CardTitle></CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2">
                            <thead><tr className="text-left text-muted-foreground"><th className="py-1">Month</th><th>Status</th><th className="text-right">Employees</th><th className="text-right">Gross</th><th className="text-right">Deductions</th><th className="text-right">Tax</th><th className="text-right">Net</th></tr></thead>
                            <tbody>
                                {runs.map((run) => (
                                    <tr key={run.id} className="cursor-pointer border-t" onClick={() => router.visit(`/accounting/payroll/runs/${run.id}`)}>
                                        <td className="py-2"><Link href={`/accounting/payroll/runs/${run.id}`} className="font-medium hover:underline">{run.period_month}</Link></td>
                                        <td><StatusPill status={run.status} /></td><td className="text-right">{run.employees}</td>
                                        <td className="text-right tabular-nums">{money(run.gross)}</td><td className="text-right tabular-nums">{money(run.deductions)}</td><td className="text-right tabular-nums">{money(run.tax)}</td><td className="text-right tabular-nums">{money(run.net)}</td>
                                    </tr>
                                ))}
                                {runs.length === 0 && <tr><td colSpan={7} className="py-6 text-center text-muted-foreground">No payroll runs yet.</td></tr>}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PayrollIndex.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }] };
