import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Edit, Filter, Plus, Trash2, X } from 'lucide-react';
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

type Account = {
    id: number;
    account_code: string;
    account_name: string;
    normal_balance: string;
    is_group: boolean;
    is_active: boolean;
};

type Props = {
    accounts: {
        data: Account[];
        links?: Array<{ url: string | null; label: string; active: boolean }>;
        from?: number | null;
        to?: number | null;
        total?: number;
    };
    filters: Record<string, string | undefined>;
    accountTypes: Array<{ id: number; name: string }>;
    currencies: Array<{ id: number; code: string; name: string }>;
};

export default function ChartOfAccountsIndex({ accounts, filters, accountTypes, currencies }: Props) {
    const { auth, flash } = usePage().props;
    const permissions = auth.accountingPermissions ?? {};
    const hasFilters = Object.values(filters ?? {}).some((value) => value && value !== 'all');
    const [filtersOpen, setFiltersOpen] = useState(hasFilters);
    const filterForm = useForm({
        account_code: filters.account_code ?? '',
        account_name: filters.account_name ?? '',
        account_type_id: filters.account_type_id ?? 'all',
        currency_id: filters.currency_id ?? 'all',
        is_group: filters.is_group ?? 'all',
        is_active: filters.is_active ?? 'all',
    });
    const activeFilterCount = Object.values(filterForm.data).filter((value) => value !== '' && value !== 'all').length;
    const accountTypeOptions = useMemo(() => accountTypes.map((type) => ({ value: String(type.id), label: type.name })), [accountTypes]);
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

        router.get('/accounting/chart-of-accounts', params, {
            preserveState: true,
            replace: true,
        });
    };

    const destroy = (account: Account) => {
        if (window.confirm(`Delete account ${account.account_code}?`)) {
            router.delete(`/accounting/chart-of-accounts/${account.id}`);
        }
    };

    const resetFilters = () => {
        filterForm.setData({
            account_code: '',
            account_name: '',
            account_type_id: 'all',
            currency_id: 'all',
            is_group: 'all',
            is_active: 'all',
        });
        router.get('/accounting/chart-of-accounts', {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <>
            <Head title="Chart of Accounts" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading title="Chart of Accounts" description="Manage the account hierarchy used by journals and reports." />
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant={filtersOpen ? 'secondary' : 'outline'} className="relative gap-2" onClick={() => setFiltersOpen((open) => !open)}>
                            <Filter className="size-4" />
                            <span>Filters</span>
                            {hasFilters ? <span className="absolute -right-1 -top-1 size-2 rounded-full bg-primary" /> : null}
                        </Button>
                        {permissions['chart-of-accounts.create'] === true ? (
                            <Button asChild className="gap-2">
                        <Link href="/accounting/chart-of-accounts/create">
                            <Plus className="size-4" />
                                    <span>New account</span>
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
                                        <Label htmlFor="account_code">Code</Label>
                                        <Input id="account_code" value={filterForm.data.account_code} onChange={(event) => filterForm.setData('account_code', event.target.value)} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="account_name">Name</Label>
                                        <Input id="account_name" value={filterForm.data.account_name} onChange={(event) => filterForm.setData('account_name', event.target.value)} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Type</Label>
                                        <SearchableSelect
                                            key={`type-${filterForm.data.account_type_id}`}
                                            value={filterForm.data.account_type_id === 'all' ? '' : filterForm.data.account_type_id}
                                            options={accountTypeOptions}
                                            placeholder="All types"
                                            onChange={(value) => filterForm.setData('account_type_id', value || 'all')}
                                        />
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
                                        <Label>Posting</Label>
                                        <Select value={filterForm.data.is_group} onValueChange={(value) => filterForm.setData('is_group', value)}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All</SelectItem>
                                                <SelectItem value="0">Posting only</SelectItem>
                                                <SelectItem value="1">Group only</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Status</Label>
                                        <Select value={filterForm.data.is_active} onValueChange={(value) => filterForm.setData('is_active', value)}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All</SelectItem>
                                                <SelectItem value="1">Active</SelectItem>
                                                <SelectItem value="0">Inactive</SelectItem>
                                            </SelectContent>
                                        </Select>
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
                                <th className="p-3">Code</th>
                                <th className="p-3">Name</th>
                                <th className="p-3">Normal</th>
                                <th className="p-3">Type</th>
                                <th className="p-3">Status</th>
                                <th className="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {accounts.data.map((account) => (
                                <tr key={account.id} className="border-t">
                                    <td className="p-3 font-mono">{account.account_code}</td>
                                    <td className="p-3">{account.account_name}</td>
                                    <td className="p-3">{account.normal_balance}</td>
                                    <td className="p-3">{account.is_group ? 'Group' : 'Posting'}</td>
                                    <td className="p-3">{account.is_active ? 'Active' : 'Inactive'}</td>
                                    <td className="p-3">
                                        <div className="flex justify-end gap-2">
                                            {permissions['chart-of-accounts.update'] === true ? (
                                                <Button asChild size="icon" variant="ghost" title="Edit">
                                                <Link href={`/accounting/chart-of-accounts/${account.id}/edit`}>
                                                    <Edit className="size-4" />
                                                </Link>
                                            </Button>
                                            ) : null}
                                            {permissions['chart-of-accounts.delete'] === true ? (
                                                <Button size="icon" variant="ghost" title="Delete" onClick={() => destroy(account)}>
                                                <Trash2 className="size-4" />
                                            </Button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {accounts.links ? (
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                        <div>{accounts.from && accounts.to ? `Showing ${accounts.from}-${accounts.to} of ${accounts.total}` : `${accounts.total ?? accounts.data.length} accounts`}</div>
                        <div className="flex flex-wrap gap-1">
                            {accounts.links.map((link, index) => (
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

ChartOfAccountsIndex.layout = {
    breadcrumbs: [{ title: 'Chart of Accounts', href: '/accounting/chart-of-accounts' }],
};
