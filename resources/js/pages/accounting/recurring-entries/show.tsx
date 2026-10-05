import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2, Zap } from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Line = {
    account: string | null;
    cost_center: string | null;
    debit: string;
    credit: string;
    description: string | null;
};
type Entry = {
    id: number;
    name: string;
    status: 'active' | 'paused' | 'finished';
    frequency: string;
    interval: number;
    day_of_month: number | null;
    start_date: string;
    end_date: string | null;
    next_run_date: string | null;
    max_runs: number | null;
    runs_count: number;
    mode: 'draft' | 'post';
    reference: string | null;
    description: string | null;
    lines: Line[];
};
type Run = {
    id: number;
    run_date: string;
    status: string;
    error: string | null;
    journal_entry_id: number | null;
    voucher_number: string | null;
    journal_status: string | null;
};
type Props = { entry: Entry; runs: Run[]; upcoming: string[] };

const runStyle: Record<string, string> = {
    posted: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    submitted:
        'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    failed: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
};

const money = (value: string) =>
    Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function ShowRecurringEntry({ entry, runs, upcoming }: Props) {
    const { permissions, flash } = useAccounting();

    return (
        <>
            <Head title={entry.name} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title={entry.name}
                        description={`${entry.interval > 1 ? `Every ${entry.interval} ` : 'Every '}${entry.frequency.replace('ly', entry.interval > 1 ? 's' : '')}${entry.day_of_month ? `, on day ${entry.day_of_month}` : ''} · ${entry.mode === 'post' ? 'posts automatically' : 'creates a draft'}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        {permissions['recurring-entries.run'] &&
                        entry.status !== 'finished' ? (
                            <Button
                                variant="outline"
                                className="gap-2"
                                onClick={() =>
                                    router.post(
                                        `/accounting/recurring-entries/${entry.id}/run`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Zap className="size-4" />
                                Generate now
                            </Button>
                        ) : null}
                        {permissions['recurring-entries.update'] ? (
                            <Button asChild variant="outline" className="gap-2">
                                <Link
                                    href={`/accounting/recurring-entries/${entry.id}/edit`}
                                >
                                    <Pencil className="size-4" />
                                    Edit
                                </Link>
                            </Button>
                        ) : null}
                        {permissions['recurring-entries.delete'] &&
                        runs.length === 0 ? (
                            <Button
                                variant="outline"
                                className="gap-2"
                                onClick={() =>
                                    window.confirm(
                                        'Delete this recurring entry?',
                                    ) &&
                                    router.delete(
                                        `/accounting/recurring-entries/${entry.id}`,
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                                Delete
                            </Button>
                        ) : null}
                    </div>
                </div>

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="text-base">
                                The entry
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="py-1 font-medium">
                                            Account
                                        </th>
                                        <th className="py-1 font-medium">
                                            Cost center
                                        </th>
                                        <th className="py-1 text-right font-medium">
                                            Debit
                                        </th>
                                        <th className="py-1 text-right font-medium">
                                            Credit
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {entry.lines.map((line, index) => (
                                        <tr key={index} className="border-t">
                                            <td className="py-1.5">
                                                {line.account}
                                            </td>
                                            <td className="py-1.5">
                                                {line.cost_center ?? '—'}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {Number(line.debit) > 0
                                                    ? money(line.debit)
                                                    : ''}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {Number(line.credit) > 0
                                                    ? money(line.credit)
                                                    : ''}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {entry.description ? (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    {entry.description}
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card className="h-fit rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between text-base">
                                Schedule{' '}
                                <Badge variant="outline">{entry.status}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div>
                                Starts {entry.start_date}
                                {entry.end_date
                                    ? `, ends ${entry.end_date}`
                                    : ''}
                            </div>
                            <div>
                                Generated {entry.runs_count}
                                {entry.max_runs ? ` of ${entry.max_runs}` : ''}
                            </div>
                            <div className="pt-1 font-medium">Upcoming</div>
                            <ul className="text-muted-foreground">
                                {upcoming.length === 0 ? (
                                    <li>Nothing left to generate.</li>
                                ) : (
                                    upcoming.map((date) => (
                                        <li key={date}>{date}</li>
                                    ))
                                )}
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <Card className="rounded-lg">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Generated entries
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {runs.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing generated yet.
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="py-1 font-medium">
                                            Date
                                        </th>
                                        <th className="py-1 font-medium">
                                            Result
                                        </th>
                                        <th className="py-1 font-medium">
                                            Entry
                                        </th>
                                        <th className="py-1 font-medium">
                                            Note
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {runs.map((run) => (
                                        <tr
                                            key={run.id}
                                            className="border-t align-top"
                                        >
                                            <td className="py-1.5">
                                                {run.run_date}
                                            </td>
                                            <td className="py-1.5">
                                                <Badge
                                                    className={
                                                        runStyle[run.status] ??
                                                        ''
                                                    }
                                                >
                                                    {run.status}
                                                </Badge>
                                            </td>
                                            <td className="py-1.5">
                                                {run.journal_entry_id ? (
                                                    <Link
                                                        href={`/accounting/journal-entries/${run.journal_entry_id}`}
                                                        className="hover:underline"
                                                    >
                                                        {run.voucher_number ??
                                                            `Draft #${run.journal_entry_id}`}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="py-1.5 text-muted-foreground">
                                                {run.error}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ShowRecurringEntry.layout = {
    breadcrumbs: [
        { title: 'Recurring Entries', href: '/accounting/recurring-entries' },
        { title: 'Details', href: '#' },
    ],
};
