import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FeatureLink } from '@/components/accounting/feature-link';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Employee = { id: number; code: string; name: string; salary_grade_id: number | null; base_salary: string; is_active: boolean };
type Props = {
    employees: Employee[];
    components: Array<{ id: number; code: string; name: string; kind: string; method: string; value: string }>;
    grades: Array<{ id: number; code: string; name: string; base_salary: string }>;
    preview: Array<{ employee_id: number; code: string; name: string; old_salary: string; new_salary: string; difference: string }> | null;
    today: string;
};

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

/** Who a bulk action is for: nobody picked = everybody on the grade chosen, or every active employee. */
function Who({ employees, grades, data, set, errors }: { employees: Employee[]; grades: Props['grades']; data: { salary_grade_id: string; employee_ids: number[] }; set: (key: 'salary_grade_id' | 'employee_ids', value: string | number[]) => void; errors: Record<string, string | undefined> }) {
    const toggle = (id: number, on: boolean) => set('employee_ids', on ? [...data.employee_ids, id] : data.employee_ids.filter((item) => item !== id));

    return (
        <div className="space-y-2 md:col-span-4">
            <div className="grid gap-4 md:grid-cols-4">
                <div className="space-y-1"><Label>Grade (when nobody is picked below)</Label>
                    <select className={selectClass} value={data.salary_grade_id} onChange={(event) => set('salary_grade_id', event.target.value)}><option value="">Every active employee</option>{grades.map((grade) => (<option key={grade.id} value={grade.id}>{grade.code} {grade.name}</option>))}</select>
                    <InputError message={errors.salary_grade_id} /></div>
            </div>
            <details className="rounded-md border p-3 text-sm">
                <summary className="cursor-pointer">Pick employees ({data.employee_ids.length} picked)</summary>
                <div className="mt-2 flex gap-2"><Button type="button" size="sm" variant="outline" onClick={() => set('employee_ids', employees.map((employee) => employee.id))}>All</Button><Button type="button" size="sm" variant="outline" onClick={() => set('employee_ids', [])}>None</Button></div>
                <div className="mt-2 grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
                    {employees.map((employee) => (<label key={employee.id} className="flex items-center gap-2"><input type="checkbox" checked={data.employee_ids.includes(employee.id)} onChange={(event) => toggle(employee.id, event.target.checked)} />{employee.code} {employee.name}</label>))}
                </div>
            </details>
        </div>
    );
}

export default function PayrollBulk({ employees, components, grades, preview, today }: Props) {
    const { permissions, flash } = useAccounting();
    const component = useForm({ pay_component_id: '', mode: 'assign', value: '', salary_grade_id: '', employee_ids: [] as number[] });
    const grade = useForm({ grade_id: '', apply_salary: false, effective_from: today, salary_grade_id: '', employee_ids: [] as number[] });
    const raise = useForm({ mode: 'percent', value: '', effective_from: today, reason: '', round_to: '1', salary_grade_id: '', employee_ids: [] as number[] });
    const submitComponent = (event: FormEvent) => { event.preventDefault(); component.post('/accounting/payroll/bulk/components'); };
    const submitGrade = (event: FormEvent) => { event.preventDefault(); router.post(`/accounting/payroll/grades/${grade.data.grade_id}/assign`, { employee_ids: grade.data.employee_ids, apply_salary: grade.data.apply_salary, effective_from: grade.data.effective_from }); };
    const canManage = permissions['payroll.manage'];

    return (
        <>
            <Head title="Bulk changes" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Bulk changes" description="Set pay up for many employees at once: allowances, grades and raises" />
                    <div className="flex gap-2"><FeatureLink href="/accounting/payroll/arrears">Arrears</FeatureLink><FeatureLink href="/accounting/payroll/grades">Grades</FeatureLink><Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button></div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                {canManage && (
                    <>
                        <form onSubmit={submitComponent}><Card>
                            <CardHeader><CardTitle>Give an allowance or deduction to many</CardTitle></CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-4">
                                <div className="space-y-1"><Label>Component</Label><select className={selectClass} value={component.data.pay_component_id} onChange={(event) => component.setData('pay_component_id', event.target.value)}><option value="">Choose…</option>{components.map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name}</option>))}</select><InputError message={component.errors.pay_component_id} /></div>
                                <div className="space-y-1"><Label>Action</Label><select className={selectClass} value={component.data.mode} onChange={(event) => component.setData('mode', event.target.value)}><option value="assign">Give it</option><option value="remove">Take it away</option></select></div>
                                <div className="space-y-1"><Label>Their own value (blank = the component's)</Label><Input type="number" step="any" value={component.data.value} onChange={(event) => component.setData('value', event.target.value)} /></div>
                                <Who employees={employees} grades={grades} data={component.data} set={(key, value) => component.setData(key as 'salary_grade_id', value as never)} errors={component.errors} />
                                <div><Button type="submit" disabled={component.processing}>Apply</Button></div>
                            </CardContent>
                        </Card></form>
                        <form onSubmit={submitGrade}><Card>
                            <CardHeader><CardTitle>Put employees on a grade</CardTitle></CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-4">
                                <div className="space-y-1"><Label>Grade</Label><select className={selectClass} value={grade.data.grade_id} onChange={(event) => grade.setData('grade_id', event.target.value)}><option value="">Choose…</option>{grades.map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name} ({money(row.base_salary)})</option>))}</select></div>
                                <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={grade.data.apply_salary} onChange={(event) => grade.setData('apply_salary', event.target.checked)} /> Also move their salary to the grade's</label>
                                <div className="space-y-1"><Label>Salary applies from</Label><Input type="date" value={grade.data.effective_from} onChange={(event) => grade.setData('effective_from', event.target.value)} /></div>
                                <Who employees={employees} grades={grades} data={grade.data} set={(key, value) => grade.setData(key as 'salary_grade_id', value as never)} errors={grade.errors} />
                                <div><Button type="submit" disabled={!grade.data.grade_id}>Move</Button></div>
                            </CardContent>
                        </Card></form>
                        <Card>
                            <CardHeader><CardTitle>Raise salaries</CardTitle></CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-4">
                                <div className="space-y-1"><Label>How</Label><select className={selectClass} value={raise.data.mode} onChange={(event) => raise.setData('mode', event.target.value)}><option value="percent">Raise by a percent</option><option value="increase">Add an amount</option><option value="set">Set everybody to an amount</option></select></div>
                                <div className="space-y-1"><Label>{raise.data.mode === 'percent' ? 'Percent' : 'Amount'}</Label><Input type="number" step="any" value={raise.data.value} onChange={(event) => raise.setData('value', event.target.value)} /><InputError message={raise.errors.value} /></div>
                                <div className="space-y-1"><Label>Applies from</Label><Input type="date" value={raise.data.effective_from} onChange={(event) => raise.setData('effective_from', event.target.value)} /><InputError message={raise.errors.effective_from} /></div>
                                <div className="space-y-1"><Label>Round to the nearest</Label><Input type="number" min="1" value={raise.data.round_to} onChange={(event) => raise.setData('round_to', event.target.value)} /></div>
                                <div className="space-y-1 md:col-span-2"><Label>Reason</Label><Input value={raise.data.reason} onChange={(event) => raise.setData('reason', event.target.value)} /></div>
                                <Who employees={employees} grades={grades} data={raise.data} set={(key, value) => raise.setData(key as 'salary_grade_id', value as never)} errors={raise.errors} />
                                <div className="flex gap-2">
                                    <Button type="button" variant="outline" disabled={raise.processing} onClick={() => raise.post('/accounting/payroll/revisions/preview', { preserveScroll: true })}>Preview</Button>
                                    <Button type="button" disabled={raise.processing || !preview} onClick={() => confirm(`Apply to ${preview?.length ?? 0} employees?`) && raise.post('/accounting/payroll/revisions')}>Apply</Button>
                                </div>
                                {preview && (
                                    <div className="overflow-x-auto md:col-span-4">
                                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th className="text-right">Now</th><th className="text-right">New</th><th className="text-right">Difference</th></tr></thead><tbody>
                                            {preview.map((row) => (<tr key={row.employee_id} className="border-t"><td className="py-1">{row.code} {row.name}</td><td className="text-right tabular-nums">{money(row.old_salary)}</td><td className="text-right tabular-nums">{money(row.new_salary)}</td><td className="text-right tabular-nums">{money(row.difference)}</td></tr>))}
                                            {preview.length === 0 && <tr><td colSpan={4} className="py-3 text-center text-muted-foreground">Nobody matches.</td></tr>}
                                        </tbody></table>
                                        <p className="mt-2 text-xs text-muted-foreground">A raise from a past date does not change payslips already posted: use Arrears to pay the difference.</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

PayrollBulk.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Bulk changes', href: '/accounting/payroll/bulk' }] };
