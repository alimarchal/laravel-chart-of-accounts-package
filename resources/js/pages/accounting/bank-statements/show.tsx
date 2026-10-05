import { Head, Link, router } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccounting } from '@/lib/accounting';

type Line = {
    id: number;
    line_no: number;
    txn_date: string;
    description: string | null;
    reference: string | null;
    deposit: string;
    withdrawal: string;
    status: 'unmatched' | 'matched' | 'created' | 'ignored';
    journal_entry_id: number | null;
    voucher_number: string | null;
    journal_status: string | null;
};
type Candidate = {
    id: number;
    date: string;
    voucher_number: string | null;
    reference: string | null;
    description: string | null;
    debit: string;
    credit: string;
};
type Props = {
    statement: {
        id: number;
        bank: string | null;
        file_name: string;
        from_date: string | null;
        to_date: string | null;
        closing_balance: string | null;
        lines_count: number;
        unmatched_count: number;
        reconciliation_id: number | null;
    };
    lines: Line[];
    accounts: Array<{ id: number; account_code: string; account_name: string }>;
    matchDays: number;
};

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const style: Record<Line['status'], string> = {
    unmatched: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    matched: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    created: 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
    ignored: 'bg-muted text-muted-foreground',
};
const money = (value: string) =>
    Number(value) === 0
        ? ''
        : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function BankStatementShow({ statement, lines, accounts }: Props) {
    const { permissions, flash } = useAccounting();
    const canMatch = permissions['bank-statements.match'] && !statement.reconciliation_id;
    const [open, setOpen] = useState<number | null>(null);
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [account, setAccount] = useState('');
    const [closing, setClosing] = useState(statement.closing_balance ?? '');
    const post = (url: string, data: Record<string, unknown> = {}) =>
        router.post(url, data as never, { preserveScroll: true, onSuccess: () => setOpen(null) });

    const load = async (line: Line) => {
        setOpen(line.id);
        setAccount('');
        setCandidates([]);
        const response = await fetch(`/accounting/bank-statement-lines/${line.id}/candidates`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        setCandidates(((await response.json()) as { data: Candidate[] }).data);
    };

    return (
        <>
            <Head title={statement.file_name} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title={statement.file_name}
                        description={`${statement.bank ?? ''} · ${statement.from_date} → ${statement.to_date} · ${statement.lines_count} transactions, ${statement.unmatched_count} to match`}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        {canMatch ? (
                            <>
                                <Button variant="outline" onClick={() => post(`/accounting/bank-statements/${statement.id}/auto-match`)}>
                                    Auto-match
                                </Button>
                                <Button
                                    onClick={() => post(`/accounting/bank-statements/${statement.id}/reconcile`)}
                                    disabled={statement.unmatched_count > 0}
                                >
                                    Reconcile
                                </Button>
                                <Button
                                    variant="ghost"
                                    onClick={() => {
                                        if (confirm('Delete this statement? Its matches are released.')) {
                                            router.delete(`/accounting/bank-statements/${statement.id}`);
                                        }
                                    }}
                                >
                                    Delete
                                </Button>
                            </>
                        ) : null}
                        <Button asChild variant="ghost">
                            <Link href="/accounting/bank-statements">Back</Link>
                        </Button>
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

                <div className="flex items-end gap-2 text-sm">
                    <span className="text-muted-foreground">Closing balance</span>
                    {canMatch ? (
                        <>
                            <Input
                                aria-label="Closing balance"
                                className="w-40"
                                type="number"
                                step="0.01"
                                value={closing}
                                onChange={(event) => setClosing(event.target.value)}
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.patch(`/accounting/bank-statements/${statement.id}`, { closing_balance: closing }, { preserveScroll: true })
                                }
                            >
                                Save
                            </Button>
                        </>
                    ) : (
                        <strong>{statement.closing_balance ?? '—'}</strong>
                    )}
                    {statement.reconciliation_id ? <Badge className="bg-emerald-100 text-emerald-800">reconciled</Badge> : null}
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Date</th>
                                <th className="px-3 py-2 font-medium">Description</th>
                                <th className="px-3 py-2 font-medium">Reference</th>
                                <th className="px-3 py-2 text-right font-medium">Withdrawal</th>
                                <th className="px-3 py-2 text-right font-medium">Deposit</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lines.map((line) => (
                                <Fragment key={line.id}>
                                    <tr className="border-t">
                                        <td className="px-3 py-2">{line.txn_date}</td>
                                        <td className="px-3 py-2">{line.description}</td>
                                        <td className="px-3 py-2">{line.reference}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{money(line.withdrawal)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{money(line.deposit)}</td>
                                        <td className="px-3 py-2">
                                            <Badge className={style[line.status]}>{line.status}</Badge>
                                            {line.journal_entry_id ? (
                                                <Link
                                                    href={`/accounting/journal-entries/${line.journal_entry_id}`}
                                                    className="ml-2 text-xs hover:underline"
                                                >
                                                    {line.voucher_number ?? `entry #${line.journal_entry_id}`}
                                                    {line.journal_status === 'draft' ? ' (draft)' : ''}
                                                </Link>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {canMatch ? (
                                                <div className="flex justify-end gap-1">
                                                    {line.status === 'unmatched' ? (
                                                        <>
                                                            <Button size="sm" variant="outline" onClick={() => (open === line.id ? setOpen(null) : load(line))}>
                                                                Match / book
                                                            </Button>
                                                            <Button size="sm" variant="ghost" onClick={() => post(`/accounting/bank-statement-lines/${line.id}/ignore`)}>
                                                                Ignore
                                                            </Button>
                                                        </>
                                                    ) : null}
                                                    {line.status === 'ignored' ? (
                                                        <Button size="sm" variant="ghost" onClick={() => post(`/accounting/bank-statement-lines/${line.id}/ignore`, { ignored: false })}>
                                                            Restore
                                                        </Button>
                                                    ) : null}
                                                    {line.status === 'matched' || line.status === 'created' ? (
                                                        <Button size="sm" variant="ghost" onClick={() => post(`/accounting/bank-statement-lines/${line.id}/unmatch`)}>
                                                            Unmatch
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            ) : null}
                                        </td>
                                    </tr>
                                    {open === line.id ? (
                                        <tr className="border-t bg-muted/30">
                                            <td colSpan={7} className="px-3 py-3">
                                                <div className="grid gap-3 md:grid-cols-2">
                                                    <div>
                                                        <div className="mb-1 text-xs font-medium uppercase text-muted-foreground">
                                                            Ledger lines with this amount
                                                        </div>
                                                        {candidates.length === 0 ? (
                                                            <div className="text-sm text-muted-foreground">None found nearby.</div>
                                                        ) : (
                                                            candidates.map((candidate) => (
                                                                <div key={candidate.id} className="flex items-center justify-between gap-2 py-1 text-sm">
                                                                    <span>
                                                                        {candidate.date} · {candidate.voucher_number ?? '—'} · {candidate.description ?? candidate.reference}
                                                                    </span>
                                                                    <Button
                                                                        size="sm"
                                                                        onClick={() => post(`/accounting/bank-statement-lines/${line.id}/match`, { journal_entry_line_id: candidate.id })}
                                                                    >
                                                                        Match
                                                                    </Button>
                                                                </div>
                                                            ))
                                                        )}
                                                    </div>
                                                    <div>
                                                        <div className="mb-1 text-xs font-medium uppercase text-muted-foreground">
                                                            Or book it to an account
                                                        </div>
                                                        <div className="flex gap-2">
                                                            <select className={`${selectClass} flex-1`} value={account} onChange={(event) => setAccount(event.target.value)}>
                                                                <option value="">Select an account</option>
                                                                {accounts.map((item) => (
                                                                    <option key={item.id} value={item.id}>
                                                                        {item.account_code} - {item.account_name}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                            <Button
                                                                disabled={account === ''}
                                                                onClick={() => post(`/accounting/bank-statement-lines/${line.id}/create-entry`, { chart_of_account_id: account })}
                                                            >
                                                                Book
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : null}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

BankStatementShow.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Bank Statements', href: '/accounting/bank-statements' },
    ],
};
