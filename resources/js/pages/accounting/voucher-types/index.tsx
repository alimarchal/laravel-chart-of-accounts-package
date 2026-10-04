import { Head, router, useForm } from '@inertiajs/react';
import { Hash, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type VoucherType = {
    id: number;
    code: string;
    name: string;
    prefix: string;
    format: string;
    reset: 'yearly' | 'monthly' | 'never';
    description: string | null;
    is_active: boolean;
    is_system: boolean;
    next_number: string;
    entries_count: number;
};

type Fields = {
    code: string;
    name: string;
    prefix: string;
    format: string;
    reset: string;
    description: string;
    is_active: boolean;
};

const resetLabels: Record<string, string> = {
    yearly: 'Every fiscal year',
    monthly: 'Every month',
    never: 'Never (one running series)',
};

const tokens = [
    ['{PREFIX}', 'the prefix'],
    ['{FY}', 'fiscal year: 2026, or 2025-26'],
    ['{YYYY}', 'year of the entry date'],
    ['{YY}', 'two-digit year'],
    ['{MM}', 'month'],
    ['{SEQ:5}', 'number, padded to 5 digits'],
];

/** Client-side example of a format, for today's date and number 1. */
function example(format: string, prefix: string): string {
    const now = new Date();
    const year = String(now.getFullYear());

    return format
        .replaceAll('{PREFIX}', prefix || 'JV')
        .replaceAll('{FY}', year)
        .replaceAll('{YYYY}', year)
        .replaceAll('{YY}', year.slice(-2))
        .replaceAll('{MM}', String(now.getMonth() + 1).padStart(2, '0'))
        .replace(/\{SEQ(?::(\d{1,2}))?\}/g, (_, width) =>
            '1'.padStart(Number(width ?? 0), '0'),
        );
}

function VoucherTypeForm({
    type,
    resets,
    defaultFormat,
    onDone,
}: {
    type: VoucherType | null;
    resets: string[];
    defaultFormat: string;
    onDone: () => void;
}) {
    const form = useForm<Fields>({
        code: type?.code ?? '',
        name: type?.name ?? '',
        prefix: type?.prefix ?? '',
        format: type?.format ?? defaultFormat,
        reset: type?.reset ?? 'yearly',
        description: type?.description ?? '',
        is_active: type?.is_active ?? true,
    });
    const locked = (type?.entries_count ?? 0) > 0;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };

        if (type) {
            form.put(`/accounting/voucher-types/${type.id}`, options);
        } else {
            form.post('/accounting/voucher-types', options);
        }
    };

    return (
        <form
            onSubmit={submit}
            className="space-y-4 rounded-lg border bg-muted/20 p-4"
        >
            <div className="font-medium">
                {type ? `Edit ${type.code}` : 'New voucher type'}
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                <div className="flex flex-col gap-1">
                    <Label htmlFor="code">Code</Label>
                    <Input
                        id="code"
                        value={form.data.code}
                        disabled={locked}
                        placeholder="SV"
                        onChange={(event) =>
                            form.setData(
                                'code',
                                event.target.value.toUpperCase(),
                            )
                        }
                    />
                    <InputError message={form.errors.code} />
                </div>
                <div className="flex flex-col gap-1 md:col-span-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        placeholder="Sales Voucher"
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="flex flex-col gap-1">
                    <Label htmlFor="prefix">Prefix</Label>
                    <Input
                        id="prefix"
                        value={form.data.prefix}
                        placeholder="SV"
                        onChange={(event) =>
                            form.setData('prefix', event.target.value)
                        }
                    />
                    <InputError message={form.errors.prefix} />
                </div>
                <div className="flex flex-col gap-1">
                    <Label htmlFor="format">Number format</Label>
                    <Input
                        id="format"
                        className="font-mono"
                        value={form.data.format}
                        onChange={(event) =>
                            form.setData('format', event.target.value)
                        }
                    />
                    <InputError message={form.errors.format} />
                </div>
                <div className="flex flex-col gap-1">
                    <Label htmlFor="reset">Numbering restarts</Label>
                    <select
                        id="reset"
                        disabled={locked}
                        className="h-9 rounded-md border bg-transparent px-3 text-sm"
                        value={form.data.reset}
                        onChange={(event) =>
                            form.setData('reset', event.target.value)
                        }
                    >
                        {resets.map((reset) => (
                            <option key={reset} value={reset}>
                                {resetLabels[reset] ?? reset}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.reset} />
                </div>
                <div className="flex flex-col gap-1 md:col-span-3">
                    <Label htmlFor="description">Description</Label>
                    <Input
                        id="description"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-background p-3 text-sm">
                <span>
                    Example:{' '}
                    <span className="font-mono font-semibold">
                        {example(form.data.format, form.data.prefix)}
                    </span>
                </span>
                <span className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    {tokens.map(([token, meaning]) => (
                        <span key={token}>
                            <code>{token}</code> {meaning}
                        </span>
                    ))}
                </span>
            </div>

            {locked ? (
                <p className="text-xs text-muted-foreground">
                    {type?.entries_count} entries carry numbers of this series,
                    so its code and restart rule are fixed.
                </p>
            ) : null}

            <div className="flex flex-wrap items-center gap-3">
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        disabled={type?.is_system}
                        onChange={(event) =>
                            form.setData('is_active', event.target.checked)
                        }
                    />
                    Active
                </label>
                <Button type="submit" disabled={form.processing}>
                    {type ? 'Save changes' : 'Create voucher type'}
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

export default function VoucherTypes({
    voucherTypes,
    resets,
    defaultFormat,
}: {
    voucherTypes: VoucherType[];
    resets: string[];
    defaultFormat: string;
}) {
    const { permissions, flash } = useAccounting();
    const [editing, setEditing] = useState<VoucherType | 'new' | null>(null);

    const remove = (type: VoucherType) => {
        if (window.confirm(`Delete voucher type ${type.code}?`)) {
            router.delete(`/accounting/voucher-types/${type.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Voucher Types" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title="Voucher Types"
                        description="Each type has its own gapless number series. A number is issued when an entry is posted, so voided drafts never leave gaps."
                    />
                    {permissions['voucher-types.create'] ? (
                        <Button onClick={() => setEditing('new')}>
                            <Plus className="size-4" /> New voucher type
                        </Button>
                    ) : null}
                </div>

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                {editing ? (
                    <VoucherTypeForm
                        key={editing === 'new' ? 'new' : editing.id}
                        type={editing === 'new' ? null : editing}
                        resets={resets}
                        defaultFormat={defaultFormat}
                        onDone={() => setEditing(null)}
                    />
                ) : null}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[820px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Type</th>
                                <th className="p-3 font-medium">Format</th>
                                <th className="p-3 font-medium">Restarts</th>
                                <th className="p-3 font-medium">Next number</th>
                                <th className="p-3 text-right font-medium">
                                    Numbered entries
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {voucherTypes.map((type) => (
                                <tr
                                    key={type.id}
                                    className="border-t hover:bg-muted/30"
                                >
                                    <td className="p-3">
                                        <div className="flex items-center gap-2 font-medium">
                                            <Hash className="size-4 text-muted-foreground" />
                                            {type.code}
                                            <span className="font-normal">
                                                {type.name}
                                            </span>
                                            {type.is_system ? (
                                                <span className="rounded bg-indigo-500/10 px-1.5 py-0.5 text-xs text-indigo-700 dark:text-indigo-300">
                                                    default
                                                </span>
                                            ) : null}
                                            {!type.is_active ? (
                                                <span className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                    inactive
                                                </span>
                                            ) : null}
                                        </div>
                                        {type.description ? (
                                            <div className="text-xs text-muted-foreground">
                                                {type.description}
                                            </div>
                                        ) : null}
                                    </td>
                                    <td className="p-3 font-mono text-xs">
                                        {type.format}
                                    </td>
                                    <td className="p-3">
                                        {resetLabels[type.reset] ?? type.reset}
                                    </td>
                                    <td className="p-3 font-mono">
                                        {type.next_number}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {type.entries_count}
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        {permissions['voucher-types.update'] ? (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => setEditing(type)}
                                            >
                                                <Pencil className="size-4" />{' '}
                                                Edit
                                            </Button>
                                        ) : null}
                                        {permissions['voucher-types.delete'] &&
                                        !type.is_system &&
                                        type.entries_count === 0 ? (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => remove(type)}
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
            </div>
        </>
    );
}

VoucherTypes.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Voucher Types', href: '/accounting/voucher-types' },
    ],
};
