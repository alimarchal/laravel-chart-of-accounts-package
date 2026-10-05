import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, GitMerge, Hash } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { SearchableSelect } from '@/components/accounting/searchable-select';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Account = {
    id: number;
    account_code: string;
    account_name: string;
    is_group: boolean;
    is_active: boolean;
    merged_into: string | null;
};

type MergePlan = {
    target: { account_code: string; account_name: string };
    transfers: Array<{
        cost_center: string | null;
        amount: string;
        side: 'debit' | 'credit';
    }>;
    balance: string;
    draft_lines: number;
    children: string[];
    bank_accounts: string[];
    problems: string[];
};

type Props = {
    account: Account;
    renumber: {
        code: string;
        with_children: boolean;
        plan: Record<string, string>;
        error: string | null;
    } | null;
    merge: MergePlan | null;
    targets: Array<{ id: number; label: string }>;
    today: string;
};

export default function Restructure({
    account,
    renumber,
    merge,
    targets,
    today,
}: Props) {
    const { flash } = useAccounting();
    const base = `/accounting/chart-of-accounts/${account.id}`;
    const [code, setCode] = useState(renumber?.code ?? '');
    const [withChildren, setWithChildren] = useState(
        renumber?.with_children ?? true,
    );
    const [target, setTarget] = useState('');
    const [date, setDate] = useState(today);
    const [description, setDescription] = useState('');
    const [busy, setBusy] = useState(false);

    const query = (params: Record<string, string>) =>
        router.get(base + '/restructure', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const previewRenumber = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        query({ renumber_code: code, with_children: withChildren ? '1' : '0' });
    };

    const previewMerge = (value: string) => {
        setTarget(value);
        if (value !== '') {
            query({ merge_target: value });
        }
    };

    const post = (url: string, data: Record<string, string | boolean>) =>
        router.post(url, data, {
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });

    const renumberRows = renumber ? Object.entries(renumber.plan) : [];

    return (
        <>
            <Head title={`Renumber or merge ${account.account_code}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <Heading
                    title={`${account.account_code} ${account.account_name}`}
                    description="Give the account a new code, or merge it into another account. The ledger history stays as it is."
                />

                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}
                {account.merged_into ? (
                    <Alert>
                        <AlertTitle>Already merged</AlertTitle>
                        <AlertDescription>
                            This account was merged into {account.merged_into}.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Hash className="size-4" /> Renumber
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <form
                                onSubmit={previewRenumber}
                                className="space-y-3"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="renumber_code">
                                        New code
                                    </Label>
                                    <Input
                                        id="renumber_code"
                                        value={code}
                                        onChange={(event) =>
                                            setCode(event.target.value)
                                        }
                                        maxLength={30}
                                    />
                                </div>
                                {account.is_group ? (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={withChildren}
                                            onChange={(event) =>
                                                setWithChildren(
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                        Renumber the sub-accounts sharing its
                                        prefix too
                                    </label>
                                ) : null}
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={code.trim() === ''}
                                >
                                    Preview
                                </Button>
                            </form>

                            {renumber?.error ? (
                                <p className="text-sm text-destructive">
                                    {renumber.error}
                                </p>
                            ) : null}
                            {renumberRows.length > 0 ? (
                                <>
                                    <div className="max-h-72 overflow-y-auto rounded-md border">
                                        <table className="w-full text-sm">
                                            <tbody>
                                                {renumberRows.map(
                                                    ([from, to]) => (
                                                        <tr
                                                            key={from}
                                                            className="border-t first:border-t-0"
                                                        >
                                                            <td className="px-3 py-1.5 font-mono">
                                                                {from}
                                                            </td>
                                                            <td className="px-1 text-muted-foreground">
                                                                <ArrowRight className="size-3" />
                                                            </td>
                                                            <td className="px-3 py-1.5 font-mono font-semibold">
                                                                {to}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <Button
                                        type="button"
                                        disabled={busy}
                                        onClick={() =>
                                            post(base + '/renumber', {
                                                account_code: renumber!.code,
                                                with_children:
                                                    renumber!.with_children,
                                            })
                                        }
                                    >
                                        Renumber {renumberRows.length} account
                                        {renumberRows.length === 1 ? '' : 's'}
                                    </Button>
                                </>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <GitMerge className="size-4" /> Merge into
                                another account
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                For duplicates. The balance moves to the other
                                account with a posted transfer entry (per cost
                                center), drafts, sub-accounts and bank accounts
                                follow, and {account.account_code} is
                                deactivated. Accounts of the same type only.
                            </p>
                            <div className="grid gap-2">
                                <Label>Merge into</Label>
                                <SearchableSelect
                                    value={target}
                                    onChange={previewMerge}
                                    options={targets.map((option) => ({
                                        value: String(option.id),
                                        label: option.label,
                                    }))}
                                    placeholder="Choose an account"
                                />
                            </div>

                            {merge ? (
                                <div className="space-y-3">
                                    {merge.problems.length > 0 ? (
                                        <Alert variant="destructive">
                                            <AlertTitle>
                                                Cannot merge
                                            </AlertTitle>
                                            <AlertDescription>
                                                <ul className="list-disc pl-4">
                                                    {merge.problems.map(
                                                        (problem) => (
                                                            <li key={problem}>
                                                                {problem}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}
                                    <ul className="space-y-1 text-sm">
                                        <li>
                                            Balance to move:{' '}
                                            <span className="font-semibold tabular-nums">
                                                {merge.balance}
                                            </span>
                                            {merge.transfers.length > 0
                                                ? ` (${merge.transfers.map((t) => `${t.cost_center ?? 'no cost center'}: ${t.amount} ${t.side}`).join(', ')})`
                                                : ' — nothing to transfer'}
                                        </li>
                                        <li>
                                            Draft lines moved:{' '}
                                            {merge.draft_lines}
                                        </li>
                                        <li>
                                            Sub-accounts moved:{' '}
                                            {merge.children.length > 0
                                                ? merge.children.join(', ')
                                                : 'none'}
                                        </li>
                                        <li>
                                            Bank accounts moved:{' '}
                                            {merge.bank_accounts.length > 0
                                                ? merge.bank_accounts.join(', ')
                                                : 'none'}
                                        </li>
                                    </ul>
                                    {merge.problems.length === 0 ? (
                                        <div className="grid gap-3 md:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="merge_date">
                                                    Transfer date
                                                </Label>
                                                <Input
                                                    id="merge_date"
                                                    type="date"
                                                    value={date}
                                                    onChange={(event) =>
                                                        setDate(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="merge_description">
                                                    Narration (optional)
                                                </Label>
                                                <Input
                                                    id="merge_description"
                                                    value={description}
                                                    onChange={(event) =>
                                                        setDescription(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                    ) : null}
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={
                                            busy ||
                                            merge.problems.length > 0 ||
                                            target === ''
                                        }
                                        onClick={() => {
                                            if (
                                                window.confirm(
                                                    `Merge ${account.account_code} into ${merge.target.account_code}? This posts a transfer entry and deactivates ${account.account_code}.`,
                                                )
                                            ) {
                                                post(base + '/merge', {
                                                    target_account_id: target,
                                                    date,
                                                    description,
                                                });
                                            }
                                        }}
                                    >
                                        Merge into {merge.target.account_code}
                                    </Button>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <Button variant="outline" asChild>
                        <Link href="/accounting/chart-of-accounts">
                            Back to the chart
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

Restructure.layout = {
    breadcrumbs: [
        { title: 'Chart of Accounts', href: '/accounting/chart-of-accounts' },
        { title: 'Renumber or merge', href: '#' },
    ],
};
