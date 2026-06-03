import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Edit, Eye, Filter, Plus, Trash2, X } from 'lucide-react';
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

type RecordValue = string | number | boolean | null;

type AccountingRecord = {
    id: number;
    [key: string]: RecordValue;
};

type Props = {
    title: string;
    routeName: string;
    columns: string[];
    fields: Array<{
        name: string;
        label: string;
        type: string;
        options?: Record<string, string>;
        filter?: boolean;
        table?: boolean;
    }>;
    filters: Record<string, string>;
    readOnly?: boolean;
    records: {
        data: AccountingRecord[];
        links?: Array<{ url: string | null; label: string; active: boolean }>;
        from?: number | null;
        to?: number | null;
        total?: number;
    };
};

function displayValue(value: RecordValue): string {
    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return value === null ? '' : String(value);
}

export default function AccountingResourceIndex({ title, routeName, columns, fields, filters, readOnly = false, records }: Props) {
    const { auth, flash } = usePage().props;
    const basePath = `/accounting/${routeName}`;
    const permissions = auth.accountingPermissions ?? {};
    const filterFields = fields.filter((field) => field.filter ?? field.table);
    const filterForm = useForm<Record<string, string>>(
        Object.fromEntries(filterFields.map((field) => [field.name, filters?.[field.name] ?? ''])) as Record<string, string>,
    );
    const hasFilters = Object.values(filters ?? {}).some((value) => value);
    const [filtersOpen, setFiltersOpen] = useState(hasFilters);
    const activeFilterCount = Object.values(filterForm.data).filter((value) => value !== '').length;
    const canCreate = !readOnly && permissions[`${routeName}.create`] === true;
    const canUpdate = !readOnly && permissions[`${routeName}.update`] === true;
    const canDelete = !readOnly && permissions[`${routeName}.delete`] === true;
    const filterSelectOptions = useMemo(
        () =>
            Object.fromEntries(
                filterFields.map((field) => [
                    field.name,
                    Object.entries(field.options ?? {}).map(([value, label]) => ({ value: String(value), label })),
                ]),
            ) as Record<string, Array<{ value: string; label: string }>>,
        [filterFields],
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

    const destroy = (record: AccountingRecord) => {
        if (window.confirm(`Delete ${title.toLowerCase()} #${record.id}?`)) {
            router.delete(`${basePath}/${record.id}`);
        }
    };

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            basePath,
            Object.fromEntries(
                Object.entries(filterForm.data)
                    .filter(([, value]) => value !== '')
                    .map(([key, value]) => [`filter[${key}]`, value]),
            ),
            { preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        filterForm.setData(Object.fromEntries(filterFields.map((field) => [field.name, ''])) as Record<string, string>);
        router.get(basePath, {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <>
            <Head title={`${title}s`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading title={`${title}s`} description={`Manage accounting ${title.toLowerCase()} records.`} />
                    <div className="flex flex-wrap gap-2">
                        {filterFields.length > 0 ? (
                            <Button type="button" variant={filtersOpen ? 'secondary' : 'outline'} className="relative gap-2" onClick={() => setFiltersOpen((open) => !open)}>
                                <Filter className="size-4" />
                                <span>Filters</span>
                                {hasFilters ? <span className="absolute -right-1 -top-1 size-2 rounded-full bg-primary" /> : null}
                            </Button>
                        ) : null}
                        {canCreate ? (
                            <Button asChild className="gap-2">
                            <Link href={`${basePath}/create`}>
                                <Plus className="size-4" />
                                <span>New {title.toLowerCase()}</span>
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

                {filtersOpen && filterFields.length > 0 ? (
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
                                    {filterFields.map((field) => (
                                        <div key={field.name} className="grid gap-2">
                                            <Label>{field.label}</Label>
                                            {field.type === 'select' ? (
                                                <SearchableSelect
                                                    key={`${field.name}-${filterForm.data[field.name] || 'all'}`}
                                                    value={filterForm.data[field.name] ?? ''}
                                                    options={filterSelectOptions[field.name] ?? []}
                                                    placeholder={`All ${field.label.toLowerCase()}`}
                                                    onChange={(value) => filterForm.setData(field.name, value)}
                                                />
                                            ) : field.type === 'checkbox' ? (
                                                <Select value={filterForm.data[field.name] || 'all'} onValueChange={(value) => filterForm.setData(field.name, value === 'all' ? '' : value)}>
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="All" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="all">All</SelectItem>
                                                        <SelectItem value="1">Yes</SelectItem>
                                                        <SelectItem value="0">No</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            ) : (
                                                <Input
                                                    type={field.type === 'number' || field.type === 'date' ? field.type : 'text'}
                                                    value={filterForm.data[field.name] ?? ''}
                                                    onChange={(event) => filterForm.setData(field.name, event.target.value)}
                                                />
                                            )}
                                        </div>
                                    ))}
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
                                {columns.map((column) => (
                                    <th key={column} className="p-3 font-medium capitalize">
                                        {column.replaceAll('_', ' ')}
                                    </th>
                                ))}
                                <th className="w-40 p-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {records.data.map((record) => (
                                <tr key={record.id} className="border-t">
                                    {columns.map((column) => (
                                        <td key={column} className="p-3">
                                            {displayValue(record[column])}
                                        </td>
                                    ))}
                                    <td className="p-3">
                                        <div className="flex justify-end gap-2">
                                            <Button asChild size="icon" variant="ghost" title="View">
                                                <Link href={`${basePath}/${record.id}`}>
                                                    <Eye className="size-4" />
                                                </Link>
                                            </Button>
                                            {canUpdate || canDelete ? (
                                                <>
                                                    {canUpdate ? (
                                                        <Button asChild size="icon" variant="ghost" title="Edit">
                                                        <Link href={`${basePath}/${record.id}/edit`}>
                                                            <Edit className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    ) : null}
                                                    {canDelete ? (
                                                        <Button size="icon" variant="ghost" title="Delete" onClick={() => destroy(record)}>
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                    ) : null}
                                                </>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {records.links ? (
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                        <div>{records.from && records.to ? `Showing ${records.from}-${records.to} of ${records.total}` : `${records.total ?? records.data.length} records`}</div>
                        <div className="flex flex-wrap gap-1">
                            {records.links.map((link, index) => (
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

AccountingResourceIndex.layout = {
    breadcrumbs: [{ title: 'Accounting', href: '/accounting' }],
};
