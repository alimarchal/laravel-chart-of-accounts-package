import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FeatureLink } from '@/components/accounting/feature-link';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Row = { id: number; employee_code: string; employee_name: string; month: string; kind: string; description: string; amount: string; taxable: boolean; status: string; notes: string | null };
type Props = {
    adjustments: Row[]; month: string; status: string;
    employees: Array<{ id: number; code: string; name: string }>; components: Array<{ id: number; code: string; name: string; kind: string }>; accounts: Array<{ id: number; account_code: string; account_name: string }>;
};

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PayrollAdjustments({ adjustments, month, status, employees, components, accounts }: Props) {
    const { permissions, flash } = useAccounting();
    const single = useForm({ employee_id: '', month: `${month}-01`, kind: 'earning', pay_component_id: '', account_id: '', description: '', amount: '', taxable: true, notes: '' });
    const bulk = useForm({ month: `${month}-01`, description: '', method: 'fixed', value: '', pay_component_id: '', taxable: true });
    const open = (query: Record<string, string>) => router.get('/accounting/payroll/adjustments', { month, status, ...query }, { preserveState: false });
    const submitSingle = (event: FormEvent) => { event.preventDefault(); single.post('/accounting/payroll/adjustments', { preserveScroll: true, onSuccess: () => single.reset('amount', 'description', 'notes') }); };
    const submitBulk = (event: FormEvent) => { event.preventDefault(); bulk.post('/accounting/payroll/adjustments/bulk', { preserveScroll: true, onSuccess: () => bulk.reset('value', 'description') }); };
    const options = (kind?: string) => components.filter((component) => !kind || component.kind === kind);

    return (
        <>
            <Head title="Bonuses and one-off pay" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Bonuses and one-off pay" description="A bonus, an extra allowance or a fine for one month, for one employee or for many; the month's payroll run takes it up" />
                    <FeatureLink href="/accounting/payroll">Payroll</FeatureLink>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <div className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1"><Label htmlFor="month">Month</Label><Input id="month" type="month" defaultValue={month} onChange={(event) => event.target.value && open({ month: event.target.value })} /></div>
                    {(['', 'open', 'included', 'cancelled'] as const).map((value) => (<Button key={value} size="sm" variant={status === value ? 'default' : 'outline'} onClick={() => open({ status: value })}>{value || 'all'}</Button>))}
                </div>
                {permissions['payroll.manage'] && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <form onSubmit={submitSingle}><Card>
                            <CardHeader><CardTitle>One employee</CardTitle></CardHeader>
                            <CardContent className="grid gap-3 md:grid-cols-2">
                                <div className="space-y-1"><Label>Employee</Label><select className={selectClass} value={single.data.employee_id} onChange={(event) => single.setData('employee_id', event.target.value)}><option value="">Choose…</option>{employees.map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name}</option>))}</select><InputError message={single.errors.employee_id} /></div>
                                <div className="space-y-1"><Label>Month</Label><Input type="date" value={single.data.month} onChange={(event) => single.setData('month', event.target.value)} /><InputError message={single.errors.month} /></div>
                                <div className="space-y-1 md:col-span-2"><Label>What for</Label><Input value={single.data.description} onChange={(event) => single.setData('description', event.target.value)} /><InputError message={single.errors.description} /></div>
                                <div className="space-y-1"><Label>Pay component (optional)</Label><select className={selectClass} value={single.data.pay_component_id} onChange={(event) => single.setData('pay_component_id', event.target.value)}><option value="">None: a plain bonus or fine</option>{options().map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name} ({row.kind})</option>))}</select></div>
                                <div className="space-y-1"><Label>Amount</Label><Input type="number" step="any" value={single.data.amount} onChange={(event) => single.setData('amount', event.target.value)} /><InputError message={single.errors.amount} /></div>
                                {!single.data.pay_component_id && (<>
                                    <div className="space-y-1"><Label>Pays or takes</Label><select className={selectClass} value={single.data.kind} onChange={(event) => single.setData('kind', event.target.value)}><option value="earning">Earning (bonus)</option><option value="deduction">Deduction (fine)</option></select></div>
                                    <div className="space-y-1"><Label>Account {single.data.kind === 'deduction' ? '(owed to)' : '(blank = bonus account)'}</Label><select className={selectClass} value={single.data.account_id} onChange={(event) => single.setData('account_id', event.target.value)}><option value="">Default</option>{accounts.map((row) => (<option key={row.id} value={row.id}>{row.account_code} {row.account_name}</option>))}</select><InputError message={single.errors.account_id} /></div>
                                </>)}
                                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={single.data.taxable} onChange={(event) => single.setData('taxable', event.target.checked)} />Taxable</label>
                                <div className="md:col-span-2"><Button type="submit" disabled={single.processing}>Add</Button></div>
                            </CardContent>
                        </Card></form>
                        <form onSubmit={submitBulk}><Card>
                            <CardHeader><CardTitle>Every active employee</CardTitle></CardHeader>
                            <CardContent className="grid gap-3 md:grid-cols-2">
                                <div className="space-y-1"><Label>Month</Label><Input type="date" value={bulk.data.month} onChange={(event) => bulk.setData('month', event.target.value)} /><InputError message={bulk.errors.month} /></div>
                                <div className="space-y-1"><Label>What for</Label><Input value={bulk.data.description} onChange={(event) => bulk.setData('description', event.target.value)} /><InputError message={bulk.errors.description} /></div>
                                <div className="space-y-1"><Label>How much</Label><select className={selectClass} value={bulk.data.method} onChange={(event) => bulk.setData('method', event.target.value)}><option value="fixed">The same amount for everybody</option><option value="percent_of_basic">A percent of each basic salary</option></select></div>
                                <div className="space-y-1"><Label>{bulk.data.method === 'fixed' ? 'Amount' : 'Percent'}</Label><Input type="number" step="any" value={bulk.data.value} onChange={(event) => bulk.setData('value', event.target.value)} /><InputError message={bulk.errors.value} /></div>
                                <div className="space-y-1"><Label>Pay component (optional)</Label><select className={selectClass} value={bulk.data.pay_component_id} onChange={(event) => bulk.setData('pay_component_id', event.target.value)}><option value="">None: a plain bonus</option>{options('earning').map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name}</option>))}</select></div>
                                <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={bulk.data.taxable} onChange={(event) => bulk.setData('taxable', event.target.checked)} />Taxable</label>
                                <div className="md:col-span-2"><Button type="submit" disabled={bulk.processing}>Add for everybody</Button></div>
                            </CardContent>
                        </Card></form>
                    </div>
                )}
                <Card><CardContent className="overflow-x-auto pt-6">
                    <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th>Month</th><th>What for</th><th>Kind</th><th className="text-right">Amount</th><th>Status</th><th /></tr></thead><tbody>
                        {adjustments.map((row) => (
                            <tr key={row.id} className="border-t"><td className="py-2">{row.employee_code} {row.employee_name}</td><td>{row.month}</td><td>{row.description}{!row.taxable && row.kind === 'earning' && <span className="ml-1 text-xs text-muted-foreground">(not taxed)</span>}</td><td>{row.kind}</td><td className="text-right tabular-nums">{money(row.amount)}</td><td><Badge variant="outline">{row.status}</Badge></td>
                                <td className="text-right">{row.status === 'open' && permissions['payroll.manage'] && <Button size="sm" variant="ghost" onClick={() => confirm('Cancel this?') && router.post(`/accounting/payroll/adjustments/${row.id}/cancel`, {}, { preserveScroll: true })}>Cancel</Button>}</td></tr>
                        ))}
                        {adjustments.length === 0 && <tr><td colSpan={7} className="py-4 text-center text-muted-foreground">Nothing for {month}.</td></tr>}
                    </tbody></table>
                </CardContent></Card>
            </div>
        </>
    );
}

PayrollAdjustments.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Bonuses', href: '/accounting/payroll/adjustments' }] };
