import { Head, Link, router } from '@inertiajs/react';
import { Filter } from 'lucide-react';
import { useState } from 'react';
import {
    ExportButtons,
    type LedgerLine,
    money,
    type Paginated,
    Pagination,
    SummaryCards,
} from '@/components/accounting/ledger';
import { SearchableSelect } from '@/components/accounting/searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type Account = {
    id: number;
    account_code: string;
    account_name: string;
    normal_balance?: string;
};
type Filters = {
    account_id?: string | number;
    date_from?: string;
    date_to?: string;
};

type Statement = {
    account: Account;
    opening_balance: string;
    entries: Paginated<LedgerLine>;
    totals: { debit: string; credit: string; closing_balance: string };
};

type Props = {
    accounts: Account[];
    filters: Filters;
    statement: Statement | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting' },
    {
        title: 'Account Statement',
        href: '/accounting/reports/account-statement',
    },
];

export default function AccountStatement({
    accounts,
    filters,
    statement,
}: Props) {
    const [form, setForm] = useState({
        account_id: filters.account_id ? String(filters.account_id) : '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
    });

    const apply = () =>
        router.get('/accounting/reports/account-statement', form, {
            preserveState: true,
            preserveScroll: true,
        });
    const options = accounts.map((account) => ({
        value: String(account.id),
        label: `${account.account_code} — ${account.account_name}`,
    }));

    return (
        <>
            <Head title="Account Statement" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Account Statement
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Opening balance, every posted line with its running
                            balance, and closing balance (base currency).
                        </p>
                    </div>
                    {statement ? (
                        <ExportButtons
                            base="/accounting/reports/account-statement/export"
                            filters={filters}
                        />
                    ) : null}
                </div>

                <div className="grid items-end gap-3 rounded-lg border p-4 md:grid-cols-[2fr_1fr_1fr_auto]">
                    <div className="flex flex-col gap-1">
                        <Label>Account</Label>
                        <SearchableSelect
                            value={form.account_id}
                            options={options}
                            onChange={(value) =>
                                setForm({ ...form, account_id: value })
                            }
                            placeholder="Choose an account…"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="date_from">From</Label>
                        <Input
                            id="date_from"
                            type="date"
                            value={form.date_from}
                            onChange={(e) =>
                                setForm({ ...form, date_from: e.target.value })
                            }
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="date_to">To</Label>
                        <Input
                            id="date_to"
                            type="date"
                            value={form.date_to}
                            onChange={(e) =>
                                setForm({ ...form, date_to: e.target.value })
                            }
                        />
                    </div>
                    <Button
                        onClick={apply}
                        variant="secondary"
                        disabled={!form.account_id}
                    >
                        <Filter className="size-4" /> Show statement
                    </Button>
                </div>

                {!statement ? (
                    <div className="rounded-lg border border-dashed p-10 text-center text-muted-foreground">
                        Choose an account to see its statement.
                    </div>
                ) : (
                    <>
                        <div className="text-lg font-medium">
                            <span className="font-mono">
                                {statement.account.account_code}
                            </span>{' '}
                            {statement.account.account_name}
                        </div>
                        <SummaryCards
                            items={[
                                {
                                    label: 'Opening balance',
                                    value: statement.opening_balance,
                                },
                                {
                                    label: 'Debits',
                                    value: statement.totals.debit,
                                },
                                {
                                    label: 'Credits',
                                    value: statement.totals.credit,
                                },
                                {
                                    label: 'Closing balance',
                                    value: statement.totals.closing_balance,
                                    tone:
                                        Number(
                                            statement.totals.closing_balance,
                                        ) < 0
                                            ? 'negative'
                                            : 'positive',
                                },
                            ]}
                        />
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[860px] text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">
                                            Date
                                        </th>
                                        <th className="p-3 font-medium">
                                            Entry
                                        </th>
                                        <th className="p-3 font-medium">
                                            Description
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Debit
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Credit
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Balance
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {statement.entries.current_page === 1 ? (
                                        <tr className="border-t bg-muted/20">
                                            <td className="p-3" colSpan={5}>
                                                Opening balance
                                            </td>
                                            <td className="p-3 text-right font-medium tabular-nums">
                                                {money(
                                                    statement.opening_balance,
                                                )}
                                            </td>
                                        </tr>
                                    ) : null}
                                    {statement.entries.data.map(
                                        (line, index) => (
                                            <tr
                                                key={`${line.journal_entry_id}-${index}`}
                                                className="border-t hover:bg-muted/30"
                                            >
                                                <td className="p-3 whitespace-nowrap tabular-nums">
                                                    {line.entry_date?.slice(
                                                        0,
                                                        10,
                                                    )}
                                                </td>
                                                <td className="p-3 whitespace-nowrap">
                                                    <Link
                                                        href={`/accounting/journal-entries/${line.journal_entry_id}`}
                                                        className="font-medium underline-offset-4 hover:underline"
                                                    >
                                                        #{line.journal_entry_id}
                                                    </Link>
                                                    {line.reference ? (
                                                        <div className="text-xs text-muted-foreground">
                                                            {line.reference}
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="p-3 text-muted-foreground">
                                                    {line.line_description ||
                                                        line.journal_description ||
                                                        '—'}
                                                </td>
                                                <td className="p-3 text-right tabular-nums">
                                                    {Number(line.base_debit) > 0
                                                        ? money(line.base_debit)
                                                        : '—'}
                                                </td>
                                                <td className="p-3 text-right tabular-nums">
                                                    {Number(line.base_credit) >
                                                    0
                                                        ? money(
                                                              line.base_credit,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td
                                                    className={`p-3 text-right font-medium tabular-nums ${Number(line.running_balance) < 0 ? 'text-red-600' : ''}`}
                                                >
                                                    {money(
                                                        line.running_balance,
                                                    )}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                    {!statement.entries.data.length ? (
                                        <tr>
                                            <td
                                                className="p-6 text-center text-muted-foreground"
                                                colSpan={6}
                                            >
                                                No posted lines in this date
                                                range.
                                            </td>
                                        </tr>
                                    ) : null}
                                </tbody>
                            </table>
                        </div>
                        <Pagination page={statement.entries} />
                    </>
                )}
            </div>
        </>
    );
}

AccountStatement.layout = { breadcrumbs };
