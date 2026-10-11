import { Head, Link, router } from '@inertiajs/react';
import FilterBar from '@/components/accounting/filter-bar';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Props = { filters: Record<string, string | null>; costCenters: Array<{ id: number; code: string; name: string }>; employees: Array<{ id: number; code: string; name: string; designation: string | null; join_date: string; leave_date: string | null; base_salary: string; withhold_tax: boolean; is_active: boolean }> };

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PayrollEmployees({ employees, filters, costCenters }: Props) {
    const { permissions, flash } = useAccounting();

    return (
        <>
            <Head title="Employees" />
            <div className="space-y-6 p-4">
                <div className="flex items-end justify-between">
                    <Heading title="Employees" description="Who is paid, and how much" />
                    <div className="flex gap-2"><Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button>{permissions['payroll.manage'] && <Button asChild><Link href="/accounting/payroll/employees/create">New employee</Link></Button>}</div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <FilterBar
                    path="/accounting/payroll/employees"
                    values={filters}
                    fields={[
                        { name: 'search', label: 'Search', placeholder: 'Code, name, ID, designation or bank' },
                        { name: 'active', label: 'Status', type: 'select', options: { '1': 'Active', '0': 'Left' } },
                        { name: 'withhold_tax', label: 'Tax withheld', type: 'select', options: { '1': 'Yes', '0': 'No' } },
                        { name: 'cost_center_id', label: 'Cost center', type: 'select', options: Object.fromEntries(costCenters.map((row) => [String(row.id), `${row.code} ${row.name}`])) },
                        { name: 'joined_from', label: 'Joined from', type: 'date' },
                        { name: 'joined_to', label: 'Joined to', type: 'date' },
                        { name: 'min_salary', label: 'Salary from', type: 'number' },
                        { name: 'max_salary', label: 'Salary up to', type: 'number' },
                    ]}
                />
                <Card><CardContent className="overflow-x-auto pt-6">
                    <table className="w-full text-sm">
                        <thead><tr className="text-left text-muted-foreground"><th className="py-1">Code</th><th>Name</th><th>Designation</th><th>Joined</th><th>Left</th><th className="text-right">Monthly salary</th><th>Tax</th><th /></tr></thead>
                        <tbody>
                            {employees.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="py-2 font-medium">{row.code}</td><td>{row.name} {!row.is_active && <Badge variant="outline">inactive</Badge>}</td><td>{row.designation}</td><td>{row.join_date}</td><td>{row.leave_date}</td>
                                    <td className="text-right tabular-nums">{money(row.base_salary)}</td><td>{row.withhold_tax ? 'withheld' : ''}</td>
                                    <td className="text-right">
                                        {permissions['payroll.manage'] && <Button asChild variant="ghost" size="sm"><Link href={`/accounting/payroll/employees/${row.id}/edit`}>Edit</Link></Button>}
                                        {permissions['payroll.manage'] && <Button variant="ghost" size="sm" onClick={() => confirm('Delete this employee?') && router.delete(`/accounting/payroll/employees/${row.id}`)}>Delete</Button>}
                                    </td>
                                </tr>
                            ))}
                            {employees.length === 0 && <tr><td colSpan={8} className="py-6 text-center text-muted-foreground">No employees yet.</td></tr>}
                        </tbody>
                    </table>
                </CardContent></Card>
            </div>
        </>
    );
}

PayrollEmployees.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Employees', href: '/accounting/payroll/employees' }] };
