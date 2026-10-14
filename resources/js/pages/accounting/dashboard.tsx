import { Head, Link } from '@inertiajs/react';
import { ArrowRight, LifeBuoy } from 'lucide-react';
import { BranchSwitcher } from '@/components/accounting/branch-switcher';
import { CompanySwitcher } from '@/components/accounting/company-switcher';
import { sectionIcons, useAccountingMenu } from '@/components/accounting/accounting-nav';
import type { MenuSection } from '@/components/accounting/accounting-nav';
import { SectionTabs } from '@/components/accounting/section-tabs';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useAccounting } from '@/lib/accounting';

type Props = {
    summary: Record<string, number>;
    section?: 'accounting' | 'settings';
    /** The sections of this part of the area, as the menu has them (what the person may open, with a one-line summary each). */
    hub?: MenuSection[];
};

const kpiLabels: Record<string, string> = {
    accountTypes: 'Account types',
    currencies: 'Currencies',
    accounts: 'Accounts',
    postedJournalEntries: 'Posted entries',
};

export default function AccountingDashboard({ summary, section = 'accounting', hub = [] }: Props) {
    const { flash, permissions } = useAccounting();
    const menu = useAccountingMenu();
    const overview = menu.find((entry) => entry.key === 'overview')?.items.find((item) => item.key === 'overview');
    const help = menu.find((entry) => entry.area === 'help')?.items[0];
    const canSeeSettings = menu.some((entry) => entry.area === 'settings') || permissions['accounting.manage-settings'] === true;

    return (
        <>
            <Head title={section === 'settings' ? 'Accounting settings' : 'Accounting'} />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title={section === 'settings' ? 'Settings' : 'Accounting'}
                        description={section === 'settings' ? 'Users, roles and permissions, and how the books are set up.' : 'Everything you can do with the books, by area. The same list is in your menu.'}
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
                    <>
                        {Object.keys(summary).length > 0 ? (
                            <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                                {Object.entries(summary).map(([key, value]) => (
                                    <div key={key} className="rounded-xl border bg-card p-4 shadow-xs">
                                        <div className="text-sm text-muted-foreground">{kpiLabels[key] ?? key}</div>
                                        <div className="mt-1 text-2xl font-semibold tabular-nums">{value}</div>
                                    </div>
                                ))}
                            </div>
                        ) : null}
                        <div className="grid gap-3 md:grid-cols-2">
                            {overview ? (
                                <Link href={overview.url} className="group flex items-center justify-between gap-4 rounded-xl border border-primary/30 bg-primary/5 p-4 transition hover:border-primary">
                                    <div>
                                        <div className="font-semibold">Overview</div>
                                        <div className="text-sm text-muted-foreground">Cash, receivables, payables, income against expense, and what needs attention today.</div>
                                    </div>
                                    <ArrowRight className="size-5 shrink-0 text-primary transition group-hover:translate-x-0.5" />
                                </Link>
                            ) : null}
                            {help ? (
                                <Link href="/accounting/help/getting-started" className="group flex items-center justify-between gap-4 rounded-xl border bg-card p-4 transition hover:border-primary">
                                    <div className="flex items-center gap-3">
                                        <LifeBuoy className="size-5 shrink-0 text-primary" />
                                        <div>
                                            <div className="font-semibold">New here? Getting started</div>
                                            <div className="text-sm text-muted-foreground">Where things are and the order to do them in. Every screen also has a Help button.</div>
                                        </div>
                                    </div>
                                    <ArrowRight className="size-5 shrink-0 text-primary transition group-hover:translate-x-0.5" />
                                </Link>
                            ) : null}
                        </div>
                    </>
                ) : null}
                {hub.map((group) => {
                    const Icon = sectionIcons[group.icon];

                    return (
                        <section key={group.key} className="space-y-3">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                {Icon ? <Icon className="size-4 text-muted-foreground" /> : null}
                                {group.title}
                            </h2>
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {group.items.map((item) => (
                                    <Link key={item.key} href={item.url} className="group flex flex-col gap-1 rounded-xl border bg-card p-4 shadow-xs transition hover:-translate-y-0.5 hover:border-primary">
                                        <span className="flex items-center justify-between font-medium">
                                            {item.title}
                                            <ArrowRight className="size-4 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                                        </span>
                                        {item.summary ? <span className="text-sm leading-snug text-muted-foreground">{item.summary}</span> : null}
                                    </Link>
                                ))}
                            </div>
                        </section>
                    );
                })}
            </div>
        </>
    );
}

AccountingDashboard.layout = {
    breadcrumbs: [{ title: 'Accounting', href: '/accounting' }],
};
