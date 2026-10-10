import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = { tax_year: number; label: string; rows: Array<Record<string, string>>; employees: Array<{ id: number; code: string; name: string }> };

const selectClass = 'h-9 w-64 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PayrollTax({ tax_year, label, rows, employees }: Props) {
    const [employee, setEmployee] = useState('');

    return (
        <>
            <Head title="Salary tax" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Salary tax statement" description={`Taxable income and income tax withheld, tax year ${label}`} />
                    <div className="flex gap-2"><Button asChild variant="outline"><Link href="/accounting/payroll/reports">Reports</Link></Button><Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button></div>
                </div>
                <div className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1"><Label htmlFor="year">Tax year starting in</Label><Input id="year" type="number" className="w-28" defaultValue={tax_year} onBlur={(event) => event.target.value && Number(event.target.value) !== tax_year && router.get('/accounting/payroll/tax', { year: event.target.value })} /></div>
                    <div className="space-y-1"><Label>Tax certificate of</Label>
                        <select className={selectClass} value={employee} onChange={(event) => setEmployee(event.target.value)}><option value="">Choose an employee…</option>{employees.map((row) => (<option key={row.id} value={row.id}>{row.code} {row.name}</option>))}</select></div>
                    <Button disabled={!employee} onClick={() => router.get(`/accounting/payroll/tax/certificate/${employee}`, { year: tax_year })}>Open certificate</Button>
                </div>
                <Card>
                    <CardHeader className="flex-row items-center justify-between"><CardTitle>Everybody, {label}</CardTitle>
                        <div className="flex gap-1">{(['csv', 'xlsx', 'pdf'] as const).map((format) => (<Button key={format} asChild size="sm" variant="outline"><a href={`/accounting/payroll/tax/annual/${format}?year=${tax_year}`}>{format.toUpperCase()}</a></Button>))}</div></CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Employee</th><th>National ID</th><th className="text-right">Months</th><th className="text-right">Taxable income</th><th className="text-right">Tax withheld</th><th className="text-right">Net pay</th></tr></thead><tbody>
                            {rows.map((row) => (<tr key={row['Employee code']} className="border-t"><td className="py-1">{row['Employee code']} {row['Employee name']}</td><td>{row['National ID']}</td><td className="text-right">{row['Months paid']}</td><td className="text-right tabular-nums">{money(row['Taxable income'])}</td><td className="text-right font-medium tabular-nums">{money(row['Tax withheld'])}</td><td className="text-right tabular-nums">{money(row['Net pay'])}</td></tr>))}
                            {rows.length === 0 && <tr><td colSpan={6} className="py-4 text-center text-muted-foreground">No salary was paid in this tax year.</td></tr>}
                        </tbody></table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PayrollTax.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Tax', href: '/accounting/payroll/tax' }] };
