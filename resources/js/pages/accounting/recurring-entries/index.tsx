import { Head, Link, router } from '@inertiajs/react';
import { Pause, Play, Plus, Repeat, Zap } from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAccounting } from '@/lib/accounting';

type Entry = {
    id: number;
    name: string;
    status: 'active' | 'paused' | 'finished';
    frequency: string;
    interval: number;
    next_run_date: string | null;
    runs_count: number;
    max_runs: number | null;
    mode: 'draft' | 'post';
    amount: string;
};

type Props = { entries: Entry[] };

const statusStyle: Record<Entry['status'], string> = {
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    paused: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    finished: 'bg-muted text-muted-foreground',
};

const units: Record<string, string> = {
    daily: 'days',
    weekly: 'weeks',
    monthly: 'months',
    quarterly: 'quarters',
    yearly: 'years',
};

const every = (entry: Entry) =>
    entry.interval > 1
        ? `every ${entry.interval} ${units[entry.frequency] ?? entry.frequency}`
        : entry.frequency;

export default function RecurringEntries({ entries }: Props) {
    const { permissions, flash } = useAccounting();
    const act = (entry: Entry, action: 'pause' | 'resume' | 'run') =>
        router.post(
            `/accounting/recurring-entries/${entry.id}/${action}`,
            {},
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Recurring Entries" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title="Recurring Entries"
                        description="Entries that repeat on a schedule — rent, subscriptions, depreciation, accruals — generated automatically."
                    />
                    {permissions['recurring-entries.create'] ? (
                        <Button asChild className="gap-2">
                            <Link href="/accounting/recurring-entries/create">
                                <Plus className="size-4" />
                                New recurring entry
                            </Link>
                        </Button>
                    ) : null}
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

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">
                                    Repeats
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Amount
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Next run
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Generated
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        <Repeat className="mx-auto mb-2 size-5" />
                                        No recurring entries yet.
                                    </td>
                                </tr>
                            ) : null}
                            {entries.map((entry) => (
                                <tr key={entry.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link
                                            href={`/accounting/recurring-entries/${entry.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {entry.name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {entry.mode === 'post'
                                                ? 'posts automatically'
                                                : 'creates a draft'}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 capitalize">
                                        {every(entry)}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {Number(entry.amount).toLocaleString(
                                            undefined,
                                            { minimumFractionDigits: 2 },
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {entry.next_run_date ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {entry.runs_count}
                                        {entry.max_runs
                                            ? ` / ${entry.max_runs}`
                                            : ''}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            className={
                                                statusStyle[entry.status]
                                            }
                                        >
                                            {entry.status}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex justify-end gap-1">
                                            {permissions[
                                                'recurring-entries.run'
                                            ] && entry.status !== 'finished' ? (
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    title="Generate the next entry now"
                                                    onClick={() =>
                                                        act(entry, 'run')
                                                    }
                                                >
                                                    <Zap className="size-4" />
                                                </Button>
                                            ) : null}
                                            {permissions[
                                                'recurring-entries.update'
                                            ] && entry.status === 'active' ? (
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    title="Pause"
                                                    onClick={() =>
                                                        act(entry, 'pause')
                                                    }
                                                >
                                                    <Pause className="size-4" />
                                                </Button>
                                            ) : null}
                                            {permissions[
                                                'recurring-entries.update'
                                            ] && entry.status === 'paused' ? (
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    title="Resume"
                                                    onClick={() =>
                                                        act(entry, 'resume')
                                                    }
                                                >
                                                    <Play className="size-4" />
                                                </Button>
                                            ) : null}
                                        </div>
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

RecurringEntries.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Recurring Entries', href: '/accounting/recurring-entries' },
    ],
};
