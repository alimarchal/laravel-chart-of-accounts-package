import { Head, Link } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    Building2,
    CalendarDays,
    FileText,
    GitBranch,
    Hash,
    KeyRound,
    Landmark,
    MessageCircle,
    Percent,
    ReceiptText,
    Scale,
    Settings2,
    ShieldCheck,
    Users,
    WalletCards,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { BranchSwitcher } from '@/components/accounting/branch-switcher';
import { CompanySwitcher } from '@/components/accounting/company-switcher';
import { SectionTabs } from '@/components/accounting/section-tabs';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { linkEnabled, useAccounting } from '@/lib/accounting';

type Props = {
    summary: Record<string, number>;
    section?: 'accounting' | 'settings';
};

type Item = {
    title: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
    /** The permission that opens the screen (the server answers 403 without it). */
    can?: string;
    /** Extra condition: a module that is switched on (companies, branches). */
    when?: boolean;
};

type Group = { title: string; description?: string; items: Item[] };

export default function AccountingDashboard({ summary, section = 'accounting' }: Props) {
    const { branch, company, permissions, flash, disabledPaths } = useAccounting();

    // Accounting: the daily work. Settings: how the books are set up, and who may do what.
    const accounting: Group[] = [
        {
            title: 'Journal & ledger',
            items: [
                { title: 'Journal Entries', href: '/accounting/journal-entries', icon: ReceiptText, can: 'journal-entries.view' },
                { title: 'Chart of Accounts', href: '/accounting/chart-of-accounts', icon: BookOpen, can: 'chart-of-accounts.view' },
                { title: 'Recurring Entries', href: '/accounting/recurring-entries', icon: FileText, can: 'recurring-entries.view' },
                { title: 'Currency Revaluation', href: '/accounting/fx-revaluation', icon: FileText, can: 'fx-revaluation.view' },
                { title: 'Balance Snapshots', href: '/accounting/account-balance-snapshots', icon: Hash, can: 'account-balance-snapshots.view' },
            ],
        },
        {
            title: 'Reports',
            items: [
                { title: 'Overview (KPIs & charts)', href: '/accounting/overview', icon: BarChart3, can: 'reports.trial-balance.view' },
                { title: 'General Ledger', href: '/accounting/reports/general-ledger', icon: BookOpen, can: 'reports.general-ledger.view' },
                { title: 'Trial Balance', href: '/accounting/reports/trial-balance', icon: BarChart3, can: 'reports.trial-balance.view' },
                { title: 'Balance Sheet', href: '/accounting/reports/balance-sheet', icon: BarChart3, can: 'reports.balance-sheet.view' },
                { title: 'Income Statement', href: '/accounting/reports/income-statement', icon: BarChart3, can: 'reports.income-statement.view' },
                { title: 'Cash Flow', href: '/accounting/reports/cash-flow', icon: BarChart3, can: 'reports.cash-flow.view' },
                { title: 'Financial Statements', href: '/accounting/reports/financial-statements', icon: BarChart3, can: 'reports.financial-statements.view' },
                { title: 'Account Statement', href: '/accounting/reports/account-statement', icon: FileText, can: 'reports.account-statement.view' },
                { title: 'Aged Receivables', href: '/accounting/reports/aged-receivables', icon: BarChart3, can: 'reports.aged-receivables.view' },
                { title: 'Aged Payables', href: '/accounting/reports/aged-payables', icon: BarChart3, can: 'reports.aged-payables.view' },
                { title: 'Consolidated Reports', href: '/accounting/reports/consolidated', icon: Building2, can: 'reports.consolidated.view', when: company.enabled },
                { title: 'My Exports', href: '/accounting/exports', icon: FileText },
            ],
        },
        {
            title: 'Customers & suppliers',
            items: [
                { title: 'Customers & Suppliers', href: '/accounting/parties', icon: Users, can: 'parties.view' },
                { title: 'Invoices & Bills', href: '/accounting/party-documents', icon: FileText, can: 'parties.view' },
                { title: 'Receipts & Payments', href: '/accounting/party-payments', icon: FileText, can: 'parties.view' },
                { title: 'Tax', href: '/accounting/tax', icon: FileText, can: 'tax-returns.view' },
            ],
        },
        {
            title: 'Banking',
            items: [
                { title: 'Bank Accounts', href: '/accounting/bank-accounts', icon: Landmark, can: 'bank-accounts.view' },
                { title: 'Reconciliations', href: '/accounting/reconciliations', icon: FileText, can: 'reconciliations.view' },
                { title: 'Bank Statements', href: '/accounting/bank-statements', icon: FileText, can: 'bank-statements.view' },
            ],
        },
        {
            title: 'Operations',
            items: [
                { title: 'Payroll', href: '/accounting/payroll', icon: Landmark, can: 'payroll.view' },
                { title: 'Inventory', href: '/accounting/inventory', icon: Landmark, can: 'inventory.view' },
                { title: 'Fixed Assets', href: '/accounting/fixed-assets', icon: Landmark, can: 'fixed-assets.view' },
                { title: 'Budgets', href: '/accounting/budgets', icon: FileText, can: 'budgets.view' },
                { title: 'FBR Invoices', href: '/accounting/fbr', icon: ReceiptText, can: 'fbr.view' },
                { title: 'WhatsApp', href: '/accounting/whatsapp', icon: MessageCircle, can: 'whatsapp.view' },
            ],
        },
    ];

    const settings: Group[] = [
        {
            title: 'Users & access',
            description: 'Create users, give them roles, extra permissions and branches; create roles and choose what each role may do.',
            items: [
                { title: 'Users', href: '/accounting/users', icon: Users, can: 'user.view' },
                { title: 'Roles & permissions', href: '/accounting/roles', icon: KeyRound, can: 'accounting.manage-settings' },
                { title: 'Branches', href: '/accounting/branches', icon: GitBranch, can: 'branches.view', when: branch.enabled },
                { title: 'Companies', href: '/accounting/companies', icon: Building2, can: 'companies.manage', when: company.enabled },
            ],
        },
        {
            title: 'Setup',
            description: 'How the books are set up.',
            items: [
                { title: 'Features', href: '/accounting/features', icon: ShieldCheck, can: 'accounting.manage-settings' },
                { title: 'Account roles', href: '/accounting/account-roles', icon: KeyRound, can: 'accounting.manage-settings' },
                { title: 'Currencies', href: '/accounting/currencies', icon: WalletCards, can: 'currencies.view' },
                { title: 'Periods', href: '/accounting/periods', icon: CalendarDays, can: 'periods.view' },
                { title: 'Account Types', href: '/accounting/account-types', icon: Scale, can: 'account-types.view' },
                { title: 'Cost Centers', href: '/accounting/cost-centers', icon: Building2, can: 'cost-centers.view' },
                { title: 'Tax Codes', href: '/accounting/tax-codes', icon: Percent, can: 'tax-codes.view' },
                { title: 'Tax Rates', href: '/accounting/tax-rates', icon: Percent, can: 'tax-rates.view' },
                { title: 'Voucher Types', href: '/accounting/voucher-types', icon: Hash, can: 'voucher-types.view' },
                { title: 'Control Accounts', href: '/accounting/control-accounts', icon: ShieldCheck, can: 'control-accounts.manage' },
                { title: 'Report Mapping', href: '/accounting/report-mapping', icon: FileText, can: 'report-mapping.manage' },
            ],
        },
        {
            title: 'Audit',
            items: [{ title: 'Audit Logs', href: '/accounting/audit-logs', icon: FileText, can: 'audit-logs.view' }],
        },
    ];

    // A link only for what the user may open, and only when its module is on.
    const visible = (groups: Group[]): Group[] =>
        groups
            .map((group) => ({
                ...group,
                items: group.items.filter(
                    (item) =>
                        item.when !== false &&
                        linkEnabled(item.href, disabledPaths) &&
                        (!item.can || permissions[item.can]),
                ),
            }))
            .filter((group) => group.items.length > 0);

    const groups = visible(section === 'settings' ? settings : accounting);
    const canSeeSettings = visible(settings).length > 0;

    return (
        <>
            <Head title={section === 'settings' ? 'Accounting settings' : 'Accounting'} />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title={section === 'settings' ? 'Settings' : 'Accounting'}
                        description={
                            section === 'settings'
                                ? 'Users, roles and permissions, and how the books are set up.'
                                : 'General ledger, chart of accounts, periods, and financial reports.'
                        }
                    />
                    <div className="flex flex-wrap items-center gap-3">
                        <CompanySwitcher />
                        <BranchSwitcher />
                    </div>
                </div>
                <SectionTabs active={section} showSettings={canSeeSettings} />
                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                {section === 'accounting' ? (
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
                ) : null}
                {groups.map((group) => (
                    <div key={group.title} className="space-y-2">
                        <div>
                            <div className="flex items-center gap-2 text-sm font-semibold">
                                {section === 'settings' && group.title === 'Users & access' ? <Settings2 className="size-4" /> : null}
                                {group.title}
                            </div>
                            {group.description ? <p className="text-xs text-muted-foreground">{group.description}</p> : null}
                        </div>
                        <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
                            {group.items.map((item) => (
                                <Button key={item.href} asChild variant="outline" className="h-auto justify-start rounded-lg p-4">
                                    <Link href={item.href}>
                                        <item.icon className="size-4" />
                                        {item.title}
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}

AccountingDashboard.layout = {
    breadcrumbs: [{ title: 'Accounting', href: '/accounting' }],
};
