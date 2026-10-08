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

type Grade = { id: number; code: string; name: string; base_salary: string; is_active: boolean; employees: number; components: Array<{ pay_component_id: number; value: string | null }> };
type Component = { id: number; code: string; name: string; kind: string; method: string; value: string; rate: string; unit: string | null };
type Props = { grades: Grade[]; editing: Grade | null; components: Component[] };

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const basis = (component: Component) => (component.method === 'percent_of_basic' ? '% of basic' : component.method === 'quantity_rate' ? `${component.unit ?? 'units'} x ${Number(component.rate)}` : 'per month');

export default function PayrollGrades({ grades, editing, components }: Props) {
    const { permissions, flash } = useAccounting();
    const form = useForm({
        code: editing?.code ?? '', name: editing?.name ?? '', base_salary: editing?.base_salary ?? '', is_active: editing?.is_active ?? true,
        components: (editing?.components ?? []).map((row) => ({ pay_component_id: row.pay_component_id, value: row.value ?? '' })),
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (editing) form.put(`/accounting/payroll/grades/${editing.id}`);
        else form.post('/accounting/payroll/grades', { onSuccess: () => form.reset() });
    };
    const linked = (id: number) => form.data.components.find((row) => row.pay_component_id === id);
    const toggle = (id: number, on: boolean) => form.setData('components', on ? [...form.data.components, { pay_component_id: id, value: '' }] : form.data.components.filter((row) => row.pay_component_id !== id));
    const setValue = (id: number, value: string) => form.setData('components', form.data.components.map((row) => (row.pay_component_id === id ? { ...row, value } : row)));

    return (
        <>
            <Head title="Salary grades" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Salary grades" description="A basic salary with its allowances: change a grade and everybody on it follows in the next payroll calculation" />
                    <div className="flex gap-2"><Button asChild variant="outline"><Link href="/accounting/payroll/bulk">Bulk changes</Link></Button><Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button></div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <Card><CardContent className="overflow-x-auto pt-6">
                    <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Code</th><th>Name</th><th className="text-right">Basic salary</th><th className="text-right">Employees</th><th>Allowances</th><th /></tr></thead><tbody>
                        {grades.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="py-2 font-medium">{row.code}</td><td>{row.name} {!row.is_active && <Badge variant="outline">inactive</Badge>}</td><td className="text-right tabular-nums">{money(row.base_salary)}</td><td className="text-right">{row.employees}</td>
                                <td>{row.components.map((link) => components.find((component) => component.id === link.pay_component_id)?.name).filter(Boolean).join(', ')}</td>
                                <td className="text-right">{permissions['payroll.manage'] && <><Button variant="ghost" size="sm" asChild><Link href={`/accounting/payroll/grades?edit=${row.id}`}>Edit</Link></Button><Button variant="ghost" size="sm" onClick={() => confirm('Delete this grade?') && router.delete(`/accounting/payroll/grades/${row.id}`)}>Delete</Button></>}</td>
                            </tr>
                        ))}
                        {grades.length === 0 && <tr><td colSpan={6} className="py-4 text-center text-muted-foreground">No grades yet.</td></tr>}
                    </tbody></table>
                </CardContent></Card>
                {permissions['payroll.manage'] && (
                    <form onSubmit={submit} key={editing?.id ?? 'new'}>
                        <Card>
                            <CardHeader><CardTitle>{editing ? `Edit ${editing.code}` : 'Add a grade'}</CardTitle></CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-4">
                                    <div className="space-y-1"><Label htmlFor="code">Code</Label><Input id="code" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /><InputError message={form.errors.code} /></div>
                                    <div className="space-y-1"><Label htmlFor="name">Name</Label><Input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /><InputError message={form.errors.name} /></div>
                                    <div className="space-y-1"><Label htmlFor="base_salary">Monthly basic salary</Label><Input id="base_salary" type="number" step="any" value={form.data.base_salary} onChange={(event) => form.setData('base_salary', event.target.value)} /><InputError message={form.errors.base_salary} /></div>
                                    <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Active</label>
                                </div>
                                <div className="space-y-2 text-sm">
                                    <p className="font-medium">Allowances and deductions of this grade</p>
                                    {components.map((component) => {
                                        const row = linked(component.id);
                                        return (
                                            <div key={component.id} className="flex flex-wrap items-center gap-3">
                                                <label className="flex w-72 items-center gap-2"><input type="checkbox" checked={!!row} onChange={(event) => toggle(component.id, event.target.checked)} />{component.name} <span className="text-muted-foreground">({component.kind})</span></label>
                                                {row && <><Input className="w-32" type="number" step="any" placeholder={component.value} value={row.value} onChange={(event) => setValue(component.id, event.target.value)} /><span className="text-muted-foreground">{basis(component)} (blank = {Number(component.value)})</span></>}
                                            </div>
                                        );
                                    })}
                                    {components.length === 0 && <p className="text-muted-foreground">No components defined yet.</p>}
                                </div>
                                <div className="flex gap-2"><Button type="submit" disabled={form.processing}>{editing ? 'Save' : 'Add'}</Button>{editing && <Button asChild variant="outline"><Link href="/accounting/payroll/grades">Cancel</Link></Button>}</div>
                            </CardContent>
                        </Card>
                    </form>
                )}
            </div>
        </>
    );
}

PayrollGrades.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Grades', href: '/accounting/payroll/grades' }] };
