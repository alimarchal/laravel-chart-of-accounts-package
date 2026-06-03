import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Edit, Eye, Filter, Plus, X } from 'lucide-react';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { playErrorSound, playSuccessSound } from '@/lib/sounds';

type Entry = {
    id: number;
    entry_date: string;
    reference: string | null;
    description: string | null;
    status: string;
};

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
};

export default function JournalEntriesIndex({ entries, filters, currencies }: Props) {
    const { auth, flash } = usePage().props;
    const permissions = auth.accountingPermissions ?? {};
    const hasFilters = Object.values(filters ?? {}).some((value) => value && value !== 'all');
    const [filtersOpen, setFiltersOpen] = useState(hasFilters);
    const filterForm = useForm({
        reference: filters.reference ?? '',
        description: filters.description ?? '',
        status: filters.status ?? 'all',
        currency_id: filters.currency_id ?? 'all',
        entry_date_from: filters.entry_date_from ?? '',
        entry_date_to: filters.entry_date_to ?? '',
    });
    const activeFilterCount = Object.values(filterForm.data).filter((value) => value !== '' && value !== 'all').length;
    const currencyOptions = useMemo(
        () => currencies.map((currency) => ({ value: String(currency.id), label: `${currency.code} - ${currency.name}` })),
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
            reference: '',
            description: '',
            status: 'all',
            currency_id: 'all',
            entry_date_from: '',
            entry_date_to: '',
        });
        router.get('/accounting/journal-entries', {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <>
            <Head title="Journal Entries" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading title="Journal Entries" description="Draft, post, reverse, and review ledger entries." />
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant={filtersOpen ? 'secondary' : 'outline'} className="relative gap-2" onClick={() => setFiltersOpen((open) => !open)}>
                            <Filter className="size-4" />
                            <span>Filters</span>
                            {hasFilters ? <span className="absolute -right-1 -top-1 size-2 rounded-full bg-primary" /> : null}
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
                                    <CardTitle className="text-sm">Filters</CardTitle>
                                    {activeFilterCount > 0 ? <Badge variant="secondary">{activeFilterCount} active</Badge> : null}
                                </div>
                                <Button type="button" variant="ghost" size="icon" className="size-8" onClick={() => setFiltersOpen(false)}>
                                    <X className="size-4" />
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="px-4">
                            <form onSubmit={submitFilters} className="space-y-4">
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="reference">Reference</Label>
                                        <Input id="reference" value={filterForm.data.reference} onChange={(event) => filterForm.setData('reference', event.target.value)} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="description">Description</Label>
                                        <Input id="description" value={filterForm.data.description} onChange={(event) => filterForm.setData('description', event.target.value)} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Status</Label>
                                        <Select value={filterForm.data.status} onValueChange={(value) => filterForm.setData('status', value)}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All</SelectItem>
                                                <SelectItem value="draft">Draft</SelectItem>
                                                <SelectItem value="posted">Posted</SelectItem>
                                                <SelectItem value="void">Void</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Currency</Label>
                                        <SearchableSelect
                                            key={`currency-${filterForm.data.currency_id}`}
                                            value={filterForm.data.currency_id === 'all' ? '' : filterForm.data.currency_id}
                                            options={currencyOptions}
                                            placeholder="All currencies"
                                            onChange={(value) => filterForm.setData('currency_id', value || 'all')}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="entry_date_from">From</Label>
                                        <Input id="entry_date_from" type="date" value={filterForm.data.entry_date_from} onChange={(event) => filterForm.setData('entry_date_from', event.target.value)} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="entry_date_to">To</Label>
                                        <Input id="entry_date_to" type="date" value={filterForm.data.entry_date_to} onChange={(event) => filterForm.setData('entry_date_to', event.target.value)} />
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button type="submit" className="min-w-20">Apply</Button>
                                    <Button type="button" variant="outline" onClick={resetFilters}>Clear</Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}
                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Date</th>
                                <th className="p-3">Reference</th>
                                <th className="p-3">Description</th>
                                <th className="p-3">Status</th>
                                <th className="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.data.map((entry) => (
                                <tr key={entry.id} className="border-t">
                                    <td className="p-3">{entry.entry_date}</td>
                                    <td className="p-3">{entry.reference}</td>
                                    <td className="p-3">{entry.description}</td>
                                    <td className="p-3">{entry.status}</td>
                                    <td className="p-3">
                                        <div className="flex justify-end gap-2">
                                            {permissions['journal-entries.update'] === true && entry.status === 'draft' && (
                                                <Button asChild size="icon" variant="ghost" title="Edit">
                                                    <Link href={`/accounting/journal-entries/${entry.id}/edit`}>
                                                        <Edit className="size-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            <Button asChild size="icon" variant="ghost" title="View">
                                                <Link href={`/accounting/journal-entries/${entry.id}`}>
                                                    <Eye className="size-4" />
                                                </Link>
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
                        <div>{entries.from && entries.to ? `Showing ${entries.from}-${entries.to} of ${entries.total}` : `${entries.total ?? entries.data.length} entries`}</div>
                        <div className="flex flex-wrap gap-1">
                            {entries.links.map((link, index) => (
                                <Button key={index} asChild={Boolean(link.url)} disabled={!link.url} variant={link.active ? 'secondary' : 'outline'} size="sm">
                                    {link.url ? <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
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
    breadcrumbs: [{ title: 'Journal Entries', href: '/accounting/journal-entries' }],
};
