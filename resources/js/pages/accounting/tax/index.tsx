import { Head, Link, router, useForm } from '@inertiajs/react';
import { Calculator, FilePlus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Code = {
    id: number;
    code: string;
    name: string;
    kind: string;
    rate: string | null;
    tax_account: string | null;
    jurisdiction: string | null;
    is_active: boolean;
};
type TaxReturn = {
    id: number;
    period_from: string;
    period_to: string;
    output_tax: string;
    input_tax: string;
    net_payable: string;
    voucher_number: string | null;
    reference: string | null;
};
type Props = {
    taxCodes: Code[];
    returns: TaxReturn[];
    accounts: Array<{ id: number; account_code: string; account_name: string }>;
    today: string;
    monthStart: string;
    monthEnd: string;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const money = (value: string) =>
    Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function TaxIndex({ taxCodes, returns, accounts, today, monthStart, monthEnd }: Props) {
    const { permissions, flash } = useAccounting();
    const [calc, setCalc] = useState({ tax_code_id: String(taxCodes[0]?.id ?? ''), amount: '', inclusive: false });
    const [result, setResult] = useState<{ base: string; tax: string; gross: string; rate: string } | null>(null);
    const [calcError, setCalcError] = useState<string | null>(null);
    const filing = useForm({ period_from: monthStart, period_to: monthEnd, payable_account_id: '', reference: '', notes: '' });

    const calculate = async (event: FormEvent) => {
        event.preventDefault();
        setCalcError(null);
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const response = await fetch('/accounting/tax/calculate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ ...calc, amount: calc.amount === '' ? 0 : calc.amount, date: today }),
        });
        const body = await response.json();
        if (response.ok) {
            setResult(body.data);
        } else {
            setResult(null);
            setCalcError(body.message ?? 'Could not calculate.');
        }
    };

    return (
        <>
            <Head title="Tax" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title="Tax"
                        description="Tax codes and rates, taxed documents, the tax ledger and tax returns."
                    />
                    <div className="flex flex-wrap gap-2">
                        {permissions['tax-entries.create'] ? (
                            <Button asChild className="gap-2">
                                <Link href="/accounting/tax/entries/create">
                                    <FilePlus className="size-4" />
                                    New taxed document
                                </Link>
                            </Button>
                        ) : null}
                        <Button asChild variant="outline">
                            <Link href={`/accounting/tax/returns/report?date_from=${monthStart}&date_to=${monthEnd}`}>Tax report</Link>
                        </Button>
                        {permissions['tax-codes.view'] ? (
                            <Button asChild variant="outline">
                                <Link href="/accounting/tax-codes">Tax codes</Link>
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

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Code</th>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Kind</th>
                                <th className="px-3 py-2 text-right font-medium">Rate today</th>
                                <th className="px-3 py-2 font-medium">Tax account</th>
                                <th className="px-3 py-2 font-medium">Jurisdiction</th>
                            </tr>
                        </thead>
                        <tbody>
                            {taxCodes.map((code) => (
                                <tr key={code.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">
                                        {code.code} {code.is_active ? null : <Badge className="ml-1 bg-muted text-muted-foreground">inactive</Badge>}
                                    </td>
                                    <td className="px-3 py-2">{code.name}</td>
                                    <td className="px-3 py-2">{code.kind}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{code.rate === null ? '—' : `${Number(code.rate)}%`}</td>
                                    <td className="px-3 py-2">{code.tax_account ?? <span className="text-amber-700">not set</span>}</td>
                                    <td className="px-3 py-2">{code.jurisdiction}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Calculator</CardTitle>
                        <CardDescription>Tax on an amount at today&apos;s rate.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={calculate} className="flex flex-wrap items-end gap-3">
                            <div className="grid gap-1">
                                <Label htmlFor="calc_code">Tax code</Label>
                                <select id="calc_code" className={selectClass} value={calc.tax_code_id} onChange={(event) => setCalc({ ...calc, tax_code_id: event.target.value })}>
                                    {taxCodes.map((code) => (
                                        <option key={code.id} value={code.id}>
                                            {code.code} ({code.kind})
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="calc_amount">Amount</Label>
                                <Input id="calc_amount" type="number" step="0.01" min="0" value={calc.amount} onChange={(event) => setCalc({ ...calc, amount: event.target.value })} />
                            </div>
                            <label className="flex items-center gap-2 pb-2 text-sm">
                                <input type="checkbox" checked={calc.inclusive} onChange={(event) => setCalc({ ...calc, inclusive: event.target.checked })} />
                                includes tax
                            </label>
                            <Button type="submit" variant="outline" className="gap-2">
                                <Calculator className="size-4" />
                                Calculate
                            </Button>
                            {result ? (
                                <div className="pb-1 text-sm tabular-nums" data-testid="calc-result">
                                    Base {money(result.base)} · Tax {money(result.tax)} ({Number(result.rate)}%) · Total {money(result.gross)}
                                </div>
                            ) : null}
                            {calcError ? <div className="pb-1 text-sm text-red-700">{calcError}</div> : null}
                        </form>
                    </CardContent>
                </Card>

                {permissions['tax-returns.file'] ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>File a return</CardTitle>
                            <CardDescription>
                                Offsets the period&apos;s output tax against its input tax in one entry and books the difference to the account you pick. Review the tax report first.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    filing.post('/accounting/tax/returns', { preserveScroll: true });
                                }}
                                className="grid gap-4 md:grid-cols-5"
                            >
                                <div className="grid gap-1">
                                    <Label htmlFor="period_from">From</Label>
                                    <Input id="period_from" type="date" value={filing.data.period_from} onChange={(event) => filing.setData('period_from', event.target.value)} />
                                    <InputError message={filing.errors.period_from} />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="period_to">To</Label>
                                    <Input id="period_to" type="date" value={filing.data.period_to} onChange={(event) => filing.setData('period_to', event.target.value)} />
                                    <InputError message={filing.errors.period_to} />
                                </div>
                                <div className="grid gap-1 md:col-span-2">
                                    <Label htmlFor="payable_account_id">Payable (or refundable) account</Label>
                                    <select id="payable_account_id" className={selectClass} value={filing.data.payable_account_id} onChange={(event) => filing.setData('payable_account_id', event.target.value)}>
                                        <option value="">Select an account</option>
                                        {accounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.account_code} - {account.account_name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={filing.errors.payable_account_id} />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="reference">Reference</Label>
                                    <Input id="reference" value={filing.data.reference} onChange={(event) => filing.setData('reference', event.target.value)} />
                                </div>
                                <div className="md:col-span-5">
                                    <Button type="submit" disabled={filing.processing || filing.data.payable_account_id === ''}>
                                        File return
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Filed returns</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Period</th>
                                        <th className="px-3 py-2 text-right font-medium">Output tax</th>
                                        <th className="px-3 py-2 text-right font-medium">Input tax</th>
                                        <th className="px-3 py-2 text-right font-medium">Net payable</th>
                                        <th className="px-3 py-2 font-medium">Entry</th>
                                        <th className="px-3 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {returns.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="px-3 py-6 text-center text-muted-foreground">
                                                No returns filed yet.
                                            </td>
                                        </tr>
                                    ) : null}
                                    {returns.map((item) => (
                                        <tr key={item.id} className="border-t">
                                            <td className="px-3 py-2">
                                                {item.period_from} → {item.period_to}
                                                {item.reference ? <span className="ml-2 text-xs text-muted-foreground">{item.reference}</span> : null}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">{money(item.output_tax)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{money(item.input_tax)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{money(item.net_payable)}</td>
                                            <td className="px-3 py-2">{item.voucher_number ?? '—'}</td>
                                            <td className="px-3 py-2 text-right">
                                                {permissions['tax-returns.file'] ? (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => {
                                                            if (confirm('Void this return? Its entry is reversed and the period can be filed again.')) {
                                                                router.delete(`/accounting/tax/returns/${item.id}`, { preserveScroll: true });
                                                            }
                                                        }}
                                                    >
                                                        Void
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
            </div>
        </>
    );
}

TaxIndex.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Tax', href: '/accounting/tax' },
    ],
};
