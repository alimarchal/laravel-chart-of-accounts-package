import { Head, Link, router } from '@inertiajs/react';
import { FeatureLink } from '@/components/accounting/feature-link';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    year: number; from: string; to: string;
    comparison: Array<{ month: string; status: string; employees: number; gross: string; deductions: string; tax: string; net: string; employer: string; total_cost: string; cost_per_employee: string; change: string | null; change_percent: number | null }>;
    cost_centers: Array<{ cost_center: string; employees: number; pay: string; employer: string; total_cost: string }>;
    headcount: Array<{ month: string; joined: number; left: number; on_payroll: number }>;
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function PayrollReports({ year, from, to, comparison, cost_centers, headcount }: Props) {
    const exportLinks = (report: string) => (['csv', 'xlsx', 'pdf'] as const).map((format) => (<Button key={format} asChild size="sm" variant="outline"><a href={`/accounting/payroll/reports/${report}/export/${format}?year=${year}&from=${from}&to=${to}`}>{format.toUpperCase()}</a></Button>));

    return (
        <>
            <Head title="Payroll reports" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Payroll reports" description="What payroll cost, month by month and by cost center, and how many people were paid" />
                    <div className="flex gap-2"><FeatureLink href="/accounting/payroll/tax">Tax</FeatureLink><Button asChild variant="outline"><Link href="/accounting/payroll">Payroll</Link></Button></div>
                </div>
                <div className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1"><Label htmlFor="year">Year</Label><Input id="year" type="number" className="w-28" defaultValue={year} onBlur={(event) => event.target.value && Number(event.target.value) !== year && router.get('/accounting/payroll/reports', { year: event.target.value })} /></div>
                    <div className="space-y-1"><Label htmlFor="from">Cost centers from</Label><Input id="from" type="month" defaultValue={from} onChange={(event) => event.target.value && router.get('/accounting/payroll/reports', { year, from: event.target.value, to })} /></div>
                    <div className="space-y-1"><Label htmlFor="to">to</Label><Input id="to" type="month" defaultValue={to} onChange={(event) => event.target.value && router.get('/accounting/payroll/reports', { year, from, to: event.target.value })} /></div>
                </div>
                <Card>
                    <CardHeader className="flex-row items-center justify-between"><CardTitle>Month by month {year}</CardTitle><div className="flex gap-1">{exportLinks('comparison')}</div></CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Month</th><th className="text-right">Employees</th><th className="text-right">Gross</th><th className="text-right">Tax</th><th className="text-right">Net</th><th className="text-right">Employer</th><th className="text-right">Total cost</th><th className="text-right">Per head</th><th className="text-right">Change</th></tr></thead><tbody>
                            {comparison.map((row) => (<tr key={row.month} className="border-t"><td className="py-1">{row.month}</td><td className="text-right">{row.employees}</td><td className="text-right tabular-nums">{money(row.gross)}</td><td className="text-right tabular-nums">{money(row.tax)}</td><td className="text-right tabular-nums">{money(row.net)}</td><td className="text-right tabular-nums">{money(row.employer)}</td><td className="text-right font-medium tabular-nums">{money(row.total_cost)}</td><td className="text-right tabular-nums">{money(row.cost_per_employee)}</td><td className="text-right tabular-nums">{row.change === null ? '—' : `${money(row.change)} (${row.change_percent ?? 0}%)`}</td></tr>))}
                            {comparison.length === 0 && <tr><td colSpan={9} className="py-4 text-center text-muted-foreground">No posted payroll in {year}.</td></tr>}
                        </tbody></table>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-center justify-between"><CardTitle>Cost centers {from} – {to}</CardTitle><div className="flex gap-1">{exportLinks('cost-centers')}</div></CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Cost center</th><th className="text-right">Employees</th><th className="text-right">Pay</th><th className="text-right">Employer contributions</th><th className="text-right">Total cost</th></tr></thead><tbody>
                            {cost_centers.map((row) => (<tr key={row.cost_center} className="border-t"><td className="py-1">{row.cost_center}</td><td className="text-right">{row.employees}</td><td className="text-right tabular-nums">{money(row.pay)}</td><td className="text-right tabular-nums">{money(row.employer)}</td><td className="text-right font-medium tabular-nums">{money(row.total_cost)}</td></tr>))}
                            {cost_centers.length === 0 && <tr><td colSpan={5} className="py-4 text-center text-muted-foreground">Nothing posted in those months.</td></tr>}
                        </tbody></table>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-center justify-between"><CardTitle>Headcount {year}</CardTitle><div className="flex gap-1">{exportLinks('headcount')}</div></CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Month</th><th className="text-right">Joined</th><th className="text-right">Left</th><th className="text-right">On the payroll</th></tr></thead><tbody>
                            {headcount.map((row) => (<tr key={row.month} className="border-t"><td className="py-1">{row.month}</td><td className="text-right">{row.joined}</td><td className="text-right">{row.left}</td><td className="text-right font-medium">{row.on_payroll}</td></tr>))}
                        </tbody></table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PayrollReports.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Payroll', href: '/accounting/payroll' }, { title: 'Reports', href: '/accounting/payroll/reports' }] };
