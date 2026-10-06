import { Head, Link } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    Building2,
    CalendarDays,
    FileText,
    Hash,
    KeyRound,
    Landmark,
    Percent,
    ReceiptText,
    ShieldCheck,
    Scale,
    Users,
    WalletCards,
} from 'lucide-react';
import { CompanySwitcher } from '@/components/accounting/company-switcher';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Props = {
    summary: Record<string, number>;
};

export default function AccountingDashboard({ summary }: Props) {
    const { company, permissions, flash } = useAccounting();
    const sections = [
        {
            title: 'Chart of Accounts',
            href: '/accounting/chart-of-accounts',
            icon: BookOpen,
        },
        {
            title: 'Journal Entries',
            href: '/accounting/journal-entries',
            icon: ReceiptText,
        },
        {
            title: 'Account Types',
            href: '/accounting/account-types',
            icon: Scale,
        },
        {
            title: 'Currencies',
            href: '/accounting/currencies',
            icon: WalletCards,
        },
        { title: 'Periods', href: '/accounting/periods', icon: CalendarDays },
        {
            title: 'Cost Centers',
            href: '/accounting/cost-centers',
            icon: Building2,
        },
        {
            title: 'Bank Accounts',
            href: '/accounting/bank-accounts',
            icon: Landmark,
        },
        {
            title: 'Reconciliations',
            href: '/accounting/reconciliations',
            icon: FileText,
        },
        { title: 'Tax Codes', href: '/accounting/tax-codes', icon: Percent },
        { title: 'Tax Rates', href: '/accounting/tax-rates', icon: Percent },
        {
            title: 'Control Accounts',
            href: '/accounting/control-accounts',
            icon: ShieldCheck,
        },
        {
            title: 'Voucher Types',
            href: '/accounting/voucher-types',
            icon: Hash,
        },
        {
            title: 'Snapshots',
            href: '/accounting/account-balance-snapshots',
            icon: BarChart3,
        },
        {
            title: 'General Ledger',
            href: '/accounting/reports/general-ledger',
            icon: BarChart3,
        },
        {
            title: 'Trial Balance',
            href: '/accounting/reports/trial-balance',
            icon: BarChart3,
        },
        {
            title: 'Balance Sheet',
            href: '/accounting/reports/balance-sheet',
            icon: BarChart3,
        },
        {
            title: 'Income Statement',
            href: '/accounting/reports/income-statement',
            icon: BarChart3,
        },
        {
            title: 'Cash Flow',
            href: '/accounting/reports/cash-flow',
            icon: BarChart3,
        },
        ...(permissions['reports.financial-statements.view']
            ? [
                  {
                      title: 'Financial Statements',
                      href: '/accounting/reports/financial-statements',
                      icon: BarChart3,
                  },
              ]
            : []),
        ...(permissions['report-mapping.manage']
            ? [
                  {
                      title: 'Report Mapping',
                      href: '/accounting/report-mapping',
                      icon: FileText,
                  },
              ]
            : []),
        {
            title: 'Aged Receivables',
            href: '/accounting/reports/aged-receivables',
            icon: BarChart3,
        },
        {
            title: 'Aged Payables',
            href: '/accounting/reports/aged-payables',
            icon: BarChart3,
        },
        {
            title: 'Account Statement',
            href: '/accounting/reports/account-statement',
            icon: FileText,
        },
        { title: 'Audit Logs', href: '/accounting/audit-logs', icon: FileText },
        ...(company.enabled && permissions['reports.consolidated.view']
            ? [
                  {
                      title: 'Consolidated Reports',
                      href: '/accounting/reports/consolidated',
                      icon: Building2,
                  },
              ]
            : []),
        ...(permissions['recurring-entries.view']
            ? [
                  {
                      title: 'Recurring Entries',
                      href: '/accounting/recurring-entries',
                      icon: FileText,
                  },
              ]
            : []),
        ...(permissions['fx-revaluation.view']
            ? [
                  {
                      title: 'Currency Revaluation',
                      href: '/accounting/fx-revaluation',
                      icon: FileText,
                  },
              ]
            : []),
        ...(permissions['bank-statements.view']
            ? [
                  {
                      title: 'Bank Statements',
                      href: '/accounting/bank-statements',
                      icon: FileText,
                  },
              ]
            : []),
        ...(permissions['budgets.view']
            ? [{ title: 'Budgets', href: '/accounting/budgets', icon: FileText }]
            : []),
        { title: 'My Exports', href: '/accounting/exports', icon: FileText },
        ...(permissions['user.view']
            ? [{ title: 'Users', href: '/accounting/users', icon: Users }]
            : []),
        ...(permissions['accounting.manage-settings']
            ? [{ title: 'Roles', href: '/accounting/roles', icon: KeyRound }]
            : []),
        ...(company.enabled && permissions['companies.manage']
            ? [
                  {
                      title: 'Companies',
                      href: '/accounting/companies',
                      icon: Building2,
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title="Accounting" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title="Accounting"
                        description="General ledger, chart of accounts, periods, and financial reports."
                    />
                    <CompanySwitcher />
                </div>
                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                <div className="grid gap-3 md:grid-cols-4">
                    {Object.entries(summary).map(([label, value]) => (
                        <Card key={label} className="rounded-lg">
                            <CardContent className="p-4">
                                <div className="text-sm text-muted-foreground">
                                    {label}
                                </div>
                                <div className="mt-2 text-2xl font-semibold">
                                    {value}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
                    {sections.map((section) => (
                        <Button
                            key={section.href}
                            asChild
                            variant="outline"
                            className="h-auto justify-start rounded-lg p-4"
                        >
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
