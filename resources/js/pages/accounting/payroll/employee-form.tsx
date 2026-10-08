import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccountingI18n } from '@/lib/i18n';

type Component = { id: number; code: string; name: string; kind: string; method: string; value: string; rate: string; unit: string | null };
type Props = {
    employee: {
        id: number; code: string; name: string; national_id: string | null; designation: string | null; cost_center_id: number | null; salary_grade_id: number | null; join_date: string; leave_date: string | null; base_salary: string;
        withhold_tax: boolean; bank_name: string | null; bank_account: string | null; is_active: boolean; components: Array<{ pay_component_id: number; value: string | null }>;
    } | null;
    components: Component[];
    grades: Array<{ id: number; code: string; name: string; base_salary: string }>;
    history: Array<{ id: number; effective_from: string; old_salary: string; new_salary: string; reason: string | null }>;
    costCenters: Array<{ id: number; code: string; name: string }>;
    today: string;
};

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function PayrollEmployeeForm({ employee, components, grades, history, costCenters, today }: Props) {
    useAccountingI18n();
    const form = useForm({
        code: employee?.code ?? '', name: employee?.name ?? '', national_id: employee?.national_id ?? '', designation: employee?.designation ?? '', cost_center_id: employee?.cost_center_id ? String(employee.cost_center_id) : '', salary_grade_id: employee?.salary_grade_id ? String(employee.salary_grade_id) : '', effective_from: today, reason: '',
        join_date: employee?.join_date ?? today, leave_date: employee?.leave_date ?? '', base_salary: employee?.base_salary ?? '', withhold_tax: employee?.withhold_tax ?? false,
        bank_name: employee?.bank_name ?? '', bank_account: employee?.bank_account ?? '', is_active: employee?.is_active ?? true,
        components: (employee?.components ?? []).map((row) => ({ pay_component_id: row.pay_component_id, value: row.value ?? '' })),
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (employee) form.put(`/accounting/payroll/employees/${employee.id}`);
        else form.post('/accounting/payroll/employees');
    };
    const field = (name: 'code' | 'name' | 'national_id' | 'designation' | 'join_date' | 'leave_date' | 'base_salary' | 'bank_name' | 'bank_account', label: string, type = 'text') => (
        <div className="space-y-1"><Label htmlFor={name}>{label}</Label><Input id={name} type={type} step="any" value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)} /><InputError message={form.errors[name]} /></div>
    );
    const linked = (id: number) => form.data.components.find((row) => row.pay_component_id === id);
    const toggle = (id: number, on: boolean) => form.setData('components', on ? [...form.data.components, { pay_component_id: id, value: '' }] : form.data.components.filter((row) => row.pay_component_id !== id));
    const setValue = (id: number, value: string) => form.setData('components', form.data.components.map((row) => (row.pay_component_id === id ? { ...row, value } : row)));

    return (
        <>
            <Head title={employee ? 'Edit employee' : 'New employee'} />
            <form onSubmit={submit} className="space-y-4 p-4">
                <Heading title={employee ? `Edit ${employee.code}` : 'New employee'} description="Pay is worked out from the monthly salary and the allowances and deductions chosen here" />
                <Card>
                    <CardHeader><CardTitle>Employee</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        {field('code', 'Code')}{field('name', 'Name')}{field('national_id', 'National ID')}{field('designation', 'Designation')}{field('join_date', 'Joined', 'date')}{field('leave_date', 'Left (if so)', 'date')}
                        {field('base_salary', 'Monthly basic salary', 'number')}{field('bank_name', 'Bank')}{field('bank_account', 'Bank account')}
                        <div className="space-y-1"><Label htmlFor="cost_center_id">Cost center</Label>
                            <select id="cost_center_id" className={selectClass} value={form.data.cost_center_id} onChange={(event) => form.setData('cost_center_id', event.target.value)}>
                                <option value="">None</option>{costCenters.map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name}</option>))}
                            </select></div>
                        <div className="space-y-1"><Label htmlFor="salary_grade_id">Salary grade</Label>
                            <select id="salary_grade_id" className={selectClass} value={form.data.salary_grade_id} onChange={(event) => form.setData('salary_grade_id', event.target.value)}>
                                <option value="">None</option>{grades.map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name}</option>))}
                            </select><InputError message={form.errors.salary_grade_id} /></div>
                        {employee && Number(form.data.base_salary) !== Number(employee.base_salary) && <>
                            <div className="space-y-1"><Label htmlFor="effective_from">New salary applies from</Label><Input id="effective_from" type="date" value={form.data.effective_from} onChange={(event) => form.setData('effective_from', event.target.value)} /><p className="text-xs text-muted-foreground">A past date is paid with arrears.</p></div>
                            <div className="space-y-1"><Label htmlFor="reason">Reason</Label><Input id="reason" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></div>
                        </>}
                        <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={form.data.withhold_tax} onChange={(event) => form.setData('withhold_tax', event.target.checked)} /> Withhold income tax</label>
                        <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Active</label>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Allowances and deductions</CardTitle></CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {components.map((component) => {
                            const row = linked(component.id);
                            return (
                                <div key={component.id} className="flex flex-wrap items-center gap-3">
                                    <label className="flex w-72 items-center gap-2"><input type="checkbox" checked={!!row} onChange={(event) => toggle(component.id, event.target.checked)} />{component.name} <span className="text-muted-foreground">({component.kind})</span></label>
                                    {row && <><Input className="w-32" type="number" step="any" placeholder={component.value} value={row.value} onChange={(event) => setValue(component.id, event.target.value)} /><span className="text-muted-foreground">{component.method === 'percent_of_basic' ? '% of basic' : component.method === 'quantity_rate' ? `${component.unit ?? 'units'} x ${Number(component.rate)}` : 'per month'} (blank = {Number(component.value)})</span></>}
                                </div>
                            );
                        })}
                        {components.length === 0 && <p className="text-muted-foreground">No components defined yet.</p>}
                    </CardContent>
                </Card>
                {history.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle>Salary history</CardTitle></CardHeader>
                        <CardContent className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">From</th><th className="text-right">Was</th><th className="text-right">Now</th><th>Reason</th></tr></thead><tbody>
                            {history.map((row) => (<tr key={row.id} className="border-t"><td className="py-1">{row.effective_from}</td><td className="text-right tabular-nums">{Number(row.old_salary).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td><td className="text-right tabular-nums">{Number(row.new_salary).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td><td>{row.reason}</td></tr>))}
                        </tbody></table></CardContent>
                    </Card>
                )}
                <Button type="submit" disabled={form.processing}>Save</Button>
            </form>
        </>
    );
}

PayrollEmployeeForm.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Employee', href: '#' }] };
