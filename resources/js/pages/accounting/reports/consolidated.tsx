import { Head, router } from '@inertiajs/react';
import { Filter } from 'lucide-react';
import { useState } from 'react';
import { money } from '@/components/accounting/ledger';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Row = {
    account_code: string;
    account_name: string;
    account_type: string | null;
    companies: Record<string, string>;
    balance: string;
};

type Result = {
    report: string;
    companies: Array<{ id: number; code: string; name: string }>;
    data: Row[];
    totals: {
        companies: Record<string, Record<string, string>>;
        group: Record<string, string>;
    };
};

type Filters = {
    report: string;
    companies?: string;
    as_of_date?: string;
    date_from?: string;
    date_to?: string;
    include_zero?: boolean;
};

const reports = [
    { value: 'trial-balance', label: 'Trial balance' },
    { value: 'balance-sheet', label: 'Balance sheet' },
    { value: 'income-statement', label: 'Income statement' },
];

const label = (key: string) =>
    key.replace(/_/g, ' ').replace(/^\w/, (letter) => letter.toUpperCase());

export default function ConsolidatedReport({
    result,
    filters,
}: {
    result: Result;
    filters: Filters;
}) {
    const { company } = useAccounting();
    const [form, setForm] = useState({
        report: filters.report,
        as_of_date: filters.as_of_date ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        include_zero: Boolean(filters.include_zero),
    });
    const [selected, setSelected] = useState<string[]>(
        result.companies.map((item) => item.code),
    );

    const apply = () =>
        router.get(
            '/accounting/reports/consolidated',
            {
                ...form,
                include_zero: form.include_zero ? 1 : 0,
                companies: selected.join(','),
            },
            { preserveState: true, preserveScroll: true },
        );
    const toggle = (code: string) =>
        setSelected(
            selected.includes(code)
                ? selected.filter((item) => item !== code)
                : [...selected, code],
        );

    return (
        <>
            <Head title="Consolidated Reports" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Consolidated Reports
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Each company's report side by side and the group total,
                        in the base currency. Intercompany balances are not
                        eliminated.
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-lg border p-4">
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="report">Report</Label>
                        <select
                            id="report"
                            className="h-9 rounded-md border bg-transparent px-3 text-sm"
                            value={form.report}
                            onChange={(event) =>
                                setForm({ ...form, report: event.target.value })
                            }
                        >
                            {reports.map((report) => (
                                <option key={report.value} value={report.value}>
                                    {report.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    {form.report === 'balance-sheet' ? (
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="as_of_date">As of</Label>
                            <Input
                                id="as_of_date"
                                type="date"
                                value={form.as_of_date}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        as_of_date: event.target.value,
                                    })
                                }
                            />
                        </div>
                    ) : null}
                    {form.report === 'income-statement' ? (
                        <>
                            <div className="flex flex-col gap-1">
                                <Label htmlFor="date_from">From</Label>
                                <Input
                                    id="date_from"
                                    type="date"
                                    value={form.date_from}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            date_from: event.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="flex flex-col gap-1">
                                <Label htmlFor="date_to">To</Label>
                                <Input
                                    id="date_to"
                                    type="date"
                                    value={form.date_to}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            date_to: event.target.value,
                                        })
                                    }
                                />
                            </div>
                        </>
                    ) : null}
                    <fieldset className="flex flex-wrap items-center gap-3">
                        <legend className="mb-1 text-sm font-medium">
                            Companies
                        </legend>
                        {company.list.map((item) => (
                            <label
                                key={item.code}
                                className="flex items-center gap-1.5 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    checked={selected.includes(item.code)}
                                    onChange={() => toggle(item.code)}
                                />
                                {item.name}
                            </label>
                        ))}
                    </fieldset>
                    <label className="flex items-center gap-1.5 text-sm">
                        <input
                            type="checkbox"
                            checked={form.include_zero}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    include_zero: event.target.checked,
                                })
                            }
                        />
                        Show zero balances
                    </label>
                    <Button
                        onClick={apply}
                        variant="secondary"
                        disabled={!selected.length}
                    >
                        <Filter className="size-4" /> Show
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {Object.entries(result.totals.group).map(([key, value]) => (
                        <div key={key} className="rounded-lg border p-4">
                            <div className="text-sm text-muted-foreground">
                                Group {label(key).toLowerCase()}
                            </div>
                            <div className="text-xl font-semibold tabular-nums">
                                {money(value)}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Account</th>
                                {result.companies.map((item) => (
                                    <th
                                        key={item.code}
                                        className="p-3 text-right font-medium"
                                    >
                                        {item.code}
                                    </th>
                                ))}
                                <th className="p-3 text-right font-medium">
                                    Group
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {result.data.length ? (
                                result.data.map((row) => (
                                    <tr
                                        key={`${row.account_code}-${row.account_name}`}
                                        className="border-t hover:bg-muted/30"
                                    >
                                        <td className="p-3">
                                            <span className="font-mono text-xs">
                                                {row.account_code}
                                            </span>{' '}
                                            {row.account_name}
                                        </td>
                                        {result.companies.map((item) => (
                                            <td
                                                key={item.code}
                                                className="p-3 text-right tabular-nums"
                                            >
                                                {money(
                                                    row.companies[item.code],
                                                )}
                                            </td>
                                        ))}
                                        <td className="p-3 text-right font-medium tabular-nums">
                                            {money(row.balance)}
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        className="p-6 text-center text-muted-foreground"
                                        colSpan={result.companies.length + 2}
                                    >
                                        No balances for the selected companies.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

ConsolidatedReport.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        {
            title: 'Consolidated Reports',
            href: '/accounting/reports/consolidated',
        },
    ],
};
