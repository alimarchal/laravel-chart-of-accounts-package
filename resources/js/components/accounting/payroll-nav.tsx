import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { linkEnabled, useAccounting } from '@/lib/accounting';

type Item = { title: string; href: string; manage?: boolean; match?: string[] };

const groups: Item[][] = [
    [{ title: 'Runs', href: '/accounting/payroll', match: ['/accounting/payroll/runs'] }],
    [
        { title: 'Employees', href: '/accounting/payroll/employees' },
        { title: 'Grades', href: '/accounting/payroll/grades' },
        { title: 'Pay items', href: '/accounting/payroll/components' },
        { title: 'Contributions', href: '/accounting/payroll/schemes' },
        { title: 'Bulk changes', href: '/accounting/payroll/bulk', manage: true },
    ],
    [
        { title: 'Attendance', href: '/accounting/payroll/attendance' },
        { title: 'Leave', href: '/accounting/payroll/leaves', match: ['/accounting/payroll/leave-types'] },
        { title: 'Loans', href: '/accounting/payroll/loans' },
        { title: 'Arrears', href: '/accounting/payroll/arrears' },
        { title: 'Bonuses', href: '/accounting/payroll/adjustments' },
        { title: 'Settlements', href: '/accounting/payroll/settlements' },
    ],
    [
        { title: 'Reports', href: '/accounting/payroll/reports' },
        { title: 'Tax', href: '/accounting/payroll/tax' },
    ],
];

/** The payroll area as one tab strip: runs, people, pay set-up, time and money owed, reports. */
export function PayrollNav() {
    const { url } = usePage();
    const { permissions, disabledPaths } = useAccounting();
    const path = url.split('?')[0].replace(/\/$/, '');
    const isActive = (item: Item) =>
        item.href === '/accounting/payroll'
            ? path === item.href || (item.match ?? []).some((prefix) => path.startsWith(prefix)) || path.startsWith('/accounting/payroll/payslips')
            : path === item.href || path.startsWith(item.href + '/') || (item.match ?? []).some((prefix) => path.startsWith(prefix));
    const visible = groups
        .map((group) => group.filter((item) => linkEnabled(item.href, disabledPaths) && (!item.manage || permissions['payroll.manage'])))
        .filter((group) => group.length > 0);

    return (
        <nav className="flex flex-wrap items-center gap-1 rounded-xl bg-muted/60 p-1 print:hidden" aria-label="Payroll">
            {visible.map((group, index) => (
                <div key={index} className="flex flex-wrap items-center gap-1">
                    {index > 0 && <span className="mx-1 h-5 w-px bg-border" aria-hidden />}
                    {group.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            aria-current={isActive(item) ? 'page' : undefined}
                            className={`rounded-lg px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors ${isActive(item) ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:bg-background/60 hover:text-foreground'}`}
                        >
                            {item.title}
                        </Link>
                    ))}
                </div>
            ))}
        </nav>
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
