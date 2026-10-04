import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Info,
    Lock,
    LockOpen,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { money } from '@/components/accounting/ledger';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Check = {
    key: string;
    label: string;
    status: 'pass' | 'fail' | 'warn' | 'info';
    detail: string | null;
    count: number | null;
    link: string | null;
};

type ClosingLine = {
    chart_of_account_id: number;
    account_code: string;
    account_name: string;
    debit: string;
    credit: string;
};

type Checklist = {
    period: {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
        status: string;
        closed_at: string | null;
        closing_net_income: string | null;
        closing_journal_entry_id: number | null;
    };
    year_end: boolean;
    can_close: boolean;
    checks: Check[];
    summary: {
        posted_entries: number;
        net_income: string;
        total_debits: string;
        total_credits: string;
    };
    closing_entry: {
        lines: ClosingLine[];
        net_income: string;
        retained_earnings: {
            account_code: string;
            account_name: string;
        } | null;
    } | null;
};

const icons = {
    pass: <CheckCircle2 className="size-5 text-green-600" />,
    fail: <XCircle className="size-5 text-red-600" />,
    warn: <AlertTriangle className="size-5 text-amber-500" />,
    info: <Info className="size-5 text-muted-foreground" />,
};

const date = (value: string) => value.slice(0, 10);

export default function PeriodClose({
    checklist,
    isFiscalYearEnd,
}: {
    checklist: Checklist;
    isFiscalYearEnd: boolean;
}) {
    const { permissions, flash } = useAccounting();
    const { period, checks, summary } = checklist;
    const isOpen = period.status === 'open';
    const [reopening, setReopening] = useState(false);
    const reopen = useForm({ reason: '' });
    const failures = checks.filter((check) => check.status === 'fail').length;
    const warnings = checks.filter((check) => check.status === 'warn').length;

    const close = () => {
        const message = checklist.year_end
            ? `Post the year-end closing entry and close ${period.name}? Profit or loss moves to retained earnings.`
            : `Close ${period.name}? No entries can be posted into it afterwards.`;

        if (
            window.confirm(
                warnings
                    ? `${message}\n\n${warnings} warning(s) are still open.`
                    : message,
            )
        ) {
            router.post(
                `/accounting/periods/${period.id}/${checklist.year_end ? 'close-fiscal-year' : 'close'}`,
            );
        }
    };

    return (
        <>
            <Head
                title={`${checklist.year_end ? 'Year-end' : 'Month-end'} close · ${period.name}`}
            />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title={`${checklist.year_end ? 'Year-end close' : 'Month-end close'}: ${period.name}`}
                        description={`${date(period.start_date)} – ${date(period.end_date)}`}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs font-medium capitalize">
                            {isOpen ? (
                                <LockOpen className="size-3" />
                            ) : (
                                <Lock className="size-3" />
                            )}
                            {period.status}
                        </span>
                        {isOpen && isFiscalYearEnd ? (
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={`/accounting/periods/${period.id}/close${checklist.year_end ? '' : '?year_end=1'}`}
                                >
                                    {checklist.year_end
                                        ? 'Month-end close only'
                                        : 'Year-end close'}
                                </Link>
                            </Button>
                        ) : null}
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/accounting/periods">All periods</Link>
                        </Button>
                    </div>
                </div>

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Could not complete</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        ['Posted entries', String(summary.posted_entries)],
                        ['Net income (period)', money(summary.net_income)],
                        ['Total debits to date', money(summary.total_debits)],
                        ['Total credits to date', money(summary.total_credits)],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-lg border p-4">
                            <div className="text-sm text-muted-foreground">
                                {label}
                            </div>
                            <div className="text-xl font-semibold tabular-nums">
                                {value}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="rounded-lg border">
                    <div className="flex items-center justify-between border-b p-4">
                        <div className="font-medium">Close checklist</div>
                        <div className="text-sm text-muted-foreground">
                            {failures ? `${failures} to fix` : 'Ready'}
                            {warnings ? ` · ${warnings} to review` : ''}
                        </div>
                    </div>
                    <ul className="divide-y">
                        {checks.map((check) => (
                            <li
                                key={check.key}
                                className="flex items-start gap-3 p-4"
                            >
                                {icons[check.status]}
                                <div className="flex-1">
                                    <div className="font-medium">
                                        {check.label}
                                    </div>
                                    {check.detail ? (
                                        <div className="text-sm text-muted-foreground">
                                            {check.detail}
                                        </div>
                                    ) : null}
                                </div>
                                {check.link && check.status !== 'pass' ? (
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={`/accounting/${check.link}`}
                                        >
                                            Open
                                        </Link>
                                    </Button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </div>

                {checklist.year_end && checklist.closing_entry ? (
                    <div className="rounded-lg border">
                        <div className="border-b p-4">
                            <div className="font-medium">
                                Closing entry preview
                            </div>
                            <div className="text-sm text-muted-foreground">
                                Zeroes every income and expense account for the
                                year and moves the result (
                                {money(checklist.closing_entry.net_income)}) to{' '}
                                {checklist.closing_entry.retained_earnings
                                    ? `${checklist.closing_entry.retained_earnings.account_code} ${checklist.closing_entry.retained_earnings.account_name}`
                                    : 'retained earnings'}
                                .
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[560px] text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">
                                            Account
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Debit
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Credit
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {checklist.closing_entry.lines.length ? (
                                        checklist.closing_entry.lines.map(
                                            (line) => (
                                                <tr
                                                    key={
                                                        line.chart_of_account_id
                                                    }
                                                    className="border-t"
                                                >
                                                    <td className="p-3">
                                                        <span className="font-mono text-xs">
                                                            {line.account_code}
                                                        </span>{' '}
                                                        {line.account_name}
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
                                                </tr>
                                            ),
                                        )
                                    ) : (
                                        <tr>
                                            <td
                                                className="p-6 text-center text-muted-foreground"
                                                colSpan={3}
                                            >
                                                No income or expense balances:
                                                no closing entry is needed.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                <div className="flex flex-wrap items-center gap-3">
                    {isOpen && permissions['periods.close'] ? (
                        <Button onClick={close} disabled={!checklist.can_close}>
                            <Lock className="size-4" />{' '}
                            {checklist.year_end
                                ? 'Post closing entry & close year'
                                : `Close ${period.name}`}
                        </Button>
                    ) : null}
                    {isOpen && !checklist.can_close ? (
                        <span className="text-sm text-muted-foreground">
                            Fix the items marked ✕ to close.
                        </span>
                    ) : null}
                    {!isOpen && permissions['periods.reopen'] ? (
                        <Button
                            variant="outline"
                            onClick={() => setReopening(!reopening)}
                        >
                            <LockOpen className="size-4" /> Reopen…
                        </Button>
                    ) : null}
                </div>

                {reopening ? (
                    <form
                        className="space-y-3 rounded-lg border border-amber-500/40 bg-amber-500/5 p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            reopen.post(
                                `/accounting/periods/${period.id}/reopen`,
                                { onSuccess: () => setReopening(false) },
                            );
                        }}
                    >
                        <div className="text-sm">
                            Reopening allows postings into {period.name} again.
                            {period.closing_journal_entry_id
                                ? ' The year-end closing entry will be reversed; close the year again afterwards.'
                                : ''}{' '}
                            The reason is kept in the audit trail.
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="reason">Reason</Label>
                            <textarea
                                id="reason"
                                className="min-h-20 rounded-md border bg-transparent p-2 text-sm"
                                value={reopen.data.reason}
                                onChange={(event) =>
                                    reopen.setData('reason', event.target.value)
                                }
                            />
                            <InputError message={reopen.errors.reason} />
                        </div>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={reopen.processing}
                        >
                            Reopen {period.name}
                        </Button>
                    </form>
                ) : null}
            </div>
        </>
    );
}

PeriodClose.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Periods', href: '/accounting/periods' },
        { title: 'Close', href: '/accounting/periods' },
    ],
};
