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

type Props = {
    components: Array<{ id: number; code: string; name: string; kind: string; method: string; value: string; taxable: boolean; account_id: number; is_active: boolean }>;
    accounts: Array<{ id: number; account_code: string; account_name: string; type: string }>;
};

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function PayrollComponents({ components, accounts }: Props) {
    const { permissions, flash } = useAccounting();
    const form = useForm({ code: '', name: '', kind: 'earning', method: 'fixed', value: '', taxable: true, account_id: '' });
    const add = (event: FormEvent) => {
        event.preventDefault();
        form.post('/accounting/payroll/components', { onSuccess: () => form.reset() });
    };
    const label = (id: number) => accounts.find((account) => account.id === id);

    return (
        <>
            <Head title="Allowances and deductions" />
            <div className="space-y-6 p-4">
                <div className="flex items-end justify-between"><Heading title="Allowances and deductions" description="Earnings are booked to an expense account, deductions to the liability they are owed to" /><Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button></div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <Card><CardContent className="overflow-x-auto pt-6">
                    <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Code</th><th>Name</th><th>Kind</th><th>Amount</th><th>Account</th><th /></tr></thead><tbody>
                        {components.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="py-2 font-medium">{row.code}</td><td>{row.name} {!row.is_active && <Badge variant="outline">inactive</Badge>}</td><td>{row.kind}{row.kind === 'earning' && !row.taxable ? ' (not taxable)' : ''}</td>
                                <td>{Number(row.value)}{row.method === 'percent_of_basic' ? '% of basic' : ' per month'}</td><td>{label(row.account_id)?.account_code} {label(row.account_id)?.account_name}</td>
                                <td className="text-right">{permissions['payroll.manage'] && <Button variant="ghost" size="sm" onClick={() => confirm('Delete this component?') && router.delete(`/accounting/payroll/components/${row.id}`)}>Delete</Button>}</td>
                            </tr>
                        ))}
                        {components.length === 0 && <tr><td colSpan={6} className="py-4 text-center text-muted-foreground">No components yet.</td></tr>}
                    </tbody></table>
                </CardContent></Card>
                {permissions['payroll.manage'] && (
                    <form onSubmit={add}>
                        <Card>
                            <CardHeader><CardTitle>Add a component</CardTitle></CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-4">
                                <div className="space-y-1"><Label htmlFor="code">Code</Label><Input id="code" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /><InputError message={form.errors.code} /></div>
                                <div className="space-y-1"><Label htmlFor="name">Name</Label><Input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /><InputError message={form.errors.name} /></div>
                                <div className="space-y-1"><Label htmlFor="kind">Kind</Label><select id="kind" className={selectClass} value={form.data.kind} onChange={(event) => form.setData('kind', event.target.value)}><option value="earning">Earning (allowance)</option><option value="deduction">Deduction</option></select></div>
                                <div className="space-y-1"><Label htmlFor="method">Method</Label><select id="method" className={selectClass} value={form.data.method} onChange={(event) => form.setData('method', event.target.value)}><option value="fixed">Fixed amount per month</option><option value="percent_of_basic">Percent of basic</option></select></div>
                                <div className="space-y-1"><Label htmlFor="value">Default value</Label><Input id="value" type="number" step="any" value={form.data.value} onChange={(event) => form.setData('value', event.target.value)} /><InputError message={form.errors.value} /></div>
                                <div className="space-y-1"><Label htmlFor="account_id">{form.data.kind === 'deduction' ? 'Liability account' : 'Expense account'}</Label>
                                    <select id="account_id" className={selectClass} value={form.data.account_id} onChange={(event) => form.setData('account_id', event.target.value)}>
                                        <option value="">Choose…</option>{accounts.filter((account) => account.type === (form.data.kind === 'deduction' ? 'LIABILITY' : 'EXPENSE')).map((account) => (<option key={account.id} value={account.id}>{account.account_code} {account.account_name}</option>))}
                                    </select><InputError message={form.errors.account_id} /></div>
                                {form.data.kind === 'earning' && <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={form.data.taxable} onChange={(event) => form.setData('taxable', event.target.checked)} /> Counts for income tax</label>}
                                <div className="pt-6"><Button type="submit" disabled={form.processing}>Add</Button></div>
                            </CardContent>
                        </Card>
                    </form>
                )}
            </div>
        </>
    );
}

PayrollComponents.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Components', href: '/accounting/payroll/components' }] };
