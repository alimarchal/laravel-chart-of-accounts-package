import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type LeaveRow = { id: number; employee_code: string; employee_name: string; leave_type: string; is_paid: boolean; from_date: string; to_date: string; days: string; status: string; notes: string | null };
type LeaveType = { id: number; code: string; name: string; is_paid: boolean; annual_days: string; is_active: boolean };
type Balance = { employee_id: number; employee_code: string; employee_name: string; leave_type_id: number; leave_type: string; entitlement: number; taken: number; balance: number };
type Props = { leaves: LeaveRow[]; types: LeaveType[]; employees: Array<{ id: number; code: string; name: string }>; balances: Balance[]; year: number; today: string };

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function PayrollLeaves({ leaves, types, employees, balances, year, today }: Props) {
    const { permissions, flash } = useAccounting();
    const leave = useForm({ employee_id: '', leave_type_id: '', from_date: today, to_date: today, days: '', notes: '' });
    const type = useForm({ code: '', name: '', is_paid: true, annual_days: '' });
    const addLeave = (event: FormEvent) => { event.preventDefault(); leave.post('/accounting/payroll/leaves', { onSuccess: () => leave.reset('days', 'notes') }); };
    const addType = (event: FormEvent) => { event.preventDefault(); type.post('/accounting/payroll/leave-types', { onSuccess: () => type.reset() }); };
    const canManage = permissions['payroll.manage'];

    return (
        <>
            <Head title="Leave" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Leave" description="Leave taken and yearly balances; unpaid leave comes off the salary by the day" />
                    <div className="flex gap-2"><Button asChild variant="outline"><Link href="/accounting/payroll/attendance">Attendance</Link></Button><Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button></div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                {canManage && (
                    <form onSubmit={addLeave}><Card>
                        <CardHeader><CardTitle>Record leave</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-4">
                            <div className="space-y-1"><Label>Employee</Label><select className={selectClass} value={leave.data.employee_id} onChange={(event) => leave.setData('employee_id', event.target.value)}><option value="">Choose…</option>{employees.map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name}</option>))}</select><InputError message={leave.errors.employee_id} /></div>
                            <div className="space-y-1"><Label>Leave type</Label><select className={selectClass} value={leave.data.leave_type_id} onChange={(event) => leave.setData('leave_type_id', event.target.value)}><option value="">Choose…</option>{types.filter((row) => row.is_active).map((row) => (<option key={row.id} value={row.id}>{row.name}{row.is_paid ? '' : ' (unpaid)'}</option>))}</select><InputError message={leave.errors.leave_type_id} /></div>
                            <div className="space-y-1"><Label>From</Label><Input type="date" value={leave.data.from_date} onChange={(event) => leave.setData('from_date', event.target.value)} /><InputError message={leave.errors.from_date} /></div>
                            <div className="space-y-1"><Label>To</Label><Input type="date" value={leave.data.to_date} onChange={(event) => leave.setData('to_date', event.target.value)} /><InputError message={leave.errors.to_date} /></div>
                            <div className="space-y-1"><Label>Days (blank = all the dates; 0.5 = half day)</Label><Input type="number" step="0.5" value={leave.data.days} onChange={(event) => leave.setData('days', event.target.value)} /><InputError message={leave.errors.days} /></div>
                            <div className="space-y-1 md:col-span-2"><Label>Notes</Label><Input value={leave.data.notes} onChange={(event) => leave.setData('notes', event.target.value)} /></div>
                            <div className="pt-6"><Button type="submit" disabled={leave.processing}>Record</Button></div>
                        </CardContent>
                    </Card></form>
                )}
                <Card>
                    <CardHeader><CardTitle>Leaves</CardTitle></CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th>Type</th><th>From</th><th>To</th><th className="text-right">Days</th><th>Status</th><th /></tr></thead><tbody>
                            {leaves.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="py-2">{row.employee_code} {row.employee_name}</td><td>{row.leave_type}{row.is_paid ? '' : ' (unpaid)'}</td><td>{row.from_date}</td><td>{row.to_date}</td><td className="text-right tabular-nums">{Number(row.days)}</td><td><Badge variant="outline">{row.status}</Badge></td>
                                    <td className="text-right">{canManage && row.status === 'approved' && <Button variant="ghost" size="sm" onClick={() => confirm('Cancel this leave?') && router.post(`/accounting/payroll/leaves/${row.id}/cancel`)}>Cancel</Button>}</td>
                                </tr>
                            ))}
                            {leaves.length === 0 && <tr><td colSpan={7} className="py-4 text-center text-muted-foreground">No leave recorded yet.</td></tr>}
                        </tbody></table>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-center justify-between"><CardTitle>Balances {year}</CardTitle>
                        <div className="flex items-center gap-2"><Button variant="outline" size="sm" onClick={() => router.get('/accounting/payroll/leaves', { year: year - 1 })}>{year - 1}</Button><Button variant="outline" size="sm" onClick={() => router.get('/accounting/payroll/leaves', { year: year + 1 })}>{year + 1}</Button></div></CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th>Type</th><th className="text-right">Entitled</th><th className="text-right">Taken</th><th className="text-right">Balance</th></tr></thead><tbody>
                            {balances.filter((row) => row.entitlement > 0 || row.taken > 0).map((row) => (<tr key={`${row.employee_id}-${row.leave_type_id}`} className="border-t"><td className="py-1">{row.employee_code} {row.employee_name}</td><td>{row.leave_type}</td><td className="text-right tabular-nums">{row.entitlement || '—'}</td><td className="text-right tabular-nums">{row.taken}</td><td className="text-right tabular-nums">{row.entitlement ? row.balance : '—'}</td></tr>))}
                        </tbody></table>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Leave types</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Code</th><th>Name</th><th>Paid</th><th className="text-right">Days a year</th><th /></tr></thead><tbody>
                            {types.map((row) => (<tr key={row.id} className="border-t"><td className="py-1 font-medium">{row.code}</td><td>{row.name}</td><td>{row.is_paid ? 'yes' : 'no'}</td><td className="text-right tabular-nums">{Number(row.annual_days) || '—'}</td>
                                <td className="text-right">{canManage && <Button variant="ghost" size="sm" onClick={() => confirm('Delete this leave type?') && router.delete(`/accounting/payroll/leave-types/${row.id}`)}>Delete</Button>}</td></tr>))}
                        </tbody></table>
                        {canManage && (
                            <form onSubmit={addType} className="grid gap-3 md:grid-cols-5">
                                <div className="space-y-1"><Label>Code</Label><Input value={type.data.code} onChange={(event) => type.setData('code', event.target.value)} /><InputError message={type.errors.code} /></div>
                                <div className="space-y-1"><Label>Name</Label><Input value={type.data.name} onChange={(event) => type.setData('name', event.target.value)} /><InputError message={type.errors.name} /></div>
                                <div className="space-y-1"><Label>Days a year (0 = not limited)</Label><Input type="number" step="0.5" value={type.data.annual_days} onChange={(event) => type.setData('annual_days', event.target.value)} /></div>
                                <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={type.data.is_paid} onChange={(event) => type.setData('is_paid', event.target.checked)} /> Paid leave</label>
                                <div className="pt-6"><Button type="submit" disabled={type.processing}>Add type</Button></div>
                            </form>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PayrollLeaves.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Leave', href: '/accounting/payroll/leaves' }] };
