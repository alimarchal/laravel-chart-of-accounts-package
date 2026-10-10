import { Head, Link, router, useForm } from '@inertiajs/react';
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

type Scheme = {
    id: number; code: string; name: string; base: string; employee_rate: string; employer_rate: string; employee_fixed: string; employer_fixed: string; ceiling: string | null;
    employee_account_id: number | null; employer_expense_account_id: number | null; employer_liability_account_id: number | null; on_arrears: boolean; applies_to_all: boolean; is_active: boolean; employees: number;
};
type Props = { schemes: Scheme[]; editing: Scheme | null; employees: Array<{ id: number; code: string; name: string }>; accounts: Array<{ id: number; account_code: string; account_name: string; type: string }> };

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function PayrollSchemes({ schemes, editing, employees, accounts }: Props) {
    const { permissions, flash } = useAccounting();
    const form = useForm({
        code: editing?.code ?? '', name: editing?.name ?? '', base: editing?.base ?? 'basic', employee_rate: editing?.employee_rate ?? '', employer_rate: editing?.employer_rate ?? '', employee_fixed: editing?.employee_fixed ?? '', employer_fixed: editing?.employer_fixed ?? '',
        ceiling: editing?.ceiling ?? '', employee_account_id: editing?.employee_account_id ? String(editing.employee_account_id) : '', employer_expense_account_id: editing?.employer_expense_account_id ? String(editing.employer_expense_account_id) : '',
        employer_liability_account_id: editing?.employer_liability_account_id ? String(editing.employer_liability_account_id) : '', on_arrears: editing?.on_arrears ?? false, applies_to_all: editing?.applies_to_all ?? false, is_active: editing?.is_active ?? true,
    });
    const assign = useForm({ scheme: '', mode: 'assign', employee_ids: [] as number[] });
    const submit = (event: FormEvent) => { event.preventDefault(); if (editing) form.put(`/accounting/payroll/schemes/${editing.id}`); else form.post('/accounting/payroll/schemes', { onSuccess: () => form.reset() }); };
    const fixed = form.data.base === 'fixed';
    const field = (key: 'code' | 'name' | 'employee_rate' | 'employer_rate' | 'employee_fixed' | 'employer_fixed' | 'ceiling', label: string, type = 'text') => (
        <div className="space-y-1"><Label htmlFor={key}>{label}</Label><Input id={key} type={type} step="any" value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)} /><InputError message={form.errors[key]} /></div>
    );
    const account = (key: 'employee_account_id' | 'employer_expense_account_id' | 'employer_liability_account_id', label: string, type: string) => (
        <div className="space-y-1"><Label htmlFor={key}>{label}</Label>
            <select id={key} className={selectClass} value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)}><option value="">Choose…</option>{accounts.filter((row) => row.type === type).map((row) => (<option key={row.id} value={row.id}>{row.account_code} {row.account_name}</option>))}</select>
            <InputError message={form.errors[key]} /></div>
    );
    const rate = (scheme: Scheme, side: 'employee' | 'employer') => (scheme.base === 'fixed' ? Number(scheme[`${side}_fixed`]).toLocaleString() : `${Number(scheme[`${side}_rate`])}%`);

    return (
        <>
            <Head title="Contributions" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Contributions" description="EOBI, PESSI/SESSI, provident fund: the employee's share comes out of pay, the employer's share is a cost owed to the fund" />
                    <Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <Card><CardContent className="overflow-x-auto pt-6">
                    <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Code</th><th>Name</th><th>Base</th><th className="text-right">Employee</th><th className="text-right">Employer</th><th className="text-right">Ceiling</th><th>Who</th><th /></tr></thead><tbody>
                        {schemes.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="py-2 font-medium">{row.code}</td><td>{row.name} {!row.is_active && <Badge variant="outline">inactive</Badge>}</td><td>{row.base}</td><td className="text-right tabular-nums">{rate(row, 'employee')}</td><td className="text-right tabular-nums">{rate(row, 'employer')}</td>
                                <td className="text-right tabular-nums">{row.ceiling ? Number(row.ceiling).toLocaleString() : '—'}</td><td>{row.applies_to_all ? 'everybody' : `${row.employees} employees`}</td>
                                <td className="text-right">{permissions['payroll.manage'] && <><Button variant="ghost" size="sm" asChild><Link href={`/accounting/payroll/schemes?edit=${row.id}`}>Edit</Link></Button><Button variant="ghost" size="sm" onClick={() => confirm('Delete this scheme?') && router.delete(`/accounting/payroll/schemes/${row.id}`)}>Delete</Button></>}</td>
                            </tr>
                        ))}
                        {schemes.length === 0 && <tr><td colSpan={8} className="py-4 text-center text-muted-foreground">No schemes yet.</td></tr>}
                    </tbody></table>
                </CardContent></Card>
                {permissions['payroll.manage'] && (
                    <form onSubmit={submit} key={editing?.id ?? 'new'}><Card>
                        <CardHeader><CardTitle>{editing ? `Edit ${editing.code}` : 'Add a scheme'}</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-4">
                            {field('code', 'Code')}{field('name', 'Name')}
                            <div className="space-y-1"><Label htmlFor="base">Calculated on</Label><select id="base" className={selectClass} value={form.data.base} onChange={(event) => form.setData('base', event.target.value)}><option value="basic">Percent of basic</option><option value="gross">Percent of gross</option><option value="fixed">Fixed amount a month</option></select></div>
                            {fixed ? <>{field('employee_fixed', 'Employee pays', 'number')}{field('employer_fixed', 'Employer pays', 'number')}</> : <>{field('employee_rate', 'Employee %', 'number')}{field('employer_rate', 'Employer %', 'number')}{field('ceiling', 'Ceiling (most of the base that counts)', 'number')}</>}
                            {account('employee_account_id', "Owed to (employees' share)", 'LIABILITY')}{account('employer_expense_account_id', "Employer's cost (expense)", 'EXPENSE')}{account('employer_liability_account_id', "Owed to (employer's share)", 'LIABILITY')}
                            <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={form.data.applies_to_all} onChange={(event) => form.setData('applies_to_all', event.target.checked)} /> Applies to every employee</label>
                            <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={form.data.on_arrears} onChange={(event) => form.setData('on_arrears', event.target.checked)} /> Also on arrears</label>
                            <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Active</label>
                            <div className="flex gap-2 pt-6"><Button type="submit" disabled={form.processing}>{editing ? 'Save' : 'Add'}</Button>{editing && <FeatureLink href="/accounting/payroll/schemes">Cancel</FeatureLink>}</div>
                        </CardContent>
                    </Card></form>
                )}
                {permissions['payroll.manage'] && schemes.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle>Give a scheme to employees</CardTitle></CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="flex flex-wrap items-end gap-3">
                                <div className="w-64 space-y-1"><Label>Scheme</Label><select className={selectClass} value={assign.data.scheme} onChange={(event) => assign.setData('scheme', event.target.value)}><option value="">Choose…</option>{schemes.map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name}</option>))}</select></div>
                                <div className="w-40 space-y-1"><Label>Action</Label><select className={selectClass} value={assign.data.mode} onChange={(event) => assign.setData('mode', event.target.value)}><option value="assign">Give it</option><option value="remove">Take it away</option></select></div>
                                <Button disabled={!assign.data.scheme} onClick={() => router.post(`/accounting/payroll/schemes/${assign.data.scheme}/assign`, { mode: assign.data.mode, employee_ids: assign.data.employee_ids }, { preserveScroll: true })}>Apply ({assign.data.employee_ids.length || 'every active employee'})</Button>
                            </div>
                            <details className="rounded-md border p-3"><summary className="cursor-pointer">Pick employees</summary>
                                <div className="mt-2 flex gap-2"><Button type="button" size="sm" variant="outline" onClick={() => assign.setData('employee_ids', employees.map((row) => row.id))}>All</Button><Button type="button" size="sm" variant="outline" onClick={() => assign.setData('employee_ids', [])}>None</Button></div>
                                <div className="mt-2 grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">{employees.map((row) => (<label key={row.id} className="flex items-center gap-2"><input type="checkbox" checked={assign.data.employee_ids.includes(row.id)} onChange={(event) => assign.setData('employee_ids', event.target.checked ? [...assign.data.employee_ids, row.id] : assign.data.employee_ids.filter((id) => id !== row.id))} />{row.code} {row.name}</label>))}</div>
                            </details>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

PayrollSchemes.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Contributions', href: '/accounting/payroll/schemes' }] };
