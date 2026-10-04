import { Head, Link, router, useForm } from '@inertiajs/react';
import { ShieldCheck, Sparkles } from 'lucide-react';
import { money } from '@/components/accounting/ledger';
import { SearchableSelect } from '@/components/accounting/searchable-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type ControlAccount = {
    id: number;
    account_code: string;
    account_name: string;
    control_type: string;
    control_label: string;
    balance: string;
    manual_postings: number;
};

type Account = {
    id: number;
    account_code: string;
    account_name: string;
    control_type: string | null;
};

type ManualPosting = {
    id: number;
    voucher_number: string | null;
    entry_date: string;
    reference: string | null;
    description: string | null;
};

export default function ControlAccounts({
    controlAccounts,
    types,
    recommended,
    accounts,
    selected,
}: {
    controlAccounts: ControlAccount[];
    types: Record<string, string>;
    recommended: Record<string, string>;
    accounts: Account[];
    selected: {
        account: Account;
        manualPostings: ManualPosting[];
    } | null;
}) {
    const { permissions, flash } = useAccounting();
    const canManage = permissions['control-accounts.manage'] === true;
    const form = useForm({ account_id: '', control_type: '' });

    const applyRecommended = () => {
        const list = Object.entries(recommended)
            .map(([code, type]) => `${code} → ${types[type] ?? type}`)
            .join('\n');

        if (
            window.confirm(
                `Mark these accounts as control accounts?\n\n${list}\n\nManual journal entries to them will then need the "control-accounts.post-manual" permission.`,
            )
        ) {
            router.post(
                '/accounting/control-accounts/recommended',
                {},
                { preserveScroll: true },
            );
        }
    };

    const clear = (account: ControlAccount) => {
        if (
            window.confirm(
                `Stop treating ${account.account_code} ${account.account_name} as a control account?`,
            )
        ) {
            router.put(
                `/accounting/chart-of-accounts/${account.id}/control-type`,
                { control_type: null },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Control Accounts" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title="Control Accounts"
                        description="Accounts that summarise a sub-ledger (customers, suppliers, stock …). Only that module posts to them, so their balance always agrees with the sub-ledger; manual entries need the control-accounts.post-manual permission."
                    />
                    {canManage ? (
                        <Button variant="outline" onClick={applyRecommended}>
                            <Sparkles className="size-4" /> Recommended setup
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

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Account</th>
                                <th className="p-3 font-medium">Controls</th>
                                <th className="p-3 text-right font-medium">
                                    Balance
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Manual postings
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {controlAccounts.length ? (
                                controlAccounts.map((account) => (
                                    <tr
                                        key={account.id}
                                        className="border-t hover:bg-muted/30"
                                    >
                                        <td className="p-3">
                                            <span className="font-mono text-xs">
                                                {account.account_code}
                                            </span>{' '}
                                            {account.account_name}
                                        </td>
                                        <td className="p-3">
                                            <span className="inline-flex items-center gap-1 rounded-full bg-indigo-500/10 px-2 py-0.5 text-xs text-indigo-700 dark:text-indigo-300">
                                                <ShieldCheck className="size-3" />
                                                {account.control_label}
                                            </span>
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {money(account.balance)}
                                        </td>
                                        <td className="p-3 text-right">
                                            {account.manual_postings ? (
                                                <Link
                                                    href={`/accounting/control-accounts?account=${account.id}`}
                                                    preserveScroll
                                                    className="font-medium text-amber-700 underline-offset-4 hover:underline dark:text-amber-400"
                                                >
                                                    {account.manual_postings}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    0
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-3 text-right">
                                            {canManage ? (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        clear(account)
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        className="p-6 text-center text-muted-foreground"
                                        colSpan={5}
                                    >
                                        No control accounts yet.{' '}
                                        {canManage
                                            ? 'Use Recommended setup, or mark an account below.'
                                            : ''}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {selected ? (
                    <div className="rounded-lg border">
                        <div className="border-b p-4">
                            <div className="font-medium">
                                Manual postings to{' '}
                                {selected.account.account_code}{' '}
                                {selected.account.account_name}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                Posted entries that did not come from the
                                account&apos;s module. Each should be a
                                documented controller adjustment.
                            </div>
                        </div>
                        <table className="w-full text-sm">
                            <tbody>
                                {selected.manualPostings.map((entry) => (
                                    <tr key={entry.id} className="border-t">
                                        <td className="p-3 whitespace-nowrap">
                                            <Link
                                                href={`/accounting/journal-entries/${entry.id}`}
                                                className="font-mono underline-offset-4 hover:underline"
                                            >
                                                {entry.voucher_number ??
                                                    `#${entry.id}`}
                                            </Link>
                                        </td>
                                        <td className="p-3 whitespace-nowrap tabular-nums">
                                            {entry.entry_date.slice(0, 10)}
                                        </td>
                                        <td className="p-3">
                                            {entry.reference ?? ''}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {entry.description ?? ''}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : null}

                {canManage ? (
                    <form
                        className="flex flex-wrap items-end gap-3 rounded-lg border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.transform((data) => ({
                                control_type: data.control_type || null,
                            }));
                            form.put(
                                `/accounting/chart-of-accounts/${form.data.account_id}/control-type`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => form.reset(),
                                },
                            );
                        }}
                    >
                        <div className="flex min-w-72 flex-col gap-1">
                            <Label>Account</Label>
                            <SearchableSelect
                                value={form.data.account_id}
                                options={accounts.map((account) => ({
                                    value: String(account.id),
                                    label: `${account.account_code} - ${account.account_name}`,
                                }))}
                                placeholder="Search posting account"
                                onChange={(value) =>
                                    form.setData('account_id', value)
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="control_type">Controls</Label>
                            <select
                                id="control_type"
                                className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                value={form.data.control_type}
                                onChange={(event) =>
                                    form.setData(
                                        'control_type',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Choose…</option>
                                {Object.entries(types).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.control_type} />
                        </div>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.account_id ||
                                !form.data.control_type
                            }
                        >
                            Mark as control account
                        </Button>
                    </form>
                ) : null}
            </div>
        </>
    );
}

ControlAccounts.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Control Accounts', href: '/accounting/control-accounts' },
    ],
};
