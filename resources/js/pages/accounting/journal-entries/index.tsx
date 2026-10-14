import { Head, Link, router, useForm } from '@inertiajs/react';
import { Edit, Filter, Plus, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { SearchableSelect } from '@/components/accounting/searchable-select';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    useAccounting,
    playErrorSound,
    playSuccessSound,
} from '@/lib/accounting';
import { Dropdown } from '@/components/accounting/dropdown';

type Entry = {
    id: number;
    voucher_number: string | null;
    source_document_number: string | null;
    voucher_type: { id: number; code: string; name: string } | null;
    branch?: { id: number; code: string; name: string } | null;
    entry_date: string;
    reference: string | null;
    description: string | null;
    status: string;
    approval_status?: string | null;
    total_debit?: string | number | null;
    lines_count?: number;
    currency?: { id: number; code: string } | null;
};

const statusStyle: Record<string, string> = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    posted: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    void: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300',
    pending: 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300',
};

function StatusPill({ status, label }: { status: string; label?: string }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold ${statusStyle[status] ?? 'bg-muted text-muted-foreground'}`}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {label ?? status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
}

type Props = {
    entries: {
        data: Entry[];
        links?: Array<{ url: string | null; label: string; active: boolean }>;
        from?: number | null;
        to?: number | null;
        total?: number;
    };
    filters: Record<string, string | undefined>;
    currencies: Array<{ id: number; code: string; name: string }>;
    branches?: Array<{ id: number; code: string; name: string }>;
};

export default function JournalEntriesIndex({
    entries,
    filters,
    currencies,
    branches = [],
}: Props) {
    const { permissions, flash } = useAccounting();
    const hasFilters = Object.values(filters ?? {}).some(
        (value) => value && value !== 'all',
    );
    const [filtersOpen, setFiltersOpen] = useState(hasFilters);
    const filterForm = useForm({
        branch_id: filters.branch_id ?? '',
        voucher_number: filters.voucher_number ?? '',
        source_document_number: filters.source_document_number ?? '',
        reference: filters.reference ?? '',
        description: filters.description ?? '',
        status: filters.status ?? 'all',
        currency_id: filters.currency_id ?? 'all',
        entry_date_from: filters.entry_date_from ?? '',
        entry_date_to: filters.entry_date_to ?? '',
    });
    const activeFilterCount = Object.values(filterForm.data).filter(
        (value) => value !== '' && value !== 'all',
    ).length;
    const currencyOptions = useMemo(
        () =>
            currencies.map((currency) => ({
                value: String(currency.id),
                label: `${currency.code} - ${currency.name}`,
            })),
        [currencies],
    );

    useEffect(() => {
        if (flash.success) {
            playSuccessSound();
        }
    }, [flash.success]);

    useEffect(() => {
        if (flash.error) {
            playErrorSound();
        }
    }, [flash.error]);

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const params = Object.fromEntries(
            Object.entries(filterForm.data)
                .filter(([, value]) => value !== '' && value !== 'all')
                .map(([key, value]) => [`filter[${key}]`, value]),
        );

        router.get('/accounting/journal-entries', params, {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        filterForm.setData({
            branch_id: '',
            voucher_number: '',
            source_document_number: '',
            reference: '',
            description: '',
            status: 'all',
            currency_id: 'all',
            entry_date_from: '',
            entry_date_to: '',
        });
        router.get(
            '/accounting/journal-entries',
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Journal Entries" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title="Journal Entries"
                        description="Draft, post, reverse, and review ledger entries."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant={filtersOpen ? 'secondary' : 'outline'}
                            className="relative gap-2"
                            onClick={() => setFiltersOpen((open) => !open)}
                        >
                            <Filter className="size-4" />
                            <span>Filters</span>
                            {hasFilters ? (
                                <span className="absolute -top-1 -right-1 size-2 rounded-full bg-primary" />
                            ) : null}
                        </Button>
                        {permissions['journal-entries.create'] === true ? (
                            <Button asChild className="gap-2">
                                <Link href="/accounting/journal-entries/create">
                                    <Plus className="size-4" />
                                    <span>New journal</span>
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                {filtersOpen ? (
                    <Card className="rounded-lg py-4">
                        <CardHeader className="px-4 pb-3">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <CardTitle className="text-sm">
                                        Filters
                                    </CardTitle>
                                    {activeFilterCount > 0 ? (
                                        <Badge variant="secondary">
                                            {activeFilterCount} active
                                        </Badge>
                                    ) : null}
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    onClick={() => setFiltersOpen(false)}
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="px-4">
                            <form
                                onSubmit={submitFilters}
                                className="space-y-4"
                            >
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    {branches.length > 0 ? (
                                        <div className="grid gap-2">
                                            <Label htmlFor="filter_branch_id">Branch</Label>
                                            <Dropdown
                                                id="filter_branch_id"
                                                className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                                value={filterForm.data.branch_id}
                                                onChange={(event) =>
                                                    filterForm.setData('branch_id', event.target.value)
                                                }
                                            >
                                                <option value="">All branches</option>
                                                {branches.map((branch) => (
                                                    <option key={branch.id} value={branch.id}>
                                                        {branch.code} - {branch.name}
                                                    </option>
                                                ))}
                                            </Dropdown>
                                        </div>
                                    ) : null}
                                    <div className="grid gap-2">
                                        <Label htmlFor="voucher_number">
                                            Voucher no.
                                        </Label>
                                        <Input
                                            id="voucher_number"
                                            placeholder="JV-2026-00012"
                                            value={
                                                filterForm.data.voucher_number
                                            }
                                            onChange={(event) =>
                                                filterForm.setData(
                                                    'voucher_number',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="source_document_number">
                                            Document no.
                                        </Label>
                                        <Input
                                            id="source_document_number"
                                            placeholder="BILL-778"
                                            value={
                                                filterForm.data
                                                    .source_document_number
                                            }
                                            onChange={(event) =>
                                                filterForm.setData(
                                                    'source_document_number',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="reference">
                                            Reference
                                        </Label>
                                        <Input
                                            id="reference"
                                            value={filterForm.data.reference}
                                            onChange={(event) =>
                                                filterForm.setData(
                                                    'reference',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <Input
                                            id="description"
                                            value={filterForm.data.description}
                                            onChange={(event) =>
                                                filterForm.setData(
                                                    'description',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Status</Label>
                                        <Dropdown
                                            value={filterForm.data.status}
                                            onValueChange={(value) =>
                                                filterForm.setData(
                                                    'status',
                                                    value,
                                                )
                                            }
                                        >
                                            
                                            
                                                <option value="all">
                                                    All
                                                </option>
                                                <option value="draft">
                                                    Draft
                                                </option>
                                                <option value="posted">
                                                    Posted
                                                </option>
                                                <option value="void">
                                                    Void
                                                </option>
                                            
                                        </Dropdown>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Currency</Label>
                                        <SearchableSelect
                                            key={`currency-${filterForm.data.currency_id}`}
                                            value={
                                                filterForm.data.currency_id ===
                                                'all'
                                                    ? ''
                                                    : filterForm.data
                                                          .currency_id
                                            }
                                            options={currencyOptions}
                                            placeholder="All currencies"
                                            onChange={(value) =>
                                                filterForm.setData(
                                                    'currency_id',
                                                    value || 'all',
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="entry_date_from">
                                            From
                                        </Label>
                                        <Input
                                            id="entry_date_from"
                                            type="date"
                                            value={
                                                filterForm.data.entry_date_from
                                            }
                                            onChange={(event) =>
                                                filterForm.setData(
                                                    'entry_date_from',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="entry_date_to">
                                            To
                                        </Label>
                                        <Input
                                            id="entry_date_to"
                                            type="date"
                                            value={
                                                filterForm.data.entry_date_to
                                            }
                                            onChange={(event) =>
                                                filterForm.setData(
                                                    'entry_date_to',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button type="submit" className="min-w-20">
                                        Apply
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={resetFilters}
                                    >
                                        Clear
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[860px] text-sm [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2">
                        <thead className="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th className="p-3">Voucher</th>
                                <th className="p-3">Date</th>
                                <th className="p-3">Reference / description</th>
                                {branches.length > 0 ? <th className="p-3">Branch</th> : null}
                                <th className="p-3 text-right">Amount</th>
                                <th className="p-3">Status</th>
                                <th className="p-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.data.map((entry) => (
                                <tr key={entry.id} className="border-t hover:bg-muted/40">
                                    <td className="p-3 whitespace-nowrap">
                                        <Link
                                            href={`/accounting/journal-entries/${entry.id}`}
                                            className={`font-mono ${entry.voucher_number ? 'font-semibold text-primary hover:underline' : 'text-muted-foreground hover:underline'}`}
                                        >
                                            {entry.voucher_number ??
                                                `${entry.voucher_type?.code ?? 'JV'} draft #${entry.id}`}
                                        </Link>
                                    </td>
                                    <td className="p-3 whitespace-nowrap">
                                        {entry.entry_date.slice(0, 10)}
                                    </td>
                                    <td className="max-w-md p-3">
                                        <div className="truncate font-medium">
                                            {entry.reference || entry.description}
                                        </div>
                                        <div className="truncate text-xs text-muted-foreground">
                                            {entry.reference && entry.description
                                                ? `${entry.description} · `
                                                : ''}
                                            {entry.source_document_number ? (
                                                <span className="font-mono">
                                                    {entry.source_document_number}
                                                    {' · '}
                                                </span>
                                            ) : null}
                                            {entry.lines_count !== undefined
                                                ? `${entry.lines_count} ${entry.lines_count === 1 ? 'line' : 'lines'}`
                                                : null}
                                        </div>
                                    </td>
                                    {branches.length > 0 ? (
                                        <td className="p-3">
                                            {entry.branch ? (
                                                <span
                                                    title={entry.branch.name}
                                                    className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary"
                                                >
                                                    {entry.branch.code}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                    ) : null}
                                    <td className="whitespace-nowrap p-3 text-right tabular-nums">
                                        {entry.total_debit !== undefined && entry.total_debit !== null
                                            ? Number(entry.total_debit).toLocaleString('en-US', {
                                                  minimumFractionDigits: 2,
                                                  maximumFractionDigits: 2,
                                              })
                                            : ''}{' '}
                                        <span className="text-xs text-muted-foreground">
                                            {entry.currency?.code}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap p-3">
                                        <StatusPill status={entry.status} />{' '}
                                        {entry.approval_status ? (
                                            <StatusPill
                                                status={entry.approval_status}
                                                label={
                                                    entry.approval_status === 'pending'
                                                        ? 'Awaiting approval'
                                                        : undefined
                                                }
                                            />
                                        ) : null}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex justify-end gap-1">
                                            {permissions['journal-entries.update'] === true &&
                                                entry.status === 'draft' && (
                                                    <Button asChild size="icon" variant="ghost" title="Edit">
                                                        <Link href={`/accounting/journal-entries/${entry.id}/edit`}>
                                                            <Edit className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                            <Button asChild size="sm" variant="outline" title="Open">
                                                <Link href={`/accounting/journal-entries/${entry.id}`}>Open</Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {entries.links ? (
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                        <div>
                            {entries.from && entries.to
                                ? `Showing ${entries.from}-${entries.to} of ${entries.total}`
                                : `${entries.total ?? entries.data.length} entries`}
                        </div>
                        <div className="flex flex-wrap gap-1">
                            {entries.links.map((link, index) => (
                                <Button
                                    key={index}
                                    asChild={Boolean(link.url)}
                                    disabled={!link.url}
                                    variant={
                                        link.active ? 'secondary' : 'outline'
                                    }
                                    size="sm"
                                >
                                    {link.url ? (
                                        <Link
                                            href={link.url}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ) : (
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    )}
                                </Button>
                            ))}
                        </div>
                    </div>
                ) : null}
            </div>
        </>
    );
}

JournalEntriesIndex.layout = {
    breadcrumbs: [
        { title: 'Journal Entries', href: '/accounting/journal-entries' },
    ],
};
