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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccountingI18n } from '@/lib/i18n';

type Line = {
    chart_of_account_id: string;
    cost_center_id: string;
    debit: string;
    credit: string;
    description: string;
};
type Entry = {
    id: number;
    name: string;
    frequency: string;
    interval: number;
    day_of_month: number | null;
    start_date: string;
    end_date: string | null;
    max_runs: number | null;
    runs_count: number;
    mode: 'draft' | 'post';
    voucher_type_id: number | null;
    reference: string | null;
    description: string | null;
    lines: Array<{
        chart_of_account_id: number;
        cost_center_id: number | null;
        debit: string;
        credit: string;
        description: string | null;
    }>;
};
type Props = {
    entry: Entry | null;
    accounts: Array<{ id: number; account_code: string; account_name: string }>;
    costCenters: Array<{ id: number; code: string; name: string }>;
    voucherTypes: Array<{ id: number; code: string; name: string }>;
    frequencies: Record<string, string>;
    today: string;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const emptyLine = (): Line => ({
    chart_of_account_id: '',
    cost_center_id: '',
    debit: '',
    credit: '',
    description: '',
});
const cents = (value: string) => Math.round(Number(value || 0) * 100);

export default function RecurringEntryForm({
    entry,
    accounts,
    costCenters,
    voucherTypes,
    frequencies,
    today,
}: Props) {
    useAccountingI18n();
    const form = useForm({
        name: entry?.name ?? '',
        voucher_type_id: entry?.voucher_type_id
            ? String(entry.voucher_type_id)
            : '',
        frequency: entry?.frequency ?? 'monthly',
        interval: String(entry?.interval ?? 1),
        day_of_month: entry?.day_of_month ? String(entry.day_of_month) : '',
        start_date: entry?.start_date ?? today,
        end_date: entry?.end_date ?? '',
        max_runs: entry?.max_runs ? String(entry.max_runs) : '',
        mode: entry?.mode ?? 'draft',
        reference: entry?.reference ?? '',
        description: entry?.description ?? '',
        lines: entry?.lines.map((line) => ({
            chart_of_account_id: String(line.chart_of_account_id),
            cost_center_id: line.cost_center_id
                ? String(line.cost_center_id)
                : '',
            debit: Number(line.debit) > 0 ? String(Number(line.debit)) : '',
            credit: Number(line.credit) > 0 ? String(Number(line.credit)) : '',
            description: line.description ?? '',
        })) ?? [emptyLine(), emptyLine()],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const accountOptions = accounts.map((account) => ({
        value: String(account.id),
        label: `${account.account_code} - ${account.account_name}`,
    }));
    const costCenterOptions = [
        { value: '', label: 'None' },
        ...costCenters.map((center) => ({
            value: String(center.id),
            label: `${center.code} - ${center.name}`,
        })),
    ];
    const debit = form.data.lines.reduce(
        (sum, line) => sum + cents(line.debit),
        0,
    );
    const credit = form.data.lines.reduce(
        (sum, line) => sum + cents(line.credit),
        0,
    );
    const monthly = ['monthly', 'quarterly', 'yearly'].includes(
        form.data.frequency,
    );

    const setLine = (index: number, patch: Partial<Line>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, i) =>
                i === index ? { ...line, ...patch } : line,
            ),
        );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (entry) {
            form.put(`/accounting/recurring-entries/${entry.id}`);
        } else {
            form.post('/accounting/recurring-entries');
        }
    };

    return (
        <>
            <Head
                title={entry ? 'Edit Recurring Entry' : 'New Recurring Entry'}
            />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title={
                            entry
                                ? 'Edit Recurring Entry'
                                : 'New Recurring Entry'
                        }
                        description="A journal entry that repeats on a schedule."
                    />
                    <Button asChild variant="outline">
                        <Link href="/accounting/recurring-entries">Back</Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="grid gap-6">
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Schedule</CardTitle>
                            <CardDescription>
                                Entries are dated on the scheduled day.
                                Month-end dates are kept (the 31st becomes the
                                28th in February).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div className="grid gap-2 xl:col-span-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="frequency">Repeats</Label>
                                    <select
                                        id="frequency"
                                        className={selectClass}
                                        value={form.data.frequency}
                                        onChange={(event) =>
                                            form.setData(
                                                'frequency',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {Object.entries(frequencies).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    <InputError
                                        message={form.errors.frequency}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="interval">Every</Label>
                                    <Input
                                        id="interval"
                                        type="number"
                                        min={1}
                                        value={form.data.interval}
                                        onChange={(event) =>
                                            form.setData(
                                                'interval',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.interval}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="start_date">
                                        First entry on
                                    </Label>
                                    <Input
                                        id="start_date"
                                        type="date"
                                        value={form.data.start_date}
                                        disabled={Boolean(
                                            entry && entry.runs_count > 0,
                                        )}
                                        onChange={(event) =>
                                            form.setData(
                                                'start_date',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.start_date}
                                    />
                                </div>
                                {monthly ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="day_of_month">
                                            Day of month
                                        </Label>
                                        <Input
                                            id="day_of_month"
                                            type="number"
                                            min={1}
                                            max={31}
                                            placeholder="Same as first"
                                            value={form.data.day_of_month}
                                            onChange={(event) =>
                                                form.setData(
                                                    'day_of_month',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.day_of_month}
                                        />
                                    </div>
                                ) : null}
                                <div className="grid gap-2">
                                    <Label htmlFor="end_date">
                                        Stop after (date)
                                    </Label>
                                    <Input
                                        id="end_date"
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'end_date',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.end_date}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="max_runs">
                                        Stop after (entries)
                                    </Label>
                                    <Input
                                        id="max_runs"
                                        type="number"
                                        min={1}
                                        value={form.data.max_runs}
                                        onChange={(event) =>
                                            form.setData(
                                                'max_runs',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.max_runs}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="mode">Each entry is</Label>
                                    <select
                                        id="mode"
                                        className={selectClass}
                                        value={form.data.mode}
                                        onChange={(event) =>
                                            form.setData(
                                                'mode',
                                                event.target.value as
                                                    | 'draft'
                                                    | 'post',
                                            )
                                        }
                                    >
                                        <option value="draft">
                                            Created as a draft
                                        </option>
                                        <option value="post">
                                            Posted automatically
                                        </option>
                                    </select>
                                    {form.data.mode === 'post' ? (
                                        <p className="text-xs text-muted-foreground">
                                            Posted as you, if you may post;
                                            otherwise (or if the period is
                                            closed, or approval is needed) it
                                            stays a draft or goes for approval.
                                        </p>
                                    ) : null}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="voucher_type_id">
                                        Voucher type
                                    </Label>
                                    <select
                                        id="voucher_type_id"
                                        className={selectClass}
                                        value={form.data.voucher_type_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'voucher_type_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            Journal voucher (default)
                                        </option>
                                        {voucherTypes.map((type) => (
                                            <option
                                                key={type.id}
                                                value={type.id}
                                            >
                                                {type.code} - {type.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="reference">Reference</Label>
                                    <Input
                                        id="reference"
                                        value={form.data.reference}
                                        placeholder="REC-…"
                                        onChange={(event) =>
                                            form.setData(
                                                'reference',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Narration
                                    </Label>
                                    <Input
                                        id="description"
                                        value={form.data.description}
                                        onChange={(event) =>
                                            form.setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Lines</CardTitle>
                            <CardDescription>
                                Debits must equal credits. The amounts are in
                                the base currency.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {form.data.lines.map((line, index) => (
                                <div
                                    key={index}
                                    className="grid gap-2 md:grid-cols-[2fr_1.2fr_1fr_1fr_1.5fr_auto] md:items-start"
                                >
                                    <div>
                                        <SearchableSelect
                                            value={line.chart_of_account_id}
                                            options={accountOptions}
                                            onChange={(value) =>
                                                setLine(index, {
                                                    chart_of_account_id: value,
                                                })
                                            }
                                            placeholder="Account"
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `lines.${index}.chart_of_account_id`
                                                ]
                                            }
                                        />
                                    </div>
                                    <SearchableSelect
                                        value={line.cost_center_id}
                                        options={costCenterOptions}
                                        onChange={(value) =>
                                            setLine(index, {
                                                cost_center_id: value,
                                            })
                                        }
                                        placeholder="Cost center"
                                    />
                                    <div>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            placeholder="Debit"
                                            value={line.debit}
                                            onChange={(event) =>
                                                setLine(index, {
                                                    debit: event.target.value,
                                                    credit: event.target.value
                                                        ? ''
                                                        : line.credit,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[`lines.${index}.debit`]
                                            }
                                        />
                                    </div>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        placeholder="Credit"
                                        value={line.credit}
                                        onChange={(event) =>
                                            setLine(index, {
                                                credit: event.target.value,
                                                debit: event.target.value
                                                    ? ''
                                                    : line.debit,
                                            })
                                        }
                                    />
                                    <Input
                                        placeholder="Line note"
                                        value={line.description}
                                        onChange={(event) =>
                                            setLine(index, {
                                                description: event.target.value,
                                            })
                                        }
                                    />
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        disabled={form.data.lines.length <= 2}
                                        onClick={() =>
                                            form.setData(
                                                'lines',
                                                form.data.lines.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}
                            <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="gap-2"
                                    onClick={() =>
                                        form.setData('lines', [
                                            ...form.data.lines,
                                            emptyLine(),
                                        ])
                                    }
                                >
                                    <Plus className="size-4" />
                                    Add line
                                </Button>
                                <div
                                    className={`text-sm tabular-nums ${debit === credit && debit > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-destructive'}`}
                                >
                                    Debit {(debit / 100).toFixed(2)} · Credit{' '}
                                    {(credit / 100).toFixed(2)}
                                    {debit === credit && debit > 0
                                        ? ' · balanced'
                                        : ` · difference ${(Math.abs(debit - credit) / 100).toFixed(2)}`}
                                </div>
                            </div>
                            <InputError message={errors.lines} />
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Button
                            type="submit"
                            className="gap-2"
                            disabled={form.processing}
                        >
                            <Save className="size-4" />
                            {entry ? 'Save changes' : 'Create recurring entry'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
