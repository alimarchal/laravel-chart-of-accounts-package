import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { linkEnabled, useAccounting } from '@/lib/accounting';

type Item = { title: string; href: string; manage?: boolean; match?: string[] };
type Group = { title: string; items: Item[] };

const groups: Group[] = [
    { title: 'Runs', items: [{ title: 'Runs', href: '/accounting/payroll', match: ['/accounting/payroll/runs', '/accounting/payroll/payslips'] }] },
    {
        title: 'People & pay',
        items: [
            { title: 'Employees', href: '/accounting/payroll/employees' },
            { title: 'Grades', href: '/accounting/payroll/grades' },
            { title: 'Pay items', href: '/accounting/payroll/components' },
            { title: 'Contributions', href: '/accounting/payroll/schemes' },
            { title: 'Bulk changes', href: '/accounting/payroll/bulk', manage: true },
        ],
    },
    {
        title: 'Attendance & leave',
        items: [
            { title: 'Attendance', href: '/accounting/payroll/attendance' },
            { title: 'Leave', href: '/accounting/payroll/leaves', match: ['/accounting/payroll/leave-types'] },
        ],
    },
    {
        title: 'Loans & extras',
        items: [
            { title: 'Loans', href: '/accounting/payroll/loans' },
            { title: 'Arrears', href: '/accounting/payroll/arrears' },
            { title: 'Bonuses', href: '/accounting/payroll/adjustments' },
            { title: 'Settlements', href: '/accounting/payroll/settlements' },
        ],
    },
    {
        title: 'Reports & tax',
        items: [
            { title: 'Reports', href: '/accounting/payroll/reports' },
            { title: 'Tax', href: '/accounting/payroll/tax' },
        ],
    },
];

/**
 * The payroll area as five tabs; the choices inside a tab (employees, grades, loans …) show only once you are in it, so nobody
 * faces fourteen links at once.
 */
export function PayrollNav() {
    const { url } = usePage();
    const { permissions, disabledPaths } = useAccounting();
    const path = url.split('?')[0].replace(/\/$/, '');
    const isActive = (item: Item) =>
        item.href === '/accounting/payroll'
            ? path === item.href || (item.match ?? []).some((prefix) => path.startsWith(prefix))
            : path === item.href || path.startsWith(item.href + '/') || (item.match ?? []).some((prefix) => path.startsWith(prefix));
    const visible = groups
        .map((group) => ({ ...group, items: group.items.filter((item) => linkEnabled(item.href, disabledPaths) && (!item.manage || permissions['payroll.manage'])) }))
        .filter((group) => group.items.length > 0);
    const current = visible.find((group) => group.items.some(isActive));

    return (
        <div className="space-y-2 print:hidden">
            <nav className="flex flex-wrap items-center gap-1 rounded-xl bg-muted/60 p-1" aria-label="Payroll">
                {visible.map((group) => (
                    <Link
                        key={group.title}
                        href={group.items[0].href}
                        aria-current={current?.title === group.title ? 'page' : undefined}
                        className={`rounded-lg px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors ${current?.title === group.title ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:bg-background/60 hover:text-foreground'}`}
                    >
                        {group.title}
                    </Link>
                ))}
            </nav>
            {current && current.items.length > 1 && (
                <nav className="flex flex-wrap gap-1 border-b" aria-label={current.title}>
                    {current.items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            aria-current={isActive(item) ? 'page' : undefined}
                            className={`-mb-px border-b-2 px-3 py-1.5 text-sm font-medium whitespace-nowrap ${isActive(item) ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                        >
                            {item.title}
                        </Link>
                    ))}
                </nav>
            )}
        </div>
    );
}

/** Title, optional actions and the payroll tab strip: the top of every payroll screen. */
export function PayrollHeader({ title, description, actions, nav = true }: { title: ReactNode; description?: string; actions?: ReactNode; nav?: boolean }) {
    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <Heading title={title as string} description={description} />
                {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
            </div>
            {nav && <PayrollNav />}
        </div>
    );
}

export default PayrollNav;
