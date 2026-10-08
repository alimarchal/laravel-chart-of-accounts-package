import { Head, Link, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Month = { month: string; paid: string; due: string; difference: string };
type Row = { id: number; employee_code: string; employee_name: string; from_month: string; to_month: string; payment_month: string; amount: string; status: string; months: Month[] };
type Preview = { employee_id: number; code: string; name: string; from_month: string; to_month: string; amount: string; months: Array<{ month: string; paid: number; due: number; difference: number }> };
type Props = {
    rows: Row[];
    totals: { count: number; amount: string; by_status: Record<string, string>; by_payment_month: Record<string, string> };
    filters: { status?: string; payment_month?: string };
    employees: Array<{ id: number; code: string; name: string }>;
    grades: Array<{ id: number; code: string; name: string }>;
    preview: Preview[] | null;
    defaults: { from_month: string; payment_month: string };
};

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const money = (value: string | number) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const cents = (value: number) => (value / 100).toFixed(2);

export default function PayrollArrears({ rows, totals, filters, employees, grades, preview, defaults }: Props) {
    const { permissions, flash } = useAccounting();
    const form = useForm({ from_month: defaults.from_month, payment_month: defaults.payment_month, notes: '', salary_grade_id: '', employee_ids: [] as number[] });
    const toggle = (id: number, on: boolean) => form.setData('employee_ids', on ? [...form.data.employee_ids, id] : form.data.employee_ids.filter((item) => item !== id));
    const badge = (status: string) => <Badge variant={status === 'approved' || status === 'included' ? 'default' : 'outline'}>{status}</Badge>;

    return (
        <>
            <Head title="Arrears" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Arrears" description="Back pay of a raise: worked out from the payslips already posted, approved, and paid with a payroll run" />
                    <div className="flex gap-2"><Button asChild variant="outline"><Link href="/accounting/payroll/bulk">Bulk changes</Link></Button><Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button></div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Arrears (not cancelled)</p><p className="mt-1 text-2xl font-semibold tabular-nums">{money(totals.amount)}</p><p className="text-xs text-muted-foreground">{totals.count} records</p></CardContent></Card>
                    {Object.entries(totals.by_status).filter(([status]) => status !== 'cancelled').map(([status, amount]) => (<Card key={status}><CardContent className="pt-6"><p className="text-sm text-muted-foreground">{status}</p><p className="mt-1 text-2xl font-semibold tabular-nums">{money(amount)}</p></CardContent></Card>))}
                </div>
                {permissions['payroll.run'] && (
                    <Card>
                        <CardHeader><CardTitle>Work out arrears</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-4">
                            <div className="space-y-1"><Label htmlFor="from_month">First month covered</Label><Input id="from_month" type="date" value={form.data.from_month} onChange={(event) => form.setData('from_month', event.target.value)} /><InputError message={form.errors.from_month} /></div>
                            <div className="space-y-1"><Label htmlFor="payment_month">Paid with the payroll of</Label><Input id="payment_month" type="date" value={form.data.payment_month} onChange={(event) => form.setData('payment_month', event.target.value)} /><InputError message={form.errors.payment_month} /></div>
                            <div className="space-y-1"><Label>Grade (when nobody is picked)</Label><select className={selectClass} value={form.data.salary_grade_id} onChange={(event) => form.setData('salary_grade_id', event.target.value)}><option value="">Every active employee</option>{grades.map((grade) => (<option key={grade.id} value={grade.id}>{grade.code} {grade.name}</option>))}</select></div>
                            <div className="space-y-1"><Label htmlFor="notes">Notes</Label><Input id="notes" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div>
                            <details className="rounded-md border p-3 text-sm md:col-span-4">
                                <summary className="cursor-pointer">Pick employees ({form.data.employee_ids.length} picked)</summary>
                                <div className="mt-2 flex gap-2"><Button type="button" size="sm" variant="outline" onClick={() => form.setData('employee_ids', employees.map((employee) => employee.id))}>All</Button><Button type="button" size="sm" variant="outline" onClick={() => form.setData('employee_ids', [])}>None</Button></div>
                                <div className="mt-2 grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">{employees.map((employee) => (<label key={employee.id} className="flex items-center gap-2"><input type="checkbox" checked={form.data.employee_ids.includes(employee.id)} onChange={(event) => toggle(employee.id, event.target.checked)} />{employee.code} {employee.name}</label>))}</div>
                            </details>
                            <div className="flex gap-2 md:col-span-4">
                                <Button type="button" variant="outline" disabled={form.processing} onClick={() => form.post('/accounting/payroll/arrears/preview', { preserveScroll: true })}>Preview</Button>
                                <Button type="button" disabled={form.processing || !preview || preview.length === 0} onClick={() => form.post('/accounting/payroll/arrears')}>Create for approval</Button>
                            </div>
                            {preview && (
                                <div className="overflow-x-auto md:col-span-4">
                                    <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th>From</th><th>To</th><th>Months</th><th className="text-right">Arrears</th></tr></thead><tbody>
                                        {preview.map((row) => (
                                            <tr key={row.employee_id} className="border-t align-top"><td className="py-1">{row.code} {row.name}</td><td>{row.from_month}</td><td>{row.to_month}</td>
                                                <td className="text-xs text-muted-foreground">{row.months.map((month) => `${month.month}: ${cents(month.paid)} → ${cents(month.due)}`).join(' · ')}</td><td className="text-right tabular-nums">{money(row.amount)}</td></tr>
                                        ))}
                                        {preview.length === 0 && <tr><td colSpan={5} className="py-3 text-center text-muted-foreground">No arrears are due for those months.</td></tr>}
                                    </tbody></table>
                                    <p className="mt-2 text-xs text-muted-foreground">Only months with a posted or paid payslip count; months already claimed are skipped.</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardHeader className="flex-row items-center justify-between"><CardTitle>Register</CardTitle>
                        {permissions['payroll.post'] && <Button variant="outline" size="sm" onClick={() => confirm('Approve every draft?') && router.post('/accounting/payroll/arrears/approve-all')}>Approve all drafts</Button>}</CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th>Covers</th><th>Paid with</th><th>Status</th><th className="text-right">Amount</th><th /></tr></thead><tbody>
                            {rows.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="py-2">{row.employee_code} {row.employee_name}</td><td>{row.from_month}{row.to_month !== row.from_month ? ` – ${row.to_month}` : ''}</td><td>{row.payment_month}</td><td>{badge(row.status)}</td><td className="text-right tabular-nums">{money(row.amount)}</td>
                                    <td className="text-right">{permissions['payroll.post'] && <>
                                        {row.status === 'draft' && <Button variant="ghost" size="sm" onClick={() => router.post(`/accounting/payroll/arrears/${row.id}/approve`)}>Approve</Button>}
                                        {(row.status === 'draft' || row.status === 'approved') && <Button variant="ghost" size="sm" onClick={() => confirm('Cancel these arrears?') && router.post(`/accounting/payroll/arrears/${row.id}/cancel`)}>Cancel</Button>}</>}</td>
                                </tr>
                            ))}
                            {rows.length === 0 && <tr><td colSpan={6} className="py-4 text-center text-muted-foreground">No arrears yet{filters.status ? ` with status ${filters.status}` : ''}.</td></tr>}
                        </tbody></table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PayrollArrears.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Arrears', href: '/accounting/payroll/arrears' }] };
