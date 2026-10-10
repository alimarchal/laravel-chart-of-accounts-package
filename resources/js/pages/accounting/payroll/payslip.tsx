import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAccountingI18n } from '@/lib/i18n';

type Props = {
    run: { id: number; period_month: string; status: string };
    employee: { code: string; name: string; designation: string | null; national_id: string | null; bank_name: string | null; bank_account: string | null };
    payslip: { basic: string; gross: string; deductions: string; tax: string; net: string; employer: string; days_paid: string; days_in_month: string; lines: Array<{ kind: string; description: string; amount: string }> };
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function Payslip({ run, employee, payslip }: Props) {
    useAccountingI18n();
    const earnings = payslip.lines.filter((line) => ['basic', 'earning', 'arrears'].includes(line.kind));
    const deductions = payslip.lines.filter((line) => ['deduction', 'tax'].includes(line.kind));
    const employer = payslip.lines.filter((line) => line.kind === 'employer');

    return (
        <>
            <Head title={`Payslip ${employee.code} ${run.period_month}`} />
            <div className="mx-auto max-w-3xl space-y-4 p-4">
                <div className="flex items-end justify-between print:hidden">
                    <Heading title={`Payslip ${run.period_month}`} description={`${employee.code} · ${employee.name}`} />
                    <div className="flex gap-2"><Button variant="outline" onClick={() => window.print()}>Print</Button><Button asChild variant="outline"><Link href={`/accounting/payroll/runs/${run.id}`}>Back</Link></Button></div>
                </div>
                <Card>
                    <CardContent className="space-y-4 pt-6 text-sm">
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div><span className="text-muted-foreground">Employee</span><p className="font-medium">{employee.name} ({employee.code})</p></div>
                            <div><span className="text-muted-foreground">Designation</span><p>{employee.designation ?? '—'}</p></div>
                            <div><span className="text-muted-foreground">National ID</span><p>{employee.national_id ?? '—'}</p></div>
                            <div><span className="text-muted-foreground">Days paid</span><p>{Number(payslip.days_paid)} of {Number(payslip.days_in_month)}</p></div>
                            <div><span className="text-muted-foreground">Bank</span><p>{employee.bank_name ? `${employee.bank_name} ${employee.bank_account ?? ''}` : '—'}</p></div>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <div><p className="mb-1 font-medium">Earnings</p>{earnings.map((line, index) => (<div key={index} className="flex justify-between gap-3 border-t py-1"><span>{line.description}</span><span className="tabular-nums">{money(line.amount)}</span></div>))}
                                <div className="flex justify-between border-t py-1 font-medium"><span>Gross</span><span className="tabular-nums">{money(payslip.gross)}</span></div></div>
                            <div><p className="mb-1 font-medium">Deductions</p>{deductions.map((line, index) => (<div key={index} className="flex justify-between gap-3 border-t py-1"><span>{line.description}</span><span className="tabular-nums">{money(line.amount)}</span></div>))}
                                <div className="flex justify-between border-t py-1 font-medium"><span>Total</span><span className="tabular-nums">{money(String(Number(payslip.deductions) + Number(payslip.tax)))}</span></div></div>
                        </div>
                        {employer.length > 0 && (
                            <div><p className="mb-1 font-medium">Employer contributions <span className="font-normal text-muted-foreground">(paid by the company, not deducted from pay)</span></p>
                                {employer.map((line, index) => (<div key={index} className="flex justify-between gap-3 border-t py-1"><span>{line.description}</span><span className="tabular-nums">{money(line.amount)}</span></div>))}</div>
                        )}
                        <div className="flex justify-between border-t pt-3 text-lg font-semibold"><span>Net pay</span><span className="tabular-nums">{money(payslip.net)}</span></div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Payslip.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Payslip', href: '#' }] };
