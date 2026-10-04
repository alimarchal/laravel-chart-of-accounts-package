import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ChevronLeft, ChevronRight, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type LedgerLine = {
    journal_entry_id: number;
    entry_date: string;
    reference: string | null;
    voucher_number?: string | null;
    source_document_number?: string | null;
    journal_description: string | null;
    line_description: string | null;
    account_code: string;
    account_name: string;
    status: string;
    debit: string | number;
    credit: string | number;
    base_debit: string | number;
    base_credit: string | number;
    currency_code: string | null;
    running_balance?: string | number;
};

export function money(value: number | string | null | undefined): string {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/** Query string from non-empty filter values. */
export function queryString(
    filters: Record<string, string | number | null | undefined>,
): string {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });

    const query = params.toString();

    return query ? `?${query}` : '';
}

export function ExportButtons({
    base,
    filters,
}: {
    base: string;
    filters: Record<string, string | number | null | undefined>;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {['csv', 'xlsx', 'pdf'].map((format) => (
                <Button key={format} asChild variant="outline" size="sm">
                    {/* A plain anchor: downloads must not go through Inertia's XHR visits. */}
                    <a href={`${base}/${format}${queryString(filters)}`}>
                        <Download className="size-4" />
                        {format.toUpperCase()}
                    </a>
                </Button>
            ))}
        </div>
    );
}

export function Pagination<T>({
    page,
    label = 'lines',
}: {
    page: Paginated<T>;
    label?: string;
}) {
    if (!page.total) {
        return null;
    }

    return (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
                Showing {page.from}–{page.to} of {page.total.toLocaleString()}{' '}
                {label}
            </span>
            <div className="flex items-center gap-2">
                <PageButton href={page.prev_page_url}>
                    <ChevronLeft className="size-4" /> Previous
                </PageButton>
                <span>
                    Page {page.current_page} of {page.last_page}
                </span>
                <PageButton href={page.next_page_url}>
                    Next <ChevronRight className="size-4" />
                </PageButton>
            </div>
        </div>
    );
}

function PageButton({
    href,
    children,
}: {
    href: string | null;
    children: ReactNode;
}) {
    return href ? (
        <Button asChild variant="outline" size="sm">
            <Link href={href} preserveScroll>
                {children}
            </Link>
        </Button>
    ) : (
        <Button variant="outline" size="sm" disabled>
            {children}
        </Button>
    );
}

export function SummaryCards({
    items,
}: {
    items: Array<{
        label: string;
        value: string | number;
        tone?: 'positive' | 'negative';
    }>;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {items.map((item) => (
                <div key={item.label} className="rounded-lg border p-4">
                    <div className="text-sm text-muted-foreground">
                        {item.label}
                    </div>
                    <div
                        className={`text-xl font-semibold tabular-nums ${item.tone === 'negative' ? 'text-red-600' : ''}`}
                    >
                        {money(item.value)}
                    </div>
                </div>
            ))}
        </div>
    );
}
