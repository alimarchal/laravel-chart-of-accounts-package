import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FeatureLink } from '@/components/accounting/feature-link';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Row = { employee_id: number; code: string; name: string; overtime_eligible: boolean; absent_days: string; overtime_hours: string; holiday_overtime_hours: string; notes: string | null; unpaid_leave_days: number };
type Props = { rows: Row[]; month: string };

export default function PayrollAttendance({ rows, month }: Props) {
    const { permissions, flash } = useAccounting();
    const form = useForm({
        month: `${month}-01`,
        rows: rows.map((row) => ({ employee_id: row.employee_id, absent_days: row.absent_days, overtime_hours: row.overtime_hours, holiday_overtime_hours: row.holiday_overtime_hours, notes: row.notes ?? '' })),
    });
    const set = (index: number, key: 'absent_days' | 'overtime_hours' | 'holiday_overtime_hours' | 'notes', value: string) => form.setData('rows', form.data.rows.map((row, position) => (position === index ? { ...row, [key]: value } : row)));
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/accounting/payroll/attendance', { preserveScroll: true }); };
    const canManage = permissions['payroll.manage'];

    return (
        <>
            <Head title="Attendance" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Attendance" description="Absent days come off the salary by the day; overtime hours are paid to employees flagged for overtime" />
                    <div className="flex gap-2"><FeatureLink href="/accounting/payroll/leaves">Leave</FeatureLink><Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button></div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <div className="flex items-end gap-2">
                    <div className="space-y-1"><Label htmlFor="month">Month</Label><Input id="month" type="month" defaultValue={month} onChange={(event) => event.target.value && router.get('/accounting/payroll/attendance', { month: event.target.value })} /></div>
                </div>
                <form onSubmit={submit}>
                    <Card><CardContent className="overflow-x-auto pt-6">
                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th className="w-28">Absent days</th><th className="w-28">Unpaid leave</th><th className="w-28">Overtime h</th><th className="w-28">Holiday OT h</th><th>Notes</th></tr></thead><tbody>
                            {rows.map((row, index) => (
                                <tr key={row.employee_id} className="border-t">
                                    <td className="py-1">{row.code} {row.name}</td>
                                    <td><Input type="number" step="0.5" min="0" disabled={!canManage} value={form.data.rows[index].absent_days} onChange={(event) => set(index, 'absent_days', event.target.value)} /></td>
                                    <td className="text-muted-foreground">{row.unpaid_leave_days > 0 ? row.unpaid_leave_days : '—'}</td>
                                    <td><Input type="number" step="0.5" min="0" disabled={!canManage || !row.overtime_eligible} value={form.data.rows[index].overtime_hours} onChange={(event) => set(index, 'overtime_hours', event.target.value)} /></td>
                                    <td><Input type="number" step="0.5" min="0" disabled={!canManage || !row.overtime_eligible} value={form.data.rows[index].holiday_overtime_hours} onChange={(event) => set(index, 'holiday_overtime_hours', event.target.value)} /></td>
                                    <td><Input disabled={!canManage} value={form.data.rows[index].notes} onChange={(event) => set(index, 'notes', event.target.value)} /></td>
                                </tr>
                            ))}
                            {rows.length === 0 && <tr><td colSpan={6} className="py-4 text-center text-muted-foreground">Nobody was employed in this month.</td></tr>}
                        </tbody></table>
                        {canManage && rows.length > 0 && <div className="mt-4"><Button type="submit" disabled={form.processing}>Save attendance</Button></div>}
                    </CardContent></Card>
                </form>
            </div>
        </>
    );
}

PayrollAttendance.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Attendance', href: '/accounting/payroll/attendance' }] };
