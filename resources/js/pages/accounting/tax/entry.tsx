import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Props = {
    taxCodes: Array<{ id: number; code: string; name: string; kind: string; rate: string | null }>;
    accounts: Array<{ id: number; account_code: string; account_name: string }>;
    costCenters: Array<{ id: number; code: string; name: string }>;
    types: Array<{ value: string; label: string }>;
    today: string;
};

const kindFor: Record<string, string> = {
    sale: 'output',
    sale_return: 'output',
    purchase: 'input',
    purchase_return: 'input',
    withholding_payment: 'withheld',
    withholding_receipt: 'advance',
};
const accountLabel: Record<string, [string, string]> = {
    sale: ['Revenue account', 'Customer / bank account (debited)'],
    sale_return: ['Revenue account (debited)', 'Customer / bank account (credited)'],
    purchase: ['Expense / asset account', 'Supplier / bank account (credited)'],
    purchase_return: ['Expense / asset account (credited)', 'Supplier / bank account (debited)'],
    withholding_payment: ['Supplier account (debited the full amount)', 'Bank account (credited the amount less the tax)'],
    withholding_receipt: ['Customer account (credited the full amount)', 'Bank account (debited the amount less the tax)'],
};
const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const money = (value: number) =>
    value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function TaxEntry({ taxCodes, accounts, costCenters, types, today }: Props) {
    const { flash } = useAccounting();
    const form = useForm({
        type: 'sale',
        entry_date: today,
        amount: '',
        tax_inclusive: false,
        tax_code_id: '',
        account_id: '',
        counter_account_id: '',
        cost_center_id: '',
        reference: '',
        description: '',
        auto_post: false,
    });
    const codes = taxCodes.filter((code) => code.kind === kindFor[form.data.type]);
    const code = codes.find((item) => String(item.id) === form.data.tax_code_id);
    const withholding = form.data.type.startsWith('withholding');
    const amount = Number(form.data.amount || 0);
    const rate = Number(code?.rate ?? 0);
    const inclusive = form.data.tax_inclusive && !withholding;
    const tax = inclusive ? (amount * rate) / (100 + rate) : (amount * rate) / 100;
    const base = inclusive ? amount - tax : amount;
    const [accountText, counterText] = accountLabel[form.data.type];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            tax_inclusive: data.tax_inclusive && !data.type.startsWith('withholding'),
            cost_center_id: data.cost_center_id === '' ? null : data.cost_center_id,
        }));
        form.post('/accounting/tax/entries');
    };

    return (
        <>
            <Head title="Taxed Document" />
            <form onSubmit={submit} className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <Heading
                    title="Taxed Document"
                    description="An invoice, bill, credit or debit note, or a payment with tax withheld: the tax is worked out from the code's rate on the date and booked to its tax account."
                />
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Document</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="type">Type</Label>
                            <select id="type" className={selectClass} value={form.data.type} onChange={(event) => form.setData({ ...form.data, type: event.target.value, tax_code_id: '' })}>
                                {types.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="entry_date">Date</Label>
                            <Input id="entry_date" type="date" value={form.data.entry_date} onChange={(event) => form.setData('entry_date', event.target.value)} />
                            <InputError message={form.errors.entry_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="tax_code_id">Tax code</Label>
                            <select id="tax_code_id" className={selectClass} value={form.data.tax_code_id} onChange={(event) => form.setData('tax_code_id', event.target.value)}>
                                <option value="">Select a {kindFor[form.data.type]} tax code</option>
                                {codes.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.code} {item.rate === null ? '(no rate)' : `(${Number(item.rate)}%)`}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.tax_code_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="amount">{withholding ? 'Gross amount' : 'Amount'}</Label>
                            <Input id="amount" type="number" step="0.01" min="0" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} />
                            <InputError message={form.errors.amount} />
                        </div>
                        {withholding ? null : (
                            <label className="flex items-center gap-2 pt-7 text-sm">
                                <input type="checkbox" checked={form.data.tax_inclusive} onChange={(event) => form.setData('tax_inclusive', event.target.checked)} />
                                The amount includes the tax
                            </label>
                        )}
                        <div className="pt-6 text-sm tabular-nums" data-testid="tax-preview">
                            {code && amount > 0
                                ? withholding
                                    ? `Withheld ${money(tax)} · paid ${money(amount - tax)}`
                                    : `Amount ${money(base)} · Tax ${money(tax)} · Total ${money(base + tax)}`
                                : ''}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="account_id">{accountText}</Label>
                            <select id="account_id" className={selectClass} value={form.data.account_id} onChange={(event) => form.setData('account_id', event.target.value)}>
                                <option value="">Select an account</option>
                                {accounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.account_code} - {account.account_name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.account_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="counter_account_id">{counterText}</Label>
                            <select id="counter_account_id" className={selectClass} value={form.data.counter_account_id} onChange={(event) => form.setData('counter_account_id', event.target.value)}>
                                <option value="">Select an account</option>
                                {accounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.account_code} - {account.account_name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.counter_account_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="cost_center_id">Cost center</Label>
                            <select id="cost_center_id" className={selectClass} value={form.data.cost_center_id} onChange={(event) => form.setData('cost_center_id', event.target.value)}>
                                <option value="">None</option>
                                {costCenters.map((center) => (
                                    <option key={center.id} value={center.id}>
                                        {center.code} - {center.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reference">Document number</Label>
                            <Input id="reference" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} />
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="description">Narration</Label>
                            <Input id="description" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                        </div>
                        <label className="flex items-center gap-2 text-sm md:col-span-3">
                            <input type="checkbox" checked={form.data.auto_post} onChange={(event) => form.setData('auto_post', event.target.checked)} />
                            Post it now (otherwise it is saved as a draft)
                        </label>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" className="gap-2" disabled={form.processing}>
                        <Save className="size-4" />
                        Create entry
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href="/accounting/tax">Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

TaxEntry.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Tax', href: '/accounting/tax' },
        { title: 'Taxed document', href: '/accounting/tax/entries/create' },
    ],
};
