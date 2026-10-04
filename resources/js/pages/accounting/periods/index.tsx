import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarPlus, Lock, LockOpen, Plus } from 'lucide-react';
import { useState } from 'react';
import { money } from '@/components/accounting/ledger';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Period = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    status: 'open' | 'closed' | 'archived';
    closed_at: string | null;
    closing_net_income: string | null;
    closing_journal_entry_id: number | null;
    is_fiscal_year_end: boolean;
};

const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

function StatusBadge({ status }: { status: Period['status'] }) {
    const styles: Record<Period['status'], string> = {
        open: 'bg-green-500/10 text-green-700 dark:text-green-400',
        closed: 'bg-muted text-muted-foreground',
        archived: 'bg-muted text-muted-foreground',
    };

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium capitalize ${styles[status]}`}
        >
            {status === 'open' ? (
                <LockOpen className="size-3" />
            ) : (
                <Lock className="size-3" />
            )}
            {status}
        </span>
    );
}

export default function Periods({
    periods,
    fiscalYearStartMonth,
}: {
    periods: Period[];
    fiscalYearStartMonth: number;
}) {
    const { permissions, flash } = useAccounting();
    const [generating, setGenerating] = useState(false);
    const nextYear = new Date().getFullYear() + 1;
    const form = useForm({
        start_date: `${nextYear}-${String(fiscalYearStartMonth).padStart(2, '0')}-01`,
    });

    return (
        <>
            <Head title="Accounting Periods" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title="Accounting Periods"
                        description={`Month-end and year-end close. The fiscal year starts in ${months[fiscalYearStartMonth - 1]}.`}
                    />
                    <div className="flex flex-wrap gap-2">
                        {permissions['periods.create'] ? (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => setGenerating(!generating)}
                                >
                                    <CalendarPlus className="size-4" /> Monthly
                                    periods
                                </Button>
                                <Button asChild>
                                    <Link href="/accounting/periods/create">
                                        <Plus className="size-4" /> New period
                                    </Link>
                                </Button>
                            </>
                        ) : null}
                    </div>
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

                {generating ? (
                    <form
                        className="flex flex-wrap items-end gap-3 rounded-lg border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/accounting/periods/generate-monthly', {
                                preserveScroll: true,
                                onSuccess: () => setGenerating(false),
                            });
                        }}
                    >
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="start_date">
                                Fiscal year starts on
                            </Label>
                            <Input
                                id="start_date"
                                type="date"
                                value={form.data.start_date}
                                onChange={(event) =>
                                    form.setData(
                                        'start_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.start_date} />
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            Create 12 monthly periods
                        </Button>
                        <p className="text-sm text-muted-foreground">
                            Close each month separately; run the year-end close
                            on the last month.
                        </p>
                    </form>
                ) : null}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Period</th>
                                <th className="p-3 font-medium">Dates</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 text-right font-medium">
                                    Net income
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {periods.length ? (
                                periods.map((period) => (
                                    <tr
                                        key={period.id}
                                        className="border-t hover:bg-muted/30"
                                    >
                                        <td className="p-3 font-medium">
                                            {period.name}
                                            {period.is_fiscal_year_end ? (
                                                <span className="ml-2 rounded bg-indigo-500/10 px-1.5 py-0.5 text-xs text-indigo-700 dark:text-indigo-300">
                                                    year end
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="p-3 whitespace-nowrap tabular-nums">
                                            {period.start_date} –{' '}
                                            {period.end_date}
                                        </td>
                                        <td className="p-3">
                                            <StatusBadge
                                                status={period.status}
                                            />
                                            {period.closing_journal_entry_id ? (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    year closed
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {period.closing_net_income !== null
                                                ? money(
                                                      period.closing_net_income,
                                                  )
                                                : '—'}
                                        </td>
                                        <td className="p-3 text-right whitespace-nowrap">
                                            {period.status === 'open' &&
                                            permissions['periods.close'] ? (
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={`/accounting/periods/${period.id}/close`}
                                                        >
                                                            Month-end close
                                                        </Link>
                                                    </Button>
                                                    {period.is_fiscal_year_end ? (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={`/accounting/periods/${period.id}/close?year_end=1`}
                                                            >
                                                                Year-end close
                                                            </Link>
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            ) : (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <Link
                                                        href={`/accounting/periods/${period.id}/close`}
                                                    >
                                                        {period.status ===
                                                        'open'
                                                            ? 'Checklist'
                                                            : 'View'}
                                                    </Link>
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        className="p-6 text-center text-muted-foreground"
                                        colSpan={5}
                                    >
                                        No periods yet. Create monthly periods
                                        for your fiscal year.
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

Periods.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Periods', href: '/accounting/periods' },
    ],
};
