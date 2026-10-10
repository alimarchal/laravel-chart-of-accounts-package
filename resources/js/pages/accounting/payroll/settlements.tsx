import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Row = {
    id: number; employee_code: string; employee_name: string; leave_date: string; status: string; service_years: string; basic: string; gratuity: string; leave_days: string; leave_encashment: string;
    adjustment: string; loan_recovery: string; net: string; journal_entry_id: number | null; posted_on: string | null; paid_on: string | null; notes: string | null;
};
type Preview = { employee_id: number; leave_date: string; service_years: string; basic: string; gratuity: string; leave_days: string; leave_encashment: string; adjustment: string; loan_recovery: string; net: string; loans: Array<{ loan_id: number; kind: string; outstanding: string }> };
type Props = { settlements: Row[]; preview: Preview | null; today: string; employees: Array<{ id: number; code: string; name: string }>; accounts: Array<{ id: number; account_code: string; account_name: string }> };

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PayrollSettlements({ settlements, preview, today, employees, accounts }: Props) {
    const { permissions, flash } = useAccounting();
    const form = useForm({ employee_id: '', leave_date: today, gratuity: '', leave_days: '', adjustment: '', notes: '' });
    const [account, setAccount] = useState('');
    const act = (id: number, action: string, body: Record<string, string> = {}, confirmText?: string) => (!confirmText || confirm(confirmText)) && router.post(`/accounting/payroll/settlements/${id}/${action}`, body, { preserveScroll: true });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/accounting/payroll/settlements', { onSuccess: () => form.reset('gratuity', 'leave_days', 'adjustment', 'notes') }); };
    const lines = preview ? ([['Years of service', preview.service_years], ['Monthly basic', money(preview.basic)], ['Gratuity', money(preview.gratuity)], [`Leave pay (${Number(preview.leave_days)} days)`, money(preview.leave_encashment)], ['Adjustment', money(preview.adjustment)], ['Loans recovered', `− ${money(preview.loan_recovery)}`], ['Net settlement', money(preview.net)]] as const) : [];

    return (
        <>
            <Head title="Final settlements" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Final settlements" description="Gratuity, unused leave and loans owed when an employee leaves, paid as one net amount" />
                    <Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                {permissions['payroll.manage'] && (
                    <form onSubmit={submit}><Card>
                        <CardHeader><CardTitle>Work out a settlement</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-4">
                            <div className="space-y-1"><Label>Employee</Label><select className={selectClass} value={form.data.employee_id} onChange={(event) => form.setData('employee_id', event.target.value)}><option value="">Choose…</option>{employees.map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name}</option>))}</select><InputError message={form.errors.employee_id} /></div>
                            <div className="space-y-1"><Label>Last day</Label><Input type="date" value={form.data.leave_date} onChange={(event) => form.setData('leave_date', event.target.value)} /><InputError message={form.errors.leave_date} /></div>
                            <div className="space-y-1"><Label>Gratuity (blank = by the years served)</Label><Input type="number" step="any" value={form.data.gratuity} onChange={(event) => form.setData('gratuity', event.target.value)} /></div>
                            <div className="space-y-1"><Label>Leave days paid (blank = what is left)</Label><Input type="number" step="0.5" value={form.data.leave_days} onChange={(event) => form.setData('leave_days', event.target.value)} /></div>
                            <div className="space-y-1"><Label>Adjustment (+ pay, − recover)</Label><Input type="number" step="any" value={form.data.adjustment} onChange={(event) => form.setData('adjustment', event.target.value)} /></div>
                            <div className="space-y-1 md:col-span-2"><Label>Notes</Label><Input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div>
                            <div className="flex gap-2 pt-6"><Button type="button" variant="outline" disabled={form.processing} onClick={() => form.post('/accounting/payroll/settlements/preview', { preserveScroll: true })}>Preview</Button><Button type="submit" disabled={form.processing || !preview}>Save draft</Button></div>
                            {preview && (
                                <div className="md:col-span-4"><table className="w-full max-w-md text-sm"><tbody>{lines.map(([label, value]) => (<tr key={label} className="border-t"><td className="py-1">{label}</td><td className="text-right tabular-nums">{value}</td></tr>))}</tbody></table>
                                    {preview.loans.length > 0 && <p className="mt-2 text-xs text-muted-foreground">Open loans recovered: {preview.loans.map((loan) => `${loan.kind} ${money(loan.outstanding)}`).join(', ')}</p>}</div>
                            )}
                        </CardContent>
                    </Card></form>
                )}
                <Card><CardContent className="overflow-x-auto pt-6">
                    <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th>Last day</th><th className="text-right">Gratuity</th><th className="text-right">Leave pay</th><th className="text-right">Loans</th><th className="text-right">Net</th><th>Status</th><th /></tr></thead><tbody>
                        {settlements.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="py-2">{row.employee_code} {row.employee_name}</td><td>{row.leave_date}</td><td className="text-right tabular-nums">{money(row.gratuity)}</td><td className="text-right tabular-nums">{money(row.leave_encashment)}</td><td className="text-right tabular-nums">{money(row.loan_recovery)}</td><td className="text-right font-medium tabular-nums">{money(row.net)}</td><td><Badge variant="outline">{row.status}</Badge></td>
                                <td className="space-x-1 text-right">
                                    {row.status === 'draft' && permissions['payroll.post'] && <Button size="sm" variant="outline" onClick={() => act(row.id, 'post', {}, 'Post this settlement to the books?')}>Post</Button>}
                                    {row.status === 'draft' && permissions['payroll.manage'] && <Button size="sm" variant="ghost" onClick={() => confirm('Delete this draft?') && router.delete(`/accounting/payroll/settlements/${row.id}`, { preserveScroll: true })}>Delete</Button>}
                                    {row.status === 'posted' && permissions['payroll.post'] && <><select className="h-8 w-48 rounded-md border border-input bg-background px-2 text-xs" value={account} onChange={(event) => setAccount(event.target.value)}><option value="">Paid from…</option>{accounts.map((a) => (<option key={a.id} value={a.id}>{a.account_code} {a.account_name}</option>))}</select><Button size="sm" disabled={!account} onClick={() => act(row.id, 'pay', { account_id: account })}>Pay</Button></>}
                                    {['posted', 'paid'].includes(row.status) && permissions['payroll.void'] && <Button size="sm" variant="ghost" onClick={() => act(row.id, 'void', {}, 'Void this settlement? Its entries are reversed and the loans it recovered open again.')}>Void</Button>}
                                </td>
                            </tr>
                        ))}
                        {settlements.length === 0 && <tr><td colSpan={8} className="py-4 text-center text-muted-foreground">No settlements yet.</td></tr>}
                    </tbody></table>
                </CardContent></Card>
            </div>
        </>
    );
}

PayrollSettlements.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Settlements', href: '/accounting/payroll/settlements' }] };
