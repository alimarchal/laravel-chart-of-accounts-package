import { Link, usePage } from '@inertiajs/react';
import { BarChart3, BookOpen, ChevronRight, Landmark, LayoutGrid, LifeBuoy, Package, Receipt, Settings2, ShieldCheck, Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';

export type MenuItem = { key: string; title: string; url: string; active: boolean; help: string; summary?: string };
export type MenuSection = { key: string; title: string; area: 'accounting' | 'settings' | 'help'; icon: string; open: boolean; items: MenuItem[] };

export const sectionIcons: Record<string, LucideIcon> = {
    overview: LayoutGrid,
    journal: BookOpen,
    sales: Receipt,
    banking: Landmark,
    payroll: Users,
    assets: Package,
    reports: BarChart3,
    access: ShieldCheck,
    setup: Settings2,
    help: LifeBuoy,
};

/** The sections the person may see, as the server worked them out (permissions, switched-off modules, the screen they are on). */
export function useAccountingMenu(): MenuSection[] {
    return (usePage().props as { accountingMenu?: MenuSection[] }).accountingMenu ?? [];
}

/**
 * The accounting menu for the sidebar of the React starter kit: one "Accounting" block whose sections open and close, then the
 * settings sections and the help centre. Add `<AccountingNav />` below `<NavMain />` in `app-sidebar.tsx`.
 */
export function AccountingNav() {
    const menu = useAccountingMenu();

    if (menu.length === 0) {
        return null;
    }

    const top = menu.find((section) => section.key === 'overview');
    const sections = menu.filter((section) => section.key !== 'overview' && section.area === 'accounting');
    const settings = menu.filter((section) => section.area === 'settings');
    const help = menu.find((section) => section.area === 'help');

    const group = (section: MenuSection) => {
        const Icon = sectionIcons[section.icon] ?? BookOpen;

        return (
            <Collapsible key={section.key} asChild defaultOpen={section.open} className="group/collapsible">
                <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuButton tooltip={{ children: section.title }} isActive={section.open}>
                            <Icon />
                            <span>{section.title}</span>
                            <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            {section.items.map((item) => (
                                <SidebarMenuSubItem key={item.key}>
                                    <SidebarMenuSubButton asChild isActive={item.active}>
                                        <Link href={item.url} prefetch>
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            ))}
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        );
    };

    return (
        <>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>Accounting</SidebarGroupLabel>
                <SidebarMenu>
                    {top?.items.map((item) => (
                        <SidebarMenuItem key={item.key}>
                            <SidebarMenuButton asChild isActive={item.active} tooltip={{ children: item.title }}>
                                <Link href={item.url} prefetch>
                                    <LayoutGrid />
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                    {sections.map(group)}
                </SidebarMenu>
            </SidebarGroup>
            {settings.length > 0 && (
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>Accounting settings</SidebarGroupLabel>
                    <SidebarMenu>{settings.map(group)}</SidebarMenu>
                </SidebarGroup>
            )}
            {help && (
                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        {help.items.map((item) => (
                            <SidebarMenuItem key={item.key}>
                                <SidebarMenuButton asChild isActive={item.active} tooltip={{ children: item.title }}>
                                    <Link href={item.url} prefetch>
                                        <LifeBuoy />
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            )}
        </>
    );
}

/** The same menu for the header layout of the React starter kit: one "Accounting" dropdown with the sections side by side. */
export function AccountingHeaderMenu() {
    const menu = useAccountingMenu();

    if (menu.length === 0) {
        return null;
    }

    const current = menu.some((section) => section.items.some((item) => item.active));
    const helpSection = menu.find((section) => section.area === 'help');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={`inline-flex h-9 cursor-pointer items-center gap-1 rounded-md px-3 text-sm font-medium hover:bg-accent hover:text-accent-foreground ${current ? 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100' : ''}`}
                >
                    Accounting
                    <ChevronRight className="size-4 rotate-90" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="grid w-[min(58rem,92vw)] grid-cols-2 gap-x-4 gap-y-2 p-3 md:grid-cols-4">
                {menu
                    .filter((section) => section.area !== 'help')
                    .map((section) => (
                        <div key={section.key}>
                            <DropdownMenuLabel className="text-xs tracking-wide text-muted-foreground uppercase">
                                {section.title}
                                {section.area === 'settings' ? ' · Settings' : ''}
                            </DropdownMenuLabel>
                            {section.items.map((item) => (
                                <Link
                                    key={item.key}
                                    href={item.url}
                                    className={`block rounded-sm px-2 py-1.5 text-sm hover:bg-accent ${item.active ? 'bg-accent font-semibold text-primary' : ''}`}
                                >
                                    {item.title}
                                </Link>
                            ))}
                        </div>
                    ))}
                {helpSection && (
                    <div className="col-span-full mt-1 flex items-center justify-between border-t pt-2 text-xs text-muted-foreground">
                        <span>Every screen has a Help button with a short guide.</span>
                        <Link href={helpSection.items[0].url} className="font-semibold text-primary hover:underline">
                            Open the help centre →
                        </Link>
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** The same menu inside the mobile sheet of the header layout. */
export function AccountingMobileMenu() {
    const menu = useAccountingMenu();

    if (menu.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col space-y-2 border-t pt-4">
            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Accounting</span>
            {menu.map((section) => (
                <details key={section.key} open={section.open} className="group">
                    <summary className="cursor-pointer list-none py-1.5 font-medium">{section.title}</summary>
                    <div className="flex flex-col pl-3">
                        {section.items.map((item) => (
                            <Link key={item.key} href={item.url} className={`py-1 ${item.active ? 'font-semibold text-primary' : 'text-muted-foreground'}`}>
                                {item.title}
                            </Link>
                        ))}
                    </div>
                </details>
            ))}
        </div>
    );
}

export default AccountingNav;
