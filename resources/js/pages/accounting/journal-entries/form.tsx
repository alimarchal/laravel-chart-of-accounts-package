import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { SearchableSelect } from '@/components/accounting/searchable-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccountingI18n } from '@/lib/i18n';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Account = {
    id: number;
    account_code: string;
    account_name: string;
};

type Currency = {
    id: number;
    code: string;
    name: string;
    is_base: boolean;
};

type CostCenter = {
    id: number;
    code: string;
    name: string;
};

type VoucherType = {
    id: number;
    code: string;
    name: string;
};

type JournalLine = {
    chart_of_account_id: string;
    cost_center_id: string;
    debit: string;
    credit: string;
    description: string;
    // Tax markers of lines made by the tax engine: sent back unchanged so editing a draft keeps its tax ledger.
    tax_code_id?: string;
    tax_role?: string;
    tax_rate?: string;
};

type Props = {
    action: string;
    method?: 'post' | 'put';
    title?: string;
    entry?: {
        voucher_type_id: number | null;
        entry_date: string;
        currency_id: number | null;
        fx_rate_to_base: string | number;
        reference: string | null;
        source_document_type: string | null;
        source_document_number: string | null;
        source_document_date: string | null;
        description: string | null;
        lines: Array<{
            chart_of_account_id: number;
            cost_center_id: number | null;
            debit: string | number;
            credit: string | number;
            description: string | null;
            tax_code_id?: number | null;
            tax_role?: string | null;
            tax_rate?: string | number | null;
        }>;
    } | null;
    accounts: Account[];
    currencies: Currency[];
    costCenters: CostCenter[];
    voucherTypes: VoucherType[];
    documentTypes: Record<string, string>;
};

const emptyLine = (): JournalLine => ({
    chart_of_account_id: '',
    cost_center_id: 'none',
    debit: '0',
    credit: '0',
    description: '',
});

export default function JournalEntryForm({
    action,
    method = 'post',
    title = 'Create Journal Entry',
    entry,
    accounts,
    currencies,
    costCenters,
    voucherTypes,
    documentTypes,
}: Props) {
    useAccountingI18n();
    const baseCurrency =
        currencies.find((currency) => currency.is_base) ?? currencies[0];
    const today = new Date().toISOString().slice(0, 10);
    const accountOptions = accounts.map((account) => ({
        value: String(account.id),
        label: `${account.account_code} - ${account.account_name}`,
    }));
    const costCenterOptions = [
        { value: 'none', label: 'None' },
        ...costCenters.map((costCenter) => ({
            value: String(costCenter.id),
            label: `${costCenter.code} - ${costCenter.name}`,
        })),
    ];

    const form = useForm({
        // The first type is the default (JV); the voucher number itself is issued on posting.
        voucher_type_id: entry?.voucher_type_id
            ? String(entry.voucher_type_id)
            : voucherTypes[0]
              ? String(voucherTypes[0].id)
              : '',
        entry_date: entry?.entry_date?.slice(0, 10) ?? today,
        currency_id: entry?.currency_id
            ? String(entry.currency_id)
            : baseCurrency
              ? String(baseCurrency.id)
              : '',
        fx_rate_to_base: entry?.fx_rate_to_base
            ? String(entry.fx_rate_to_base)
            : '1',
        reference: entry?.reference ?? '',
        source_document_type: entry?.source_document_type ?? 'none',
        source_document_number: entry?.source_document_number ?? '',
        source_document_date: entry?.source_document_date?.slice(0, 10) ?? '',
        description: entry?.description ?? '',
        auto_post: false as boolean,
        lines: entry?.lines.map((line) => ({
            chart_of_account_id: String(line.chart_of_account_id),
            cost_center_id: line.cost_center_id
                ? String(line.cost_center_id)
                : 'none',
            debit: String(line.debit ?? '0'),
            credit: String(line.credit ?? '0'),
            description: line.description ?? '',
            tax_code_id: line.tax_code_id ? String(line.tax_code_id) : '',
            tax_role: line.tax_role ?? '',
            tax_rate: line.tax_rate === null || line.tax_rate === undefined ? '' : String(line.tax_rate),
        })) ?? [emptyLine(), emptyLine()],
    });

    // Laravel returns array validation errors with dotted keys (lines.0.debit), which useForm's error type does not model.
    const errors = form.errors as Record<string, string | undefined>;

    const setLine = (
        index: number,
        field: keyof JournalLine,
        value: string,
    ) => {
        form.setData(
            'lines',
            form.data.lines.map((line, lineIndex) =>
                lineIndex === index ? { ...line, [field]: value } : line,
            ),
        );
    };

    const addLine = () => {
        form.setData('lines', [...form.data.lines, emptyLine()]);
    };

    const removeLine = (index: number) => {
        if (form.data.lines.length <= 2) {
            return;
        }

        form.setData(
            'lines',
            form.data.lines.filter((_, lineIndex) => lineIndex !== index),
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            source_document_type:
                data.source_document_type === 'none'
                    ? ''
                    : data.source_document_type,
            lines: data.lines.map((line) => ({
                ...line,
                cost_center_id:
                    line.cost_center_id === 'none' ? '' : line.cost_center_id,
                debit: line.debit === '' ? '0' : line.debit,
                credit: line.credit === '' ? '0' : line.credit,
            })),
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
                    <Heading
                        title={title}
                        description="Build a balanced journal entry before posting it to the ledger."
                    />
                    <Button asChild variant="outline">
                        <Link href="/accounting/journal-entries">Back</Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="grid gap-6">
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Journal header</CardTitle>
                            <CardDescription>
                                Voucher type, date, currency, reference, and
                                summary. The voucher number is issued when the
                                entry is posted.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                                <div className="flex flex-col gap-2">
                                    <Label>Voucher type</Label>
                                    <Select
                                        value={form.data.voucher_type_id}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'voucher_type_id',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {voucherTypes.map((type) => (
                                                <SelectItem
                                                    key={type.id}
                                                    value={String(type.id)}
                                                >
                                                    {type.code} - {type.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.voucher_type_id}
                                    />
                                </div>

                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="entry_date">
                                        Entry Date
                                    </Label>
                                    <Input
                                        id="entry_date"
                                        type="date"
                                        value={form.data.entry_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'entry_date',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.entry_date}
                                    />
                                </div>

                                <div className="flex flex-col gap-2">
                                    <Label>Currency</Label>
                                    <Select
                                        value={form.data.currency_id}
                                        onValueChange={(value) =>
                                            form.setData('currency_id', value)
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Select currency" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencies.map((currency) => (
                                                <SelectItem
                                                    key={currency.id}
                                                    value={String(currency.id)}
                                                >
                                                    {currency.code} -{' '}
                                                    {currency.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.currency_id}
                                    />
                                </div>

                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="fx_rate_to_base">
                                        FX Rate
                                    </Label>
                                    <Input
                                        id="fx_rate_to_base"
                                        type="number"
                                        step="0.00000001"
                                        value={form.data.fx_rate_to_base}
                                        onChange={(event) =>
                                            form.setData(
                                                'fx_rate_to_base',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.fx_rate_to_base}
                                    />
                                </div>

                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="reference">Reference</Label>
                                    <Input
                                        id="reference"
                                        value={form.data.reference}
                                        onChange={(event) =>
                                            form.setData(
                                                'reference',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.reference}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 rounded-md border border-dashed p-3 md:grid-cols-3">
                                <div className="flex flex-col gap-2">
                                    <Label>Source document</Label>
                                    <Select
                                        value={form.data.source_document_type}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'source_document_type',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="None" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                None
                                            </SelectItem>
                                            {Object.entries(documentTypes).map(
                                                ([key, label]) => (
                                                    <SelectItem
                                                        key={key}
                                                        value={key}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            form.errors.source_document_type
                                        }
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="source_document_number">
                                        Document number
                                    </Label>
                                    <Input
                                        id="source_document_number"
                                        placeholder="INV-1001"
                                        value={form.data.source_document_number}
                                        onChange={(event) =>
                                            form.setData(
                                                'source_document_number',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            form.errors.source_document_number
                                        }
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="source_document_date">
                                        Document date
                                    </Label>
                                    <Input
                                        id="source_document_date"
                                        type="date"
                                        value={form.data.source_document_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'source_document_date',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            form.errors.source_document_date
                                        }
                                    />
                                </div>
                                <p className="text-xs text-muted-foreground md:col-span-3">
                                    The invoice, bill or receipt this entry
                                    records. A document can be posted only once;
                                    reverse the entry to post it again.
                                </p>
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={form.errors.description} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Lines</CardTitle>
                            <CardDescription>
                                Every journal must balance: total debit must
                                equal total credit.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="overflow-hidden rounded-lg border">
                                <table className="w-full min-w-[980px] text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="p-3">Account</th>
                                            <th className="p-3">Cost Center</th>
                                            <th className="p-3">Debit</th>
                                            <th className="p-3">Credit</th>
                                            <th className="p-3">Description</th>
                                            <th className="w-12 p-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {form.data.lines.map((line, index) => (
                                            <tr
                                                key={index}
                                                className="border-t"
                                            >
                                                <td className="p-3">
                                                    <SearchableSelect
                                                        value={
                                                            line.chart_of_account_id
                                                        }
                                                        options={accountOptions}
                                                        placeholder="Search account"
                                                        onChange={(value) =>
                                                            setLine(
                                                                index,
                                                                'chart_of_account_id',
                                                                value,
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `lines.${index}.chart_of_account_id`
                                                            ]
                                                        }
                                                    />
                                                </td>
                                                <td className="p-3">
                                                    <SearchableSelect
                                                        value={
                                                            line.cost_center_id
                                                        }
                                                        options={
                                                            costCenterOptions
                                                        }
                                                        placeholder="Search cost center"
                                                        onChange={(value) =>
                                                            setLine(
                                                                index,
                                                                'cost_center_id',
                                                                value,
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `lines.${index}.cost_center_id`
                                                            ]
                                                        }
                                                    />
                                                </td>
                                                <td className="p-3">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={line.debit}
                                                        onChange={(event) =>
                                                            setLine(
                                                                index,
                                                                'debit',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `lines.${index}.debit`
                                                            ]
                                                        }
                                                    />
                                                </td>
                                                <td className="p-3">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={line.credit}
                                                        onChange={(event) =>
                                                            setLine(
                                                                index,
                                                                'credit',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `lines.${index}.credit`
                                                            ]
                                                        }
                                                    />
                                                </td>
                                                <td className="p-3">
                                                    <Input
                                                        value={line.description}
                                                        onChange={(event) =>
                                                            setLine(
                                                                index,
                                                                'description',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `lines.${index}.description`
                                                            ]
                                                        }
                                                    />
                                                </td>
                                                <td className="p-3">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            removeLine(index)
                                                        }
                                                        disabled={
                                                            form.data.lines
                                                                .length <= 2
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addLine}
                                >
                                    <Plus className="size-4" />
                                    Add line
                                </Button>
                                <div className="flex items-center gap-3 rounded-md border px-3 py-2">
                                    <Checkbox
                                        id="auto_post"
                                        checked={form.data.auto_post}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'auto_post',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="auto_post">
                                        Post after saving
                                    </Label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            <Save className="size-4" />
                            {method === 'put'
                                ? 'Update journal'
                                : 'Save journal'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => form.reset()}
                        >
                            Reset
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

JournalEntryForm.layout = {
    breadcrumbs: [
        { title: 'Journal Entries', href: '/accounting/journal-entries' },
    ],
};
