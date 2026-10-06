import { Head, Link, router } from '@inertiajs/react';
import { Filter, X } from 'lucide-react';
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
import { useAccountingI18n } from '@/lib/i18n';

type Filters = {
    date_from?: string;
    date_to?: string;
    account_id?: string | number;
    status?: string;
};
type Account = { id: number; account_code: string; account_name: string };

type Props = {
    entries: Paginated<LedgerLine>;
    totals: {
        total_debit: number;
        total_credit: number;
        closing_balance: number;
    };
    filters: Filters;
    accounts: Account[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting' },
    { title: 'General Ledger', href: '/accounting/reports/general-ledger' },
];

const statuses = [
    { value: 'posted', label: 'Posted' },
    { value: 'draft', label: 'Draft' },
    { value: 'void', label: 'Void' },
    { value: 'all', label: 'All statuses' },
];

export default function GeneralLedger({
    entries,
    totals,
    filters,
    accounts,
}: Props) {
    useAccountingI18n();
    const [form, setForm] = useState({
        account_id: filters.account_id ? String(filters.account_id) : '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        status: filters.status ?? 'posted',
    });

    const apply = () =>
        router.get('/accounting/reports/general-ledger', form, {
            preserveState: true,
            preserveScroll: true,
        });
    const reset = () => router.get('/accounting/reports/general-ledger');
    const accountOptions = accounts.map((account) => ({
        value: String(account.id),
        label: `${account.account_code} — ${account.account_name}`,
    }));

    return (
        <>
            <Head title="General Ledger" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            General Ledger
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Every journal line, in date order. Totals are in the
                            base currency.
                        </p>
                    </div>
                    <ExportButtons
                        base="/accounting/reports/general-ledger/export"
                        filters={filters}
                    />
                </div>

                <div className="grid items-end gap-3 rounded-lg border p-4 md:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                    <div className="flex flex-col gap-1">
                        <Label>Account</Label>
                        <SearchableSelect
                            value={form.account_id}
                            options={[
                                { value: '', label: 'All accounts' },
                                ...accountOptions,
                            ]}
                            onChange={(value) =>
                                setForm({ ...form, account_id: value })
                            }
                            placeholder="All accounts"
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
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            className="h-9 rounded-md border bg-transparent px-3 text-sm"
                            value={form.status}
                            onChange={(e) =>
                                setForm({ ...form, status: e.target.value })
                            }
                        >
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={apply} variant="secondary">
                            <Filter className="size-4" /> Apply
                        </Button>
                        <Button
                            onClick={reset}
                            variant="ghost"
                            size="icon"
                            aria-label="Reset filters"
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                </div>

                <SummaryCards
                    items={[
                        { label: 'Total debit', value: totals.total_debit },
                        { label: 'Total credit', value: totals.total_credit },
                        {
                            label: 'Net (debit − credit)',
                            value: totals.closing_balance,
                            tone:
                                totals.closing_balance < 0
                                    ? 'negative'
                                    : 'positive',
                        },
                    ]}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[960px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Date</th>
                                <th className="p-3 font-medium">Entry</th>
                                <th className="p-3 font-medium">Account</th>
                                <th className="p-3 font-medium">Description</th>
                                <th className="p-3 text-right font-medium">
                                    Debit
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Credit
                                </th>
                                <th className="p-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.data.length ? (
                                entries.data.map((line, index) => (
                                    <tr
                                        key={`${line.journal_entry_id}-${index}`}
                                        className="border-t hover:bg-muted/30"
                                    >
                                        <td className="p-3 whitespace-nowrap tabular-nums">
                                            {line.entry_date?.slice(0, 10)}
                                        </td>
                                        <td className="p-3 whitespace-nowrap">
                                            <Link
                                                href={`/accounting/journal-entries/${line.journal_entry_id}`}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {line.voucher_number ??
                                                    `#${line.journal_entry_id}`}
                                            </Link>
                                            {line.reference ||
                                            line.source_document_number ? (
                                                <div className="text-xs text-muted-foreground">
                                                    {[
                                                        line.reference,
                                                        line.source_document_number,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="p-3">
                                            <span className="font-mono text-xs">
                                                {line.account_code}
                                            </span>{' '}
                                            {line.account_name}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {line.line_description ||
                                                line.journal_description ||
                                                '—'}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {Number(line.debit) > 0
                                                ? money(line.debit)
                                                : '—'}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {Number(line.credit) > 0
                                                ? money(line.credit)
                                                : '—'}
                                        </td>
                                        <td className="p-3 capitalize">
                                            {line.status}
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        className="p-6 text-center text-muted-foreground"
                                        colSpan={7}
                                    >
                                        No journal lines match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination page={entries} />
            </div>
        </>
    );
}

GeneralLedger.layout = { breadcrumbs };
