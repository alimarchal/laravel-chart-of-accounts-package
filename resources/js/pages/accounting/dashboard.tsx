import { Head, Link } from '@inertiajs/react';
import { BarChart3, BookOpen, Building2, CalendarDays, FileText, Landmark, Percent, ReceiptText, Scale, WalletCards } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    summary: Record<string, number>;
};

export default function AccountingDashboard({ summary }: Props) {
    const sections = [
        { title: 'Chart of Accounts', href: '/accounting/chart-of-accounts', icon: BookOpen },
        { title: 'Journal Entries', href: '/accounting/journal-entries', icon: ReceiptText },
        { title: 'Account Types', href: '/accounting/account-types', icon: Scale },
        { title: 'Currencies', href: '/accounting/currencies', icon: WalletCards },
        { title: 'Periods', href: '/accounting/periods', icon: CalendarDays },
        { title: 'Cost Centers', href: '/accounting/cost-centers', icon: Building2 },
        { title: 'Bank Accounts', href: '/accounting/bank-accounts', icon: Landmark },
        { title: 'Reconciliations', href: '/accounting/reconciliations', icon: FileText },
        { title: 'Tax Codes', href: '/accounting/tax-codes', icon: Percent },
        { title: 'Tax Rates', href: '/accounting/tax-rates', icon: Percent },
        { title: 'Snapshots', href: '/accounting/account-balance-snapshots', icon: BarChart3 },
        { title: 'General Ledger', href: '/accounting/reports/general-ledger', icon: BarChart3 },
        { title: 'Trial Balance', href: '/accounting/reports/trial-balance', icon: BarChart3 },
        { title: 'Balance Sheet', href: '/accounting/reports/balance-sheet', icon: BarChart3 },
        { title: 'Income Statement', href: '/accounting/reports/income-statement', icon: BarChart3 },
        { title: 'Cash Flow', href: '/accounting/reports/cash-flow', icon: BarChart3 },
        { title: 'Aged Receivables', href: '/accounting/reports/aged-receivables', icon: BarChart3 },
        { title: 'Aged Payables', href: '/accounting/reports/aged-payables', icon: BarChart3 },
        { title: 'Account Statement', href: '/accounting/reports/account-statement', icon: FileText },
        { title: 'Audit Logs', href: '/accounting/audit-logs', icon: FileText },
    ];

    return (
        <>
            <Head title="Accounting" />
            <div className="space-y-6 p-4">
                <Heading title="Accounting" description="General ledger, chart of accounts, periods, and financial reports." />
                <div className="grid gap-3 md:grid-cols-4">
                    {Object.entries(summary).map(([label, value]) => (
                        <Card key={label} className="rounded-lg">
                            <CardContent className="p-4">
                                <div className="text-sm text-muted-foreground">{label}</div>
                                <div className="mt-2 text-2xl font-semibold">{value}</div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
                    {sections.map((section) => (
                        <Button key={section.href} asChild variant="outline" className="h-auto justify-start rounded-lg p-4">
                            <Link href={section.href}>
                                <section.icon className="size-4" />
                                {section.title}
                            </Link>
                        </Button>
                    ))}
                </div>
            </div>
        </>
    );
}

AccountingDashboard.layout = {
    breadcrumbs: [{ title: 'Accounting', href: '/accounting' }],
};
