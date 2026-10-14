import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Save, Scale, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Dropdown } from '@/components/accounting/dropdown';
import type { DropdownOption } from '@/components/accounting/dropdown';
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

type Account = {
    id: number;
    account_code: string;
    account_name: string;
    type?: string | null;
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
        branch_id?: number | null;
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
    branches?: Array<{ id: number; code: string; name: string }>;
    defaultBranchId?: number | null;
};

const num = (value: string | number | null | undefined): number => {
    const parsed = parseFloat(String(value ?? '').replace(/,/g, ''));

    return Number.isFinite(parsed) ? parsed : 0;
};
const round = (value: number): number => Math.round(value * 100) / 100;
const money = (value: string | number): string =>
    num(value) > 0 ? num(value).toFixed(2) : '';
const fmt = (value: number): string =>
    value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const emptyLine = (): JournalLine => ({
    chart_of_account_id: '',
    cost_center_id: 'none',
    debit: '',
    credit: '',
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
    branches = [],
    defaultBranchId = null,
}: Props) {
    useAccountingI18n();
    const baseCurrency =
        currencies.find((currency) => currency.is_base) ?? currencies[0];
    const today = new Date().toISOString().slice(0, 10);
    const accountOptions = useMemo<DropdownOption[]>(
        () => [
            { value: '', label: 'Choose an account' },
            ...accounts.map((account) => ({
                value: String(account.id),
                label: `${account.account_code} - ${account.account_name}`,
                code: account.account_code,
                name: account.account_name,
                tag: account.type ?? undefined,
            })),
        ],
        [accounts],
    );
    const costCenterOptions = useMemo<DropdownOption[]>(
        () => [
            { value: 'none', label: 'No cost center' },
            ...costCenters.map((costCenter) => ({
                value: String(costCenter.id),
                label: `${costCenter.code} - ${costCenter.name}`,
            })),
        ],
        [costCenters],
    );

    const form = useForm({
        branch_id: String(entry?.branch_id ?? defaultBranchId ?? ''),
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
            debit: money(line.debit ?? '0'),
            credit: money(line.credit ?? '0'),
            description: line.description ?? '',
            tax_code_id: line.tax_code_id ? String(line.tax_code_id) : '',
            tax_role: line.tax_role ?? '',
            tax_rate:
                line.tax_rate === null || line.tax_rate === undefined
                    ? ''
                    : String(line.tax_rate),
        })) ?? [emptyLine(), emptyLine()],
    });

    // Laravel returns array validation errors with dotted keys (lines.0.debit), which useForm's error type does not model.
    const errors = form.errors as Record<string, string | undefined>;
    const [problems, setProblems] = useState<string[]>([]);
    const [badLines, setBadLines] = useState<number[]>([]);
    const [sourceOpen, setSourceOpen] = useState(
        Boolean(
            (entry?.source_document_type && entry.source_document_type !== '') ||
                entry?.source_document_number,
        ),
    );
    const rowsRef = useRef<HTMLTableSectionElement>(null);

    const lines = form.data.lines;
    const totalDebit = round(lines.reduce((sum, line) => sum + num(line.debit), 0));
    const totalCredit = round(lines.reduce((sum, line) => sum + num(line.credit), 0));
    const diff = round(totalDebit - totalCredit);
    const balanced = Math.abs(diff) < 0.005 && totalDebit > 0;
    const foreign =
        form.data.currency_id !== '' &&
        baseCurrency !== undefined &&
        form.data.currency_id !== String(baseCurrency.id);

    const setLine = (
        index: number,
        patch: Partial<JournalLine>,
    ) => {
        form.setData(
            'lines',
            lines.map((line, lineIndex) =>
                lineIndex === index ? { ...line, ...patch } : line,
            ),
        );
    };

    const setAmount = (index: number, side: 'debit' | 'credit', raw: string) => {
        const value = raw.replace(/[^0-9.,]/g, '');
        const other = side === 'debit' ? 'credit' : 'debit';

        setLine(
            index,
            num(value) > 0 ? { [side]: value, [other]: '' } : { [side]: value },
        );
    };

    const addLine = () => {
        const line = emptyLine();

        // The new line starts with what is still missing, on the opposite side.
        if (diff > 0.004) {
            line.credit = diff.toFixed(2);
        } else if (diff < -0.004) {
            line.debit = Math.abs(diff).toFixed(2);
        }

        form.setData('lines', [...lines, line]);
    };

    const removeLine = (index: number) => {
        if (lines.length <= 2) {
            return;
        }

        form.setData(
            'lines',
            lines.filter((_, lineIndex) => lineIndex !== index),
        );
    };

    const balanceTo = (index: number) => {
        const need = round(
            lines.reduce(
                (sum, line, lineIndex) =>
                    lineIndex === index ? sum : sum + num(line.debit) - num(line.credit),
                0,
            ),
        );

        if (need > 0) {
            setLine(index, { credit: need.toFixed(2), debit: '' });
        } else if (need < 0) {
            setLine(index, { debit: Math.abs(need).toFixed(2), credit: '' });
        }
    };

    const check = (): boolean => {
        const found: string[] = [];
        const bad: number[] = [];
        let valid = 0;

        lines.forEach((line, index) => {
            const amount = num(line.debit) + num(line.credit);

            if (line.chart_of_account_id === '' && amount === 0) {
                return;
            }

            if (line.chart_of_account_id === '') {
                found.push(`Line ${index + 1}: choose an account.`);
                bad.push(index);
            } else if (amount === 0) {
                found.push(`Line ${index + 1}: enter a debit or a credit amount.`);
                bad.push(index);
            } else {
                valid++;
            }
        });

        if (found.length === 0 && valid < 2) {
            found.push('An entry needs at least two lines with an account and an amount.');
        }

        if (found.length === 0 && !balanced) {
            found.push(`Debits and credits must balance (difference ${fmt(Math.abs(diff))}).`);
        }

        setProblems(found);
        setBadLines(bad);

        return found.length === 0;
    };

    const submit = (event?: FormEvent<HTMLFormElement>) => {
        event?.preventDefault();

        if (!check() || form.processing) {
            return;
        }

        form.transform((data) => ({
            ...data,
            ...(branches.length > 0
                ? { branch_id: data.branch_id === '' ? null : data.branch_id }
                : { branch_id: undefined }),
            source_document_type:
                data.source_document_type === 'none'
                    ? ''
                    : data.source_document_type,
            // Rows left empty are not sent.
            lines: data.lines
                .filter((line) => line.chart_of_account_id !== '')
                .map((line) => ({
                    ...line,
                    cost_center_id:
                        line.cost_center_id === 'none' ? '' : line.cost_center_id,
                    debit: String(num(line.debit)),
                    credit: String(num(line.credit)),
                })),
        }));

        if (method === 'put') {
            form.put(action);

            return;
        }

        form.post(action);
    };

    // Ctrl/Cmd + S saves.
    const submitRef = useRef(submit);
    submitRef.current = submit;
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                submitRef.current();
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const fieldClass = 'flex flex-col gap-1.5';

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title={title}
                        description="Build a balanced journal entry. It is saved as a draft; you review and post it afterwards."
                    />
                    <Button asChild variant="outline">
                        <Link href="/accounting/journal-entries">Back</Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="grid gap-6">
                    {problems.length > 0 ? (
                        <div
                            role="alert"
                            className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                        >
                            <strong className="block">Check the entry before saving</strong>
                            <ul className="mt-1 list-disc pl-5">
                                {problems.map((problem) => (
                                    <li key={problem}>{problem}</li>
                                ))}
                            </ul>
                        </div>
                    ) : null}

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Entry details</CardTitle>
                            <CardDescription>
                                Who, when and why. The voucher number and the
                                accounting period are set when the entry is
                                posted.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div className={fieldClass}>
                                    <Label htmlFor="voucher_type_id">Voucher type</Label>
                                    <Dropdown
                                        id="voucher_type_id"
                                        required
                                        value={form.data.voucher_type_id}
                                        onChange={(event) =>
                                            form.setData('voucher_type_id', event.target.value)
                                        }
                                    >
                                        {voucherTypes.map((type) => (
                                            <option key={type.id} value={String(type.id)}>
                                                {type.code} - {type.name}
                                            </option>
                                        ))}
                                    </Dropdown>
                                    <InputError message={form.errors.voucher_type_id} />
                                </div>

                                <div className={fieldClass}>
                                    <Label htmlFor="entry_date">Entry date</Label>
                                    <Input
                                        id="entry_date"
                                        type="date"
                                        value={form.data.entry_date}
                                        onChange={(event) =>
                                            form.setData('entry_date', event.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.entry_date} />
                                </div>

                                <div className={fieldClass}>
                                    <Label htmlFor="currency_id">Currency</Label>
                                    <Dropdown
                                        id="currency_id"
                                        required
                                        value={form.data.currency_id}
                                        onChange={(event) =>
                                            form.setData('currency_id', event.target.value)
                                        }
                                    >
                                        {currencies.map((currency) => (
                                            <option key={currency.id} value={String(currency.id)}>
                                                {currency.code} - {currency.name}
                                            </option>
                                        ))}
                                    </Dropdown>
                                    <InputError message={form.errors.currency_id} />
                                </div>

                                {branches.length > 0 ? (
                                    <div className={fieldClass}>
                                        <Label htmlFor="branch_id">Branch</Label>
                                        <Dropdown
                                            id="branch_id"
                                            value={form.data.branch_id}
                                            onChange={(event) =>
                                                form.setData('branch_id', event.target.value)
                                            }
                                        >
                                            <option value="">Head office / not allocated</option>
                                            {branches.map((branch) => (
                                                <option key={branch.id} value={String(branch.id)}>
                                                    {branch.code} - {branch.name}
                                                </option>
                                            ))}
                                        </Dropdown>
                                        <InputError message={form.errors.branch_id} />
                                    </div>
                                ) : null}

                                {foreign ? (
                                    <div className={fieldClass}>
                                        <Label htmlFor="fx_rate_to_base">
                                            Exchange rate to {baseCurrency?.code}
                                        </Label>
                                        <Input
                                            id="fx_rate_to_base"
                                            type="number"
                                            step="0.00000001"
                                            className="text-right tabular-nums"
                                            value={form.data.fx_rate_to_base}
                                            onChange={(event) =>
                                                form.setData('fx_rate_to_base', event.target.value)
                                            }
                                        />
                                        <InputError message={form.errors.fx_rate_to_base} />
                                    </div>
                                ) : null}

                                <div className={fieldClass}>
                                    <Label htmlFor="reference">Reference</Label>
                                    <Input
                                        id="reference"
                                        placeholder="Cheque no., slip no. …"
                                        value={form.data.reference}
                                        onChange={(event) =>
                                            form.setData('reference', event.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.reference} />
                                </div>

                                <div className={`${fieldClass} md:col-span-2 xl:col-span-4`}>
                                    <Label htmlFor="description">Description</Label>
                                    <Input
                                        id="description"
                                        placeholder="What is this entry for?"
                                        value={form.data.description}
                                        onChange={(event) =>
                                            form.setData('description', event.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.description} />
                                </div>
                            </div>

                            <div>
                                <button
                                    type="button"
                                    className="text-sm font-semibold text-primary hover:underline"
                                    onClick={() => setSourceOpen((open) => !open)}
                                    aria-expanded={sourceOpen}
                                >
                                    {sourceOpen ? '− ' : '+ '}Link a source document{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (invoice, bill or receipt — optional)
                                    </span>
                                </button>
                                {sourceOpen ? (
                                    <div className="mt-3 grid grid-cols-1 gap-4 rounded-md border border-dashed p-3 md:grid-cols-3">
                                        <div className={fieldClass}>
                                            <Label htmlFor="source_document_type">Document type</Label>
                                            <Dropdown
                                                id="source_document_type"
                                                value={form.data.source_document_type}
                                                onChange={(event) =>
                                                    form.setData('source_document_type', event.target.value)
                                                }
                                            >
                                                <option value="none">None</option>
                                                {Object.entries(documentTypes).map(([key, label]) => (
                                                    <option key={key} value={key}>
                                                        {label}
                                                    </option>
                                                ))}
                                            </Dropdown>
                                            <InputError message={form.errors.source_document_type} />
                                        </div>
                                        <div className={fieldClass}>
                                            <Label htmlFor="source_document_number">Document number</Label>
                                            <Input
                                                id="source_document_number"
                                                placeholder="INV-1001"
                                                value={form.data.source_document_number}
                                                onChange={(event) =>
                                                    form.setData('source_document_number', event.target.value)
                                                }
                                            />
                                            <InputError message={form.errors.source_document_number} />
                                        </div>
                                        <div className={fieldClass}>
                                            <Label htmlFor="source_document_date">Document date</Label>
                                            <Input
                                                id="source_document_date"
                                                type="date"
                                                value={form.data.source_document_date}
                                                onChange={(event) =>
                                                    form.setData('source_document_date', event.target.value)
                                                }
                                            />
                                            <InputError message={form.errors.source_document_date} />
                                        </div>
                                        <p className="text-xs text-muted-foreground md:col-span-3">
                                            A document can be posted only once; reverse the entry to post it again.
                                        </p>
                                    </div>
                                ) : null}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-lg">
                        <CardHeader className="flex flex-row items-start justify-between gap-3">
                            <div className="space-y-1.5">
                                <CardTitle>Lines</CardTitle>
                                <CardDescription>
                                    Pick the account by its number or name. Debits must equal credits.
                                </CardDescription>
                            </div>
                            <Button type="button" variant="outline" size="sm" onClick={addLine}>
                                <Plus className="size-4" />
                                Add line
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-[1100px] text-sm [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2">
                                    <thead className="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="w-10 p-3">#</th>
                                            <th className="min-w-72 p-3">Account</th>
                                            <th className="w-52 p-3">Cost center</th>
                                            <th className="w-36 p-3 text-right">Debit</th>
                                            <th className="w-36 p-3 text-right">Credit</th>
                                            <th className="min-w-48 p-3">Note</th>
                                            <th className="w-24 p-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody ref={rowsRef}>
                                        {lines.map((line, index) => (
                                            <tr
                                                key={index}
                                                className={`border-t align-top ${badLines.includes(index) ? 'bg-destructive/5' : ''}`}
                                            >
                                                <td className="p-2 pt-4 text-center text-xs text-muted-foreground">
                                                    {index + 1}
                                                </td>
                                                <td className="p-2">
                                                    <Dropdown
                                                        aria-label="Account"
                                                        value={line.chart_of_account_id}
                                                        options={accountOptions}
                                                        search
                                                        placeholder="Choose an account"
                                                        onChange={(event) =>
                                                            setLine(index, {
                                                                chart_of_account_id: event.target.value,
                                                            })
                                                        }
                                                    />
                                                    <InputError
                                                        message={errors[`lines.${index}.chart_of_account_id`]}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Dropdown
                                                        aria-label="Cost center"
                                                        value={line.cost_center_id}
                                                        options={costCenterOptions}
                                                        onChange={(event) =>
                                                            setLine(index, {
                                                                cost_center_id: event.target.value,
                                                            })
                                                        }
                                                    />
                                                    <InputError
                                                        message={errors[`lines.${index}.cost_center_id`]}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="text"
                                                        inputMode="decimal"
                                                        autoComplete="off"
                                                        aria-label="Debit"
                                                        placeholder="0.00"
                                                        className="text-right tabular-nums"
                                                        value={line.debit}
                                                        onFocus={(event) => event.target.select()}
                                                        onChange={(event) =>
                                                            setAmount(index, 'debit', event.target.value)
                                                        }
                                                        onBlur={() =>
                                                            setLine(index, { debit: money(line.debit) })
                                                        }
                                                    />
                                                    <InputError message={errors[`lines.${index}.debit`]} />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="text"
                                                        inputMode="decimal"
                                                        autoComplete="off"
                                                        aria-label="Credit"
                                                        placeholder="0.00"
                                                        className="text-right tabular-nums"
                                                        value={line.credit}
                                                        onFocus={(event) => event.target.select()}
                                                        onChange={(event) =>
                                                            setAmount(index, 'credit', event.target.value)
                                                        }
                                                        onBlur={() =>
                                                            setLine(index, { credit: money(line.credit) })
                                                        }
                                                    />
                                                    <InputError message={errors[`lines.${index}.credit`]} />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        aria-label="Note"
                                                        placeholder="Optional"
                                                        value={line.description}
                                                        onChange={(event) =>
                                                            setLine(index, { description: event.target.value })
                                                        }
                                                        onKeyDown={(event) => {
                                                            if (event.key === 'Enter') {
                                                                event.preventDefault();

                                                                if (index === lines.length - 1) {
                                                                    addLine();
                                                                }
                                                            }
                                                        }}
                                                    />
                                                    <InputError message={errors[`lines.${index}.description`]} />
                                                </td>
                                                <td className="whitespace-nowrap p-2 text-right">
                                                    {Math.abs(diff) >= 0.005 ? (
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            title="Put the amount that balances the entry on this line"
                                                            onClick={() => balanceTo(index)}
                                                        >
                                                            <Scale className="size-4" />
                                                        </Button>
                                                    ) : null}
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        title="Remove line"
                                                        onClick={() => removeLine(index)}
                                                        disabled={lines.length <= 2}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="border-t bg-muted/50 font-semibold">
                                        <tr>
                                            <td colSpan={3} className="p-3 text-right">
                                                Totals
                                            </td>
                                            <td className="p-3 text-right tabular-nums">{fmt(totalDebit)}</td>
                                            <td className="p-3 text-right tabular-nums">{fmt(totalCredit)}</td>
                                            <td colSpan={2}></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="sticky bottom-3 z-20 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-background px-4 py-3 shadow-lg">
                        <div className="flex flex-wrap items-center gap-3">
                            {balanced ? (
                                <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                    ● Balanced — ready to save
                                </span>
                            ) : (
                                <span className="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800 dark:bg-rose-500/15 dark:text-rose-300">
                                    ●{' '}
                                    {totalDebit === 0 && totalCredit === 0
                                        ? 'Enter the amounts'
                                        : `Out of balance by ${fmt(Math.abs(diff))} (${diff > 0 ? 'credit' : 'debit'} short)`}
                                </span>
                            )}
                            <span className="text-xs text-muted-foreground">Ctrl + S saves</span>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="auto_post"
                                    checked={form.data.auto_post}
                                    onCheckedChange={(checked) =>
                                        form.setData('auto_post', checked === true)
                                    }
                                />
                                <Label htmlFor="auto_post" className="text-sm font-normal">
                                    Post after saving
                                </Label>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button asChild variant="outline" type="button">
                                <Link href="/accounting/journal-entries">Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing || !balanced}>
                                <Save className="size-4" />
                                {form.processing
                                    ? 'Saving…'
                                    : method === 'put'
                                      ? 'Update journal'
                                      : 'Save journal'}
                            </Button>
                        </div>
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
