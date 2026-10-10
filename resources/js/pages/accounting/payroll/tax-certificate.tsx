import { Head, Link } from '@inertiajs/react';
import { FeatureLink } from '@/components/accounting/feature-link';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    certificate: {
        tax_year: number; label: string; from: string; to: string;
        employee: { id: number; code: string; name: string; national_id: string | null; designation: string | null; join_date: string };
        months: Array<{ month: string; taxable: string; tax: string; gross: string; net: string }>;
        totals: { taxable: string; tax: string; gross: string; net: string };
    };
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PayrollTaxCertificate({ certificate }: Props) {
    const { employee, months, totals } = certificate;

    return (
        <>
            <Head title={`Tax certificate ${employee.code}`} />
            <div className="mx-auto max-w-3xl space-y-4 p-4">
                <div className="flex items-end justify-between print:hidden">
                    <Heading title="Salary tax certificate" description={`${employee.code} · ${employee.name} · ${certificate.label}`} />
                    <div className="flex gap-2"><Button variant="outline" onClick={() => window.print()}>Print</Button><FeatureLink href="/accounting/payroll/tax">Back</FeatureLink></div>
                </div>
                <Card><CardContent className="space-y-4 pt-6 text-sm">
                    <h2 className="hidden text-lg font-semibold print:block">Salary tax certificate — tax year {certificate.label}</h2>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div><span className="text-muted-foreground">Employee</span><p className="font-medium">{employee.name} ({employee.code})</p></div>
                        <div><span className="text-muted-foreground">National ID</span><p>{employee.national_id ?? '—'}</p></div>
                        <div><span className="text-muted-foreground">Designation</span><p>{employee.designation ?? '—'}</p></div>
                        <div><span className="text-muted-foreground">Joined</span><p>{employee.join_date}</p></div>
                    </div>
                    <table className="w-full"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Month</th><th className="text-right">Gross</th><th className="text-right">Taxable income</th><th className="text-right">Tax withheld</th><th className="text-right">Net pay</th></tr></thead><tbody>
                        {months.map((row) => (<tr key={row.month} className="border-t"><td className="py-1">{row.month}</td><td className="text-right tabular-nums">{money(row.gross)}</td><td className="text-right tabular-nums">{money(row.taxable)}</td><td className="text-right tabular-nums">{money(row.tax)}</td><td className="text-right tabular-nums">{money(row.net)}</td></tr>))}
                        {months.length === 0 && <tr><td colSpan={5} className="py-4 text-center text-muted-foreground">No salary was paid in this tax year.</td></tr>}
                    </tbody><tfoot><tr className="border-t font-semibold"><td className="py-1">Total</td><td className="text-right tabular-nums">{money(totals.gross)}</td><td className="text-right tabular-nums">{money(totals.taxable)}</td><td className="text-right tabular-nums">{money(totals.tax)}</td><td className="text-right tabular-nums">{money(totals.net)}</td></tr></tfoot></table>
                    <p className="text-xs text-muted-foreground">Worked out from the salaries posted in the books for the tax year {certificate.label}.</p>
                </CardContent></Card>
            </div>
        </>
    );
}

PayrollTaxCertificate.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Tax certificate', href: '#' }] };
