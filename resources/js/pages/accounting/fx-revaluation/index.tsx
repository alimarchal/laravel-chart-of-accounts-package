import { Head, Link, router, useForm } from '@inertiajs/react';
import { Calculator, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Row = {
    chart_of_account_id: number;
    account_code: string;
    account_name: string;
    currency_code: string;
    foreign_balance: string;
    rate: string;
    carrying_base: string;
    revalued_base: string;
    adjustment: string;
};
type Plan = {
    as_of_date: string;
    rows: Row[];
    total_gain: string;
    total_loss: string;
};
type Revaluation = {
    id: number;
    as_of_date: string;
    total_gain: string;
    total_loss: string;
    voucher_number: string | null;
    reversal_voucher_number: string | null;
    reversal_date: string | null;
};
type Props = {
    base: string | null;
    asOf: string;
    preview: Plan | null;
    previewError: string | null;
    currencies: Array<{ id: number; code: string; name: string; rate: string }>;
    accounts: Array<{ id: number; account_code: string; account_name: string; type: string }>;
    revaluations: Revaluation[];
    rates: Array<{ id: number; currency_id: number; currency: string; rate_date: string; rate: string; source: string | null }>;
    today: string;
    defaultAccountCode: string | null;
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs';
const money = (value: string) =>
    Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function FxRevaluation({
    base,
    asOf,
    preview,
    previewError,
    currencies,
    accounts,
    revaluations,
    rates,
    today,
    defaultAccountCode,
}: Props) {
    const { permissions, flash } = useAccounting();
    const [date, setDate] = useState(asOf);
    const [closing, setClosing] = useState<Record<number, string>>(
        Object.fromEntries(currencies.map((c) => [c.id, c.rate])),
    );
    const post = useForm({
        gain_loss_account_id: String(
            accounts.find((a) => a.account_code === defaultAccountCode)?.id ??
                '',
        ),
        auto_reverse: false,
        reversal_date: '',
        notes: '',
    });
    const rateForm = useForm({
        currency_id: String(currencies[0]?.id ?? ''),
        rate_date: today,
        rate: '',
        source: '',
    });

    const calculate = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/accounting/fx-revaluation',
            { as_of_date: date, rates: closing },
            { preserveScroll: true },
        );
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post.transform((data) => ({
            ...data,
            as_of_date: date,
            rates: closing,
            reversal_date: data.auto_reverse ? data.reversal_date || null : null,
        }));
        post.post('/accounting/fx-revaluation', { preserveScroll: true });
    };
    const saveRate = (event: FormEvent) => {
        event.preventDefault();
        rateForm.post('/accounting/fx-revaluation/rates', {
            preserveScroll: true,
            onSuccess: () => rateForm.reset('rate', 'source'),
        });
    };

    return (
        <>
            <Head title="Currency Revaluation" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <Heading
                    title="Currency Revaluation"
                    description={`Restate foreign-currency receivables, payables and bank balances at the closing rate${base ? ` (base currency ${base})` : ''}; the difference is booked as an unrealised exchange gain or loss.`}
                />

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                {flash.error || previewError ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>
                            {flash.error ?? previewError}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {permissions['fx-revaluation.run'] ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Revalue</CardTitle>
                            <CardDescription>
                                Pick the date and the closing rate of each currency, then calculate to see what would be adjusted.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={calculate} className="grid gap-4 md:grid-cols-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="as_of_date">As of</Label>
                                    <Input
                                        id="as_of_date"
                                        type="date"
                                        value={date}
                                        onChange={(event) => setDate(event.target.value)}
                                    />
                                </div>
                                {currencies.map((currency) => (
                                    <div key={currency.id} className="grid gap-2">
                                        <Label htmlFor={`rate-${currency.id}`}>
                                            {currency.code} closing rate
                                        </Label>
                                        <Input
                                            id={`rate-${currency.id}`}
                                            type="number"
                                            step="any"
                                            value={closing[currency.id] ?? ''}
                                            onChange={(event) =>
                                                setClosing({ ...closing, [currency.id]: event.target.value })
                                            }
                                        />
                                    </div>
                                ))}
                                <div className="flex items-end">
                                    <Button type="submit" className="gap-2">
                                        <Calculator className="size-4" />
                                        Calculate
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}

                {preview ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Preview as of {preview.as_of_date}</CardTitle>
                            <CardDescription>
                                Gain {money(preview.total_gain)} · Loss {money(preview.total_loss)}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">Account</th>
                                            <th className="px-3 py-2 text-right font-medium">Foreign balance</th>
                                            <th className="px-3 py-2 text-right font-medium">Rate</th>
                                            <th className="px-3 py-2 text-right font-medium">Carrying ({base})</th>
                                            <th className="px-3 py-2 text-right font-medium">Revalued ({base})</th>
                                            <th className="px-3 py-2 text-right font-medium">Adjustment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {preview.rows.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="px-3 py-6 text-center text-muted-foreground">
                                                    Nothing to revalue: every foreign-currency balance is already stated at these rates.
                                                </td>
                                            </tr>
                                        ) : null}
                                        {preview.rows.map((row) => (
                                            <tr key={row.chart_of_account_id} className="border-t">
                                                <td className="px-3 py-2">
                                                    {row.account_code} {row.account_name}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums">
                                                    {money(row.foreign_balance)} {row.currency_code}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums">{Number(row.rate)}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{money(row.carrying_base)}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{money(row.revalued_base)}</td>
                                                <td
                                                    className={`px-3 py-2 text-right tabular-nums ${Number(row.adjustment) >= 0 ? 'text-emerald-700' : 'text-red-700'}`}
                                                >
                                                    {money(row.adjustment)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {preview.rows.length > 0 && permissions['fx-revaluation.run'] ? (
                                <form onSubmit={submit} className="grid gap-4 md:grid-cols-4">
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="gain_loss_account_id">Unrealised gain/loss account</Label>
                                        <select
                                            id="gain_loss_account_id"
                                            className={selectClass}
                                            value={post.data.gain_loss_account_id}
                                            onChange={(event) => post.setData('gain_loss_account_id', event.target.value)}
                                        >
                                            <option value="">Select an income or expense account</option>
                                            {accounts.map((account) => (
                                                <option key={account.id} value={account.id}>
                                                    {account.account_code} - {account.account_name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={post.errors.gain_loss_account_id} />
                                    </div>
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="notes">Narration</Label>
                                        <Input
                                            id="notes"
                                            value={post.data.notes}
                                            onChange={(event) => post.setData('notes', event.target.value)}
                                        />
                                    </div>
                                    <label className="flex items-center gap-2 text-sm md:col-span-2">
                                        <input
                                            type="checkbox"
                                            checked={post.data.auto_reverse}
                                            onChange={(event) => post.setData('auto_reverse', event.target.checked)}
                                        />
                                        Reverse automatically at the start of the next period
                                    </label>
                                    {post.data.auto_reverse ? (
                                        <div className="grid gap-2">
                                            <Label htmlFor="reversal_date">Reversal date</Label>
                                            <Input
                                                id="reversal_date"
                                                type="date"
                                                value={post.data.reversal_date}
                                                onChange={(event) => post.setData('reversal_date', event.target.value)}
                                            />
                                            <InputError message={post.errors.reversal_date} />
                                        </div>
                                    ) : null}
                                    <div className="flex items-end md:col-span-4">
                                        <Button type="submit" disabled={post.processing || post.data.gain_loss_account_id === ''}>
                                            Post revaluation
                                        </Button>
                                    </div>
                                </form>
                            ) : null}
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Exchange rates</CardTitle>
                        <CardDescription>
                            Dated rates (1 unit = rate {base}). A revaluation date picks the latest rate on or before it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {permissions['fx-revaluation.rates'] && currencies.length > 0 ? (
                            <form onSubmit={saveRate} className="grid gap-4 md:grid-cols-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="rate_currency">Currency</Label>
                                    <select
                                        id="rate_currency"
                                        className={selectClass}
                                        value={rateForm.data.currency_id}
                                        onChange={(event) => rateForm.setData('currency_id', event.target.value)}
                                    >
                                        {currencies.map((currency) => (
                                            <option key={currency.id} value={currency.id}>
                                                {currency.code}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="rate_date">Date</Label>
                                    <Input
                                        id="rate_date"
                                        type="date"
                                        value={rateForm.data.rate_date}
                                        onChange={(event) => rateForm.setData('rate_date', event.target.value)}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="rate_value">Rate</Label>
                                    <Input
                                        id="rate_value"
                                        type="number"
                                        step="any"
                                        value={rateForm.data.rate}
                                        onChange={(event) => rateForm.setData('rate', event.target.value)}
                                    />
                                    <InputError message={rateForm.errors.rate} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="rate_source">Source</Label>
                                    <Input
                                        id="rate_source"
                                        value={rateForm.data.source}
                                        onChange={(event) => rateForm.setData('source', event.target.value)}
                                    />
                                </div>
                                <div className="flex items-end">
                                    <Button type="submit" variant="outline" disabled={rateForm.processing}>
                                        Save rate
                                    </Button>
                                </div>
                            </form>
                        ) : null}
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Date</th>
                                        <th className="px-3 py-2 font-medium">Currency</th>
                                        <th className="px-3 py-2 text-right font-medium">Rate</th>
                                        <th className="px-3 py-2 font-medium">Source</th>
                                        <th className="px-3 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {rates.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">
                                                No dated rates yet: revaluations use each currency&apos;s current rate.
                                            </td>
                                        </tr>
                                    ) : null}
                                    {rates.map((rate) => (
                                        <tr key={rate.id} className="border-t">
                                            <td className="px-3 py-2">{rate.rate_date}</td>
                                            <td className="px-3 py-2">{rate.currency}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{Number(rate.rate)}</td>
                                            <td className="px-3 py-2">{rate.source ?? '—'}</td>
                                            <td className="px-3 py-2 text-right">
                                                {permissions['fx-revaluation.rates'] ? (
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        title="Remove"
                                                        onClick={() =>
                                                            router.delete(`/accounting/fx-revaluation/rates/${rate.id}`, { preserveScroll: true })
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">As of</th>
                                        <th className="px-3 py-2 font-medium">Voucher</th>
                                        <th className="px-3 py-2 text-right font-medium">Gain</th>
                                        <th className="px-3 py-2 text-right font-medium">Loss</th>
                                        <th className="px-3 py-2 font-medium">Reversal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {revaluations.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">
                                                No revaluations yet.
                                            </td>
                                        </tr>
                                    ) : null}
                                    {revaluations.map((revaluation) => (
                                        <tr key={revaluation.id} className="border-t">
                                            <td className="px-3 py-2">
                                                <Link href={`/accounting/fx-revaluation/${revaluation.id}`} className="font-medium hover:underline">
                                                    {revaluation.as_of_date}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2">{revaluation.voucher_number ?? '—'}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{money(revaluation.total_gain)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{money(revaluation.total_loss)}</td>
                                            <td className="px-3 py-2">
                                                {revaluation.reversal_date
                                                    ? `${revaluation.reversal_voucher_number ?? ''} on ${revaluation.reversal_date}`
                                                    : 'not reversed'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

FxRevaluation.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Currency Revaluation', href: '/accounting/fx-revaluation' },
    ],
};
