import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { SearchableSelect } from '@/components/accounting/searchable-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Option = {
    id: number;
    name?: string;
    code?: string;
    account_code?: string;
    account_name?: string;
    normal_balance?: string;
    is_base?: boolean;
};

type AccountRecord = {
    id: number;
    parent_id: number | null;
    account_type_id: number;
    currency_id: number;
    account_code: string;
    account_name: string;
    normal_balance: string;
    description: string | null;
    is_group: boolean;
    is_active: boolean;
};

type Props = {
    title: string;
    action: string;
    method: 'post' | 'put';
    record: AccountRecord | null;
    accountTypes: Option[];
    currencies: Option[];
    parents: Option[];
};

export default function ChartOfAccountForm({ title, action, method, record, accountTypes, currencies, parents }: Props) {
    const parentOptions = [
        { value: 'none', label: 'No parent' },
        ...parents.map((parent) => ({
            value: String(parent.id),
            label: `${parent.account_code} - ${parent.account_name}`,
        })),
    ];
    const accountTypeOptions = accountTypes.map((type) => ({
        value: String(type.id),
        label: `${type.name} (${type.normal_balance})`,
    }));
    const currencyOptions = currencies.map((currency) => ({
        value: String(currency.id),
        label: `${currency.code} - ${currency.name}`,
    }));

    const form = useForm({
        parent_id: record?.parent_id ? String(record.parent_id) : 'none',
        account_type_id: record?.account_type_id ? String(record.account_type_id) : '',
        currency_id: record?.currency_id ? String(record.currency_id) : '',
        account_code: record?.account_code ?? '',
        account_name: record?.account_name ?? '',
        normal_balance: record?.normal_balance ?? 'debit',
        description: record?.description ?? '',
        is_group: Boolean(record?.is_group),
        is_active: record ? Boolean(record.is_active) : true,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            parent_id: data.parent_id === 'none' ? '' : data.parent_id,
        }));

        if (method === 'put') {
            form.put(action);

            return;
        }

        form.post(action);
    };

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading title={title} description="Maintain the account structure used by journal posting and reporting." />
                    <Button asChild variant="outline">
                        <Link href="/accounting/chart-of-accounts">Back</Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                    <Card className="rounded-lg lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Account details</CardTitle>
                            <CardDescription>Stable code, name, type, currency, and hierarchy.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <Label>Parent Account</Label>
                                <SearchableSelect value={form.data.parent_id} options={parentOptions} placeholder="Search parent account" onChange={(value) => form.setData('parent_id', value)} />
                                <InputError message={form.errors.parent_id} />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label>Account Type</Label>
                                <SearchableSelect value={form.data.account_type_id} options={accountTypeOptions} placeholder="Search account type" onChange={(value) => form.setData('account_type_id', value)} />
                                <InputError message={form.errors.account_type_id} />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label>Currency</Label>
                                <SearchableSelect value={form.data.currency_id} options={currencyOptions} placeholder="Search currency" onChange={(value) => form.setData('currency_id', value)} />
                                <InputError message={form.errors.currency_id} />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label>Normal Balance</Label>
                                <Select value={form.data.normal_balance} onValueChange={(value) => form.setData('normal_balance', value)}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select normal balance" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="debit">Debit</SelectItem>
                                        <SelectItem value="credit">Credit</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.normal_balance} />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="account_code">Account Code</Label>
                                <Input id="account_code" value={form.data.account_code} onChange={(event) => form.setData('account_code', event.target.value)} />
                                <InputError message={form.errors.account_code} />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="account_name">Account Name</Label>
                                <Input id="account_name" value={form.data.account_name} onChange={(event) => form.setData('account_name', event.target.value)} />
                                <InputError message={form.errors.account_name} />
                            </div>

                            <div className="flex flex-col gap-2 md:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(event) => form.setData('description', event.target.value)}
                                    className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={form.errors.description} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Options</CardTitle>
                            <CardDescription>Posting behavior and account availability.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center gap-3 rounded-md border p-3">
                                <Checkbox id="is_group" checked={form.data.is_group} onCheckedChange={(checked) => form.setData('is_group', checked === true)} />
                                <Label htmlFor="is_group">Group account</Label>
                            </div>

                            <div className="flex items-center gap-3 rounded-md border p-3">
                                <Checkbox id="is_active" checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked === true)} />
                                <Label htmlFor="is_active">Active</Label>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex gap-2 lg:col-span-3">
                        <Button type="submit" disabled={form.processing}>
                            <Save className="size-4" />
                            Save
                        </Button>
                        <Button type="button" variant="outline" onClick={() => form.reset()}>Reset</Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ChartOfAccountForm.layout = {
    breadcrumbs: [{ title: 'Chart of Accounts', href: '/accounting/chart-of-accounts' }],
};
