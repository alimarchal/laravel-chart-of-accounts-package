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

type Loan = {
    id: number; employee_code: string; employee_name: string; kind: string; principal: string; installments: number; start_month: string; issued_on: string | null; status: string; outstanding: string; notes: string | null;
    schedule: Array<{ id: number; due_month: string; amount: string; status: string }>;
};
type Props = { loans: Loan[]; status: string | null; employees: Array<{ id: number; code: string; name: string }>; accounts: Array<{ id: number; account_code: string; account_name: string }>; today: string };

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PayrollLoans({ loans, status, employees, accounts, today }: Props) {
    const { permissions, flash } = useAccounting();
    const form = useForm({ employee_id: '', kind: 'loan', principal: '', installments: '12', start_month: today, notes: '' });
    const [open, setOpen] = useState<number | null>(null);
    const [account, setAccount] = useState('');
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/accounting/payroll/loans', { onSuccess: () => form.reset('principal', 'notes') }); };
    const act = (id: number, action: string, body: Record<string, string> = {}, confirmText?: string) => (!confirmText || confirm(confirmText)) && router.post(`/accounting/payroll/loans/${id}/${action}`, body, { preserveScroll: true });
    const bankSelect = (
        <select className={`${selectClass} w-64`} value={account} onChange={(event) => setAccount(event.target.value)}><option value="">Bank or cash account…</option>{accounts.map((row) => (<option key={row.id} value={row.id}>{row.account_code} {row.account_name}</option>))}</select>
    );

    return (
        <>
            <Head title="Loans and advances" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Loans and advances" description="Paid out to employees and recovered by instalments from their salary" />
                    <div className="flex gap-2">
                        {['', 'draft', 'active', 'closed'].map((value) => (<Button key={value} variant={(status ?? '') === value ? 'default' : 'outline'} size="sm" onClick={() => router.get('/accounting/payroll/loans', value ? { status: value } : {})}>{value || 'all'}</Button>))}
                        <Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button>
                    </div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                {permissions['payroll.manage'] && (
                    <form onSubmit={submit}><Card>
                        <CardHeader><CardTitle>New loan or advance</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-4">
                            <div className="space-y-1"><Label>Employee</Label><select className={selectClass} value={form.data.employee_id} onChange={(event) => form.setData('employee_id', event.target.value)}><option value="">Choose…</option>{employees.map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name}</option>))}</select><InputError message={form.errors.employee_id} /></div>
                            <div className="space-y-1"><Label>Kind</Label><select className={selectClass} value={form.data.kind} onChange={(event) => form.setData('kind', event.target.value)}><option value="loan">Loan</option><option value="advance">Salary advance</option></select></div>
                            <div className="space-y-1"><Label>Amount</Label><Input type="number" step="any" value={form.data.principal} onChange={(event) => form.setData('principal', event.target.value)} /><InputError message={form.errors.principal} /></div>
                            <div className="space-y-1"><Label>Instalments</Label><Input type="number" min="1" value={form.data.installments} onChange={(event) => form.setData('installments', event.target.value)} /><InputError message={form.errors.installments} /></div>
                            <div className="space-y-1"><Label>First salary it comes out of</Label><Input type="date" value={form.data.start_month} onChange={(event) => form.setData('start_month', event.target.value)} /></div>
                            <div className="space-y-1 md:col-span-2"><Label>Notes</Label><Input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div>
                            <div className="pt-6"><Button type="submit" disabled={form.processing}>Record</Button></div>
                        </CardContent>
                    </Card></form>
                )}
                <Card><CardContent className="overflow-x-auto pt-6">
                    <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th>Kind</th><th className="text-right">Amount</th><th className="text-right">Left to recover</th><th>From</th><th>Status</th><th /></tr></thead><tbody>
                        {loans.map((loan) => (
                            <>
                                <tr key={loan.id} className="border-t">
                                    <td className="py-2">{loan.employee_code} {loan.employee_name}</td><td>{loan.kind}</td><td className="text-right tabular-nums">{money(loan.principal)}</td><td className="text-right tabular-nums">{money(loan.outstanding)}</td><td>{loan.start_month} · {loan.installments}x</td><td><Badge variant="outline">{loan.status}</Badge></td>
                                    <td className="space-x-1 text-right"><Button variant="ghost" size="sm" onClick={() => setOpen(open === loan.id ? null : loan.id)}>Schedule</Button>
                                        {loan.status === 'active' && permissions['payroll.manage'] && <Button variant="ghost" size="sm" onClick={() => act(loan.id, 'skip', {}, 'Move the next instalment to the end?')}>Skip next</Button>}
                                        {loan.status === 'draft' && permissions['payroll.manage'] && <Button variant="ghost" size="sm" onClick={() => act(loan.id, 'cancel', {}, 'Cancel this loan?')}>Cancel</Button>}</td>
                                </tr>
                                {open === loan.id && (
                                    <tr key={`${loan.id}-s`}><td colSpan={7} className="bg-muted/30 p-3">
                                        <div className="flex flex-wrap gap-2 text-xs">{loan.schedule.map((row) => (<span key={row.id} className={`rounded border px-2 py-1 ${row.status === 'cancelled' ? 'line-through opacity-50' : ''}`}>{row.due_month} {money(row.amount)} <em>{row.status}</em></span>))}</div>
                                        {permissions['payroll.post'] && (loan.status === 'draft' || loan.status === 'active') && (
                                            <div className="mt-3 flex flex-wrap items-center gap-2">{bankSelect}
                                                {loan.status === 'draft' && <Button size="sm" disabled={!account} onClick={() => act(loan.id, 'disburse', { account_id: account })}>Pay out {money(loan.principal)}</Button>}
                                                {loan.status === 'active' && <Button size="sm" variant="outline" disabled={!account} onClick={() => act(loan.id, 'settle', { account_id: account }, 'The employee pays back what is left in cash?')}>Settle {money(loan.outstanding)}</Button>}</div>
                                        )}
                                    </td></tr>
                                )}
                            </>
                        ))}
                        {loans.length === 0 && <tr><td colSpan={7} className="py-4 text-center text-muted-foreground">No loans yet.</td></tr>}
                    </tbody></table>
                </CardContent></Card>
            </div>
        </>
    );
}

PayrollLoans.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Loans', href: '/accounting/payroll/loans' }] };
