import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Line = { chart_of_account_id: string; description: string; cost_center_id: string; quantity: string; unit_price: string; tax_code_id: string };
type Props = {
    document: {
        id: number;
        party_id: number;
        issue_date: string;
        due_date: string;
        reference: string | null;
        prices_include_tax: boolean;
        notes: string | null;
        lines: Array<{ chart_of_account_id: number; description: string | null; cost_center_id: number | null; quantity: string; unit_price: string; tax_code_id: number | null }>;
    } | null;
    kind: string;
    parties: Array<{ id: number; code: string; name: string; payment_terms_days: number }>;
    accounts: Array<{ id: number; account_code: string; account_name: string }>;
    costCenters: Array<{ id: number; code: string; name: string }>;
    taxCodes: Array<{ id: number; code: string; name: string; rate: string | null }>;
    today: string;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const titles: Record<string, string> = { invoice: 'Invoice', bill: 'Bill', credit_note: 'Credit Note', debit_note: 'Debit Note' };
const emptyLine = (): Line => ({ chart_of_account_id: '', description: '', cost_center_id: '', quantity: '1', unit_price: '', tax_code_id: '' });
const money = (value: number) => value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function PartyDocumentForm({ document, kind, parties, accounts, costCenters, taxCodes, today }: Props) {
    const { flash } = useAccounting();
    const form = useForm({
        party_id: document ? String(document.party_id) : '',
        kind,
        issue_date: document?.issue_date ?? today,
        due_date: document?.due_date ?? '',
        reference: document?.reference ?? '',
        prices_include_tax: document?.prices_include_tax ?? false,
        notes: document?.notes ?? '',
        lines: document
            ? document.lines.map((line) => ({
                  chart_of_account_id: String(line.chart_of_account_id),
                  description: line.description ?? '',
                  cost_center_id: line.cost_center_id ? String(line.cost_center_id) : '',
                  quantity: String(Number(line.quantity)),
                  unit_price: String(Number(line.unit_price)),
                  tax_code_id: line.tax_code_id ? String(line.tax_code_id) : '',
              }))
            : [emptyLine()],
    });
    const setLine = (index: number, patch: Partial<Line>) =>
        form.setData('lines', form.data.lines.map((line, position) => (position === index ? { ...line, ...patch } : line)));
    const errors = form.errors as Record<string, string | undefined>;

    // A preview of the totals; the server computes the real ones.
    let subtotal = 0;
    let tax = 0;
    for (const line of form.data.lines) {
        const gross = Number(line.quantity || 0) * Number(line.unit_price || 0);
        const rate = Number(taxCodes.find((code) => String(code.id) === line.tax_code_id)?.rate ?? 0);
        const lineTax = form.data.prices_include_tax ? (gross * rate) / (100 + rate) : (gross * rate) / 100;
        tax += lineTax;
        subtotal += form.data.prices_include_tax ? gross - lineTax : gross;
    }

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            due_date: data.due_date || null,
            lines: data.lines.filter((line) => line.chart_of_account_id !== '').map((line) => ({
                ...line,
                cost_center_id: line.cost_center_id || null,
                tax_code_id: line.tax_code_id || null,
            })),
        }));
        if (document) {
            form.put(`/accounting/party-documents/${document.id}`);
        } else {
            form.post('/accounting/party-documents');
        }
    };

    return (
        <>
            <Head title={`${document ? 'Edit' : 'New'} ${titles[kind]}`} />
            <form onSubmit={submit} className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <Heading title={`${document ? 'Edit' : 'New'} ${titles[kind]}`} description="Saved as a draft; posting numbers it and books it to the ledger." />
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>{kind === 'invoice' || kind === 'credit_note' ? 'Customer' : 'Supplier'}</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-4">
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="party_id">{kind === 'invoice' || kind === 'credit_note' ? 'Customer' : 'Supplier'}</Label>
                            <select id="party_id" className={selectClass} value={form.data.party_id} onChange={(event) => form.setData('party_id', event.target.value)}>
                                <option value="">Select</option>
                                {parties.map((party) => (
                                    <option key={party.id} value={party.id}>
                                        {party.code} - {party.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.party_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="issue_date">Date</Label>
                            <Input id="issue_date" type="date" value={form.data.issue_date} onChange={(event) => form.setData('issue_date', event.target.value)} />
                            <InputError message={form.errors.issue_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="due_date">Due date (default: payment terms)</Label>
                            <Input id="due_date" type="date" value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} />
                            <InputError message={form.errors.due_date} />
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="reference">{kind === 'bill' ? "Supplier's invoice number" : 'Reference (e.g. order number)'}</Label>
                            <Input id="reference" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} />
                        </div>
                        <label className="flex items-center gap-2 pt-7 text-sm md:col-span-2">
                            <input type="checkbox" checked={form.data.prices_include_tax} onChange={(event) => form.setData('prices_include_tax', event.target.checked)} />
                            Prices include tax
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Lines</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {form.data.lines.map((line, index) => (
                            <div key={index} className="grid gap-2 rounded-md border p-3">
                                <div className="grid gap-2 md:grid-cols-[2fr_2fr_1fr_1fr_1.4fr_auto] md:items-center">
                                    <select className={selectClass} value={line.chart_of_account_id} onChange={(event) => setLine(index, { chart_of_account_id: event.target.value })} aria-label="Account">
                                        <option value="">Account</option>
                                        {accounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.account_code} - {account.account_name}
                                            </option>
                                        ))}
                                    </select>
                                    <Input placeholder="Description" value={line.description} onChange={(event) => setLine(index, { description: event.target.value })} aria-label="Description" />
                                    <Input type="number" step="any" min="0" placeholder="Qty" value={line.quantity} onChange={(event) => setLine(index, { quantity: event.target.value })} aria-label="Quantity" />
                                    <Input type="number" step="any" min="0" placeholder="Price" value={line.unit_price} onChange={(event) => setLine(index, { unit_price: event.target.value })} aria-label="Unit price" />
                                    <select className={selectClass} value={line.tax_code_id} onChange={(event) => setLine(index, { tax_code_id: event.target.value })} aria-label="Tax code">
                                        <option value="">No tax</option>
                                        {taxCodes.map((code) => (
                                            <option key={code.id} value={code.id}>
                                                {code.code} {code.rate === null ? '' : `(${Number(code.rate)}%)`}
                                            </option>
                                        ))}
                                    </select>
                                    <Button type="button" size="icon" variant="ghost" title="Remove" onClick={() => form.data.lines.length > 1 && form.setData('lines', form.data.lines.filter((_, position) => position !== index))}>
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                                {costCenters.length > 0 ? (
                                    <select className={`${selectClass} md:w-64`} value={line.cost_center_id} onChange={(event) => setLine(index, { cost_center_id: event.target.value })} aria-label="Cost center">
                                        <option value="">No cost center</option>
                                        {costCenters.map((center) => (
                                            <option key={center.id} value={center.id}>
                                                {center.code} - {center.name}
                                            </option>
                                        ))}
                                    </select>
                                ) : null}
                                <InputError message={errors[`lines.${index}.chart_of_account_id`] ?? errors[`lines.${index}.unit_price`]} />
                            </div>
                        ))}
                        <InputError message={errors.lines} />
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <Button type="button" variant="outline" className="gap-2" onClick={() => form.setData('lines', [...form.data.lines, emptyLine()])}>
                                <Plus className="size-4" />
                                Add line
                            </Button>
                            <div className="text-sm tabular-nums" data-testid="totals">
                                Subtotal {money(subtotal)} · Tax {money(tax)} · Total {money(subtotal + tax)}
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Input id="notes" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                        </div>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" className="gap-2" disabled={form.processing}>
                        <Save className="size-4" />
                        Save draft
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href="/accounting/party-documents">Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

PartyDocumentForm.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Invoices & Bills', href: '/accounting/party-documents' },
    ],
};
