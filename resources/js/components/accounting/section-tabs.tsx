import { Link } from '@inertiajs/react';

/**
 * The two parts of the accounting area: "Accounting" (the daily work) and "Settings" (users, roles, permissions and how the books are set up).
 * Shown on both hubs; the Settings tab only for people who may open something in it.
 */
export function SectionTabs({ active, showSettings = true }: { active: 'accounting' | 'settings'; showSettings?: boolean }) {
    const tabs = [
        { key: 'accounting', title: 'Accounting', href: '/accounting' },
        ...(showSettings ? [{ key: 'settings', title: 'Settings', href: '/accounting/settings' }] : []),
    ];

    return (
        <nav className="flex gap-1 border-b" aria-label="Accounting sections">
            {tabs.map((tab) => (
                <Link
                    key={tab.key}
                    href={tab.href}
                    className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${active === tab.key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                    aria-current={active === tab.key ? 'page' : undefined}
                >
                    {tab.title}
                </Link>
            ))}
        </nav>
    );
}

export default SectionTabs;
