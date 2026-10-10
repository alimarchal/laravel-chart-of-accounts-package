import { Head, Link, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Slip = { id: number; employee_code: string; employee_name: string; basic: string; gross: string; deductions: string; tax: string; net: string; days_paid: string; days_in_month: string };
type Props = {
    run: { id: number; period_month: string; status: string; gross: string; deductions: string; tax: string; net: string; employer?: string; journal_entry_id: number | null; payment_entry_id: number | null; posted_on: string | null; paid_on: string | null; notes: string | null };
    payslips: Slip[];
    accounts: Array<{ id: number; account_code: string; account_name: string; type: string }>;
    today: string;
    bank: { missing: Array<{ code: string; name: string; net: string }>; layouts: string[] } | null;
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function PayrollRunShow({ run, payslips, accounts, today, bank }: Props) {
    const { permissions, flash, features } = useAccounting();
    const pay = useForm({ account_id: '', date: today });
    const base = `/accounting/payroll/runs/${run.id}`;
    const act = (action: string, confirmText?: string) => (!confirmText || confirm(confirmText)) && router.post(`${base}/${action}`, {}, { preserveScroll: true });

    return (
        <>
            <Head title={`Payroll ${run.period_month}`} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title={`Payroll ${run.period_month}`} description={run.notes ?? undefined} />
                    <div className="flex items-center gap-2">
                        <Badge variant="outline">{run.status}</Badge>
                        {run.status === 'draft' && permissions['payroll.run'] && <Button variant="outline" onClick={() => act('recalculate')}>Recalculate</Button>}
                        {run.status === 'draft' && permissions['payroll.post'] && <Button onClick={() => act('post', 'Post this payroll to the books?')}>Post</Button>}
                        {run.status === 'draft' && permissions['payroll.run'] && <Button variant="ghost" onClick={() => confirm('Delete this draft run?') && router.delete(base)}>Delete</Button>}
                        {['posted', 'paid'].includes(run.status) && permissions['payroll.manage'] && features.payroll_payslip_mail !== false && <Button variant="outline" onClick={() => confirm('E-mail every payslip to its employee?') && act('email-payslips')}>E-mail payslips</Button>}
                        {['posted', 'paid'].includes(run.status) && permissions['payroll.void'] && <Button variant="ghost" onClick={() => act('void', 'Void this payroll? Its entries will be reversed.')}>Void</Button>}
                    </div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <div className="grid gap-4 sm:grid-cols-4">
                    {([['Gross', run.gross], ['Deductions', run.deductions], ['Income tax', run.tax], ['Net pay', run.net]] as const).map(([label, value]) => (
                        <Card key={label}><CardContent className="pt-6"><p className="text-sm text-muted-foreground">{label}</p><p className="mt-1 text-2xl font-semibold tabular-nums">{money(value)}</p></CardContent></Card>
                    ))}
                </div>
                <p className="text-sm text-muted-foreground">
                    {run.journal_entry_id && <>Salary entry <Link className="hover:underline" href={`/accounting/journal-entries/${run.journal_entry_id}`}>#{run.journal_entry_id}</Link> on {run.posted_on}. </>}
                    {run.payment_entry_id && <>Payment entry <Link className="hover:underline" href={`/accounting/journal-entries/${run.payment_entry_id}`}>#{run.payment_entry_id}</Link> on {run.paid_on}.</>}
                </p>
                {run.status === 'posted' && permissions['payroll.post'] && (
                    <Card>
                        <CardHeader><CardTitle>Pay salaries</CardTitle></CardHeader>
                        <CardContent className="flex flex-wrap items-end gap-3">
                            <div className="w-72 space-y-1"><Label htmlFor="account_id">Paid from</Label>
                                <select id="account_id" className={selectClass} value={pay.data.account_id} onChange={(event) => pay.setData('account_id', event.target.value)}>
                                    <option value="">Choose bank or cash…</option>{accounts.filter((account) => account.type === 'ASSET').map((account) => (<option key={account.id} value={account.id}>{account.account_code} {account.account_name}</option>))}
                                </select></div>
                            <div className="space-y-1"><Label htmlFor="date">Date</Label><Input id="date" type="date" value={pay.data.date} onChange={(event) => pay.setData('date', event.target.value)} /></div>
                            <Button disabled={!pay.data.account_id} onClick={() => pay.post(`${base}/pay`)}>Pay {money(run.net)}</Button>
                        </CardContent>
                    </Card>
                )}
                {bank && permissions['payroll.post'] && features.payroll_bank_file !== false && (
                    <Card>
                        <CardHeader><CardTitle>Bank salary file</CardTitle></CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="flex flex-wrap gap-2">
                                {bank.layouts.map((layout) => (
                                    <span key={layout} className="flex items-center gap-1 rounded-md border px-2 py-1">
                                        <span className="font-medium">{layout}</span>
                                        <Button asChild size="sm" variant="outline"><a href={`${base}/bank-file/csv?layout=${layout}`}>CSV</a></Button>
                                        <Button asChild size="sm" variant="outline"><a href={`${base}/bank-file/xlsx?layout=${layout}`}>Excel</a></Button>
                                    </span>
                                ))}
                            </div>
                            {bank.missing.length > 0 && <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-900">Left out for want of a bank account: {bank.missing.map((row) => `${row.code} ${row.name} (${money(row.net)})`).join(', ')}.</p>}
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardHeader><CardTitle>Payslips</CardTitle></CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th className="text-right">Days</th><th className="text-right">Basic</th><th className="text-right">Gross</th><th className="text-right">Deductions</th><th className="text-right">Tax</th><th className="text-right">Net</th></tr></thead>
                            <tbody>
                                {payslips.map((slip) => (
                                    <tr key={slip.id} className="border-t">
                                        <td className="py-1"><Link href={`${base}/payslips/${slip.id}`} className="hover:underline">{slip.employee_code} · {slip.employee_name}</Link></td>
                                        <td className="text-right">{Number(slip.days_paid)}/{Number(slip.days_in_month)}</td>
                                        <td className="text-right tabular-nums">{money(slip.basic)}</td><td className="text-right tabular-nums">{money(slip.gross)}</td><td className="text-right tabular-nums">{money(slip.deductions)}</td><td className="text-right tabular-nums">{money(slip.tax)}</td><td className="text-right font-medium tabular-nums">{money(slip.net)}</td>
                                    </tr>
                                ))}
                                {payslips.length === 0 && <tr><td colSpan={7} className="py-4 text-center text-muted-foreground">No employees were employed in this month.</td></tr>}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PayrollRunShow.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Run', href: '#' }] };
