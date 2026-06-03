import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Fragment } from 'react';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type StatementRow = {
    account_code: string;
    account_name: string;
    account_type: string;
    normal_balance: string;
    total_debits: number | string;
    total_credits: number | string;
    balance: number | string;
};

function money(value: number | string | null | undefined): string {
    return Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting' },
    { title: 'Income Statement', href: '/accounting/reports/income-statement' },
];

export default function IncomeStatement({ rows }: { rows: StatementRow[] }) {
    const grouped = rows.reduce<Record<string, StatementRow[]>>((carry, row) => {
        carry[row.account_type] = [...(carry[row.account_type] ?? []), row];

        return carry;
    }, {});
    const net = rows.reduce((sum, row) => sum + Number(row.balance ?? 0), 0);

    return (
        <>
            <Head title="Income Statement" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">Income Statement</h1>
                        <p className="text-sm text-muted-foreground">Income and expense activity for posted ledger entries.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {['csv', 'xlsx', 'pdf'].map((format) => (
                            <Button key={format} asChild variant="outline">
                                <Link href={`/accounting/reports/income-statement/export/${format}`}>
                                    <Download className="size-4" />
                                    {format.toUpperCase()}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="rounded-lg border p-4">
                    <div className="text-sm text-muted-foreground">Net movement</div>
                    <div className="mt-1 text-2xl font-semibold tabular-nums">{money(net)}</div>
                </div>

                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full min-w-[820px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Code</th>
                                <th className="p-3 font-medium">Account</th>
                                <th className="p-3 text-right font-medium">Debit</th>
                                <th className="p-3 text-right font-medium">Credit</th>
                                <th className="p-3 text-right font-medium">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(grouped).map(([group, groupRows]) => (
                                <Fragment key={group}>
                                    <tr key={`${group}-header`} className="border-t bg-muted/20">
                                        <td className="p-3 font-semibold" colSpan={5}>{group}</td>
                                    </tr>
                                    {groupRows.map((row) => (
                                        <tr key={row.account_code} className="border-t">
                                            <td className="p-3 font-medium">{row.account_code}</td>
                                            <td className="p-3">{row.account_name}</td>
                                            <td className="p-3 text-right tabular-nums">{money(row.total_debits)}</td>
                                            <td className="p-3 text-right tabular-nums">{money(row.total_credits)}</td>
                                            <td className="p-3 text-right tabular-nums">{money(row.balance)}</td>
                                        </tr>
                                    ))}
                                </Fragment>
                            ))}
                            {!rows.length ? <tr><td className="p-6 text-center text-muted-foreground" colSpan={5}>No income statement rows found.</td></tr> : null}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

IncomeStatement.layout = {
    breadcrumbs,
};
