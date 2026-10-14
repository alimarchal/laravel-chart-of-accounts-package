import { Head, Link, router } from '@inertiajs/react';
import {
    Ban,
    CheckCircle2,
    Edit,
    FileDown,
    Printer,
    RotateCcw,
    Send,
    XCircle,
} from 'lucide-react';
import { useEffect } from 'react';
import { StatusPill } from '@/components/accounting/status-pill';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    AttachmentsPanel,
    type AttachmentItem,
} from '@/components/accounting/attachments-panel';
import { money } from '@/components/accounting/ledger';
import {
    useAccounting,
    playErrorSound,
    playSuccessSound,
} from '@/lib/accounting';
import { confirmThen } from '@/lib/confirm';

type TrailStep = { step: string; by: string | null; at: string };

const dateTime = (value: string | null | undefined): string =>
    value ? new Date(value).toLocaleString() : '—';

type JournalLine = {
    id: number;
    line_no: number;
    debit: string;
    credit: string;
    description: string | null;
    account?: {
        account_code: string;
        account_name: string;
    };
    cost_center?: {
        code: string;
        name: string;
    } | null;
};

type JournalEntry = {
    id: number;
    voucher_number: string | null;
    voucher_type?: { code: string; name: string } | null;
    branch?: { id: number; code: string; name: string } | null;
    entry_date: string;
    reference: string | null;
    source_document_type: string | null;
    source_document_number: string | null;
    source_document_date: string | null;
    sourceable_type: string | null;
    sourceable_id: number | null;
    description: string | null;
    status: 'draft' | 'posted' | 'void';
    approval_status: 'pending' | 'approved' | 'rejected' | null;
    rejection_reason: string | null;
    posted_at: string | null;
    lines: JournalLine[];
    currency?: {
        code: string;
    };
};

export default function JournalEntryShow({
    entry,
    requiresApproval = false,
    trail = [],
    documentTypes = {},
    attachments = [],
    attachmentRules = { max_size_kb: 10240, mimes: [] },
}: {
    entry: JournalEntry;
    requiresApproval?: boolean;
    trail?: TrailStep[];
    documentTypes?: Record<string, string>;
    attachments?: AttachmentItem[];
    attachmentRules?: { max_size_kb: number; mimes: string[] };
}) {
    const { permissions, flash } = useAccounting();
    const isDraft = entry.status === 'draft';
    const canEdit = permissions['journal-entries.update'] === true && isDraft;
    // Under maker-checker an entry that needs approval is submitted, never posted directly.
    const canPost =
        permissions['journal-entries.post'] === true &&
        isDraft &&
        !requiresApproval;
    const canSubmit =
        permissions['journal-entries.create'] === true &&
        isDraft &&
        requiresApproval &&
        entry.approval_status !== 'pending';
    const canReview =
        permissions['journal-entries.approve'] === true &&
        isDraft &&
        entry.approval_status === 'pending';
    const canReverse =
        permissions['journal-entries.reverse'] === true &&
        entry.status === 'posted';
    const canVoid =
        permissions['journal-entries.void'] === true &&
        entry.status === 'draft';

    const post = () =>
        router.post(`/accounting/journal-entries/${entry.id}/post`);
    const reverse = () =>
        router.post(`/accounting/journal-entries/${entry.id}/reverse`);
    const voidEntry = () =>
        router.post(`/accounting/journal-entries/${entry.id}/void`);
    const submit = () =>
        router.post(`/accounting/journal-entries/${entry.id}/submit`);
    const approve = () => {
        confirmThen('Approve and post this entry?', () => {
            router.post(`/accounting/journal-entries/${entry.id}/approve`);
        });
    };
    const reject = () => {
        const reason = window.prompt('Reason for rejection');

        if (reason) {
            router.post(`/accounting/journal-entries/${entry.id}/reject`, {
                reason,
            });
        }
    };

    useEffect(() => {
        if (flash.success) {
            playSuccessSound();
        }
    }, [flash.success]);

    useEffect(() => {
        if (flash.error) {
            playErrorSound();
        }
    }, [flash.error]);

    return (
        <>
            <Head
                title={entry.voucher_number ?? `Journal Entry #${entry.id}`}
            />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div className="flex flex-wrap items-center gap-3">
                    <Heading
                        title={
                            entry.voucher_number
                                ? `${entry.voucher_type?.name ?? 'Voucher'} ${entry.voucher_number}`
                                : `${entry.voucher_type?.name ?? 'Journal entry'} (draft #${entry.id})`
                        }
                        description={`${entry.entry_date.slice(0, 10)} · ${entry.reference ?? 'No reference'} · ${entry.currency?.code ?? 'Base currency'}${entry.branch ? ` · ${entry.branch.name}` : ''}`}
                    />
                        <StatusPill status={entry.status} />
                        {entry.approval_status ? <StatusPill status={entry.approval_status} label={entry.approval_status === 'pending' ? 'Awaiting approval' : undefined} /> : null}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/accounting/journal-entries">Back</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={`/accounting/journal-entries/${entry.id}/print`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Printer className="size-4" />
                                Print
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={`/accounting/journal-entries/${entry.id}/pdf`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileDown className="size-4" />
                                PDF
                            </a>
                        </Button>
                        {canEdit && (
                            <Button asChild variant="outline">
                                <Link
                                    href={`/accounting/journal-entries/${entry.id}/edit`}
                                >
                                    <Edit className="size-4" />
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {canPost && (
                            <Button onClick={post}>
                                <Send className="size-4" />
                                Post
                            </Button>
                        )}
                        {canSubmit && (
                            <Button onClick={submit}>
                                <Send className="size-4" />
                                Submit for approval
                            </Button>
                        )}
                        {canReview && (
                            <>
                                <Button onClick={approve}>
                                    <CheckCircle2 className="size-4" />
                                    Approve &amp; post
                                </Button>
                                <Button onClick={reject} variant="destructive">
                                    <XCircle className="size-4" />
                                    Reject
                                </Button>
                            </>
                        )}
                        {canReverse && (
                            <Button onClick={reverse} variant="outline">
                                <RotateCcw className="size-4" />
                                Reverse
                            </Button>
                        )}
                        {canVoid && (
                            <Button onClick={voidEntry} variant="destructive">
                                <Ban className="size-4" />
                                Void
                            </Button>
                        )}
                    </div>
                </div>

                {entry.source_document_number || entry.sourceable_type ? (
                    <div className="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg border bg-muted/30 px-4 py-3 text-sm">
                        <span className="font-medium">Source document</span>
                        {entry.source_document_number ? (
                            <span>
                                {documentTypes[
                                    entry.source_document_type ?? ''
                                ] ?? entry.source_document_type}{' '}
                                <span className="font-mono font-semibold">
                                    {entry.source_document_number}
                                </span>
                            </span>
                        ) : null}
                        {entry.source_document_date ? (
                            <span className="text-muted-foreground">
                                dated {entry.source_document_date.slice(0, 10)}
                            </span>
                        ) : null}
                        {entry.sourceable_type ? (
                            <span className="text-muted-foreground">
                                linked to{' '}
                                {entry.sourceable_type.split('\\').pop()} #
                                {entry.sourceable_id}
                            </span>
                        ) : null}
                    </div>
                ) : null}

                {requiresApproval || entry.approval_status ? (
                    <Alert
                        className={
                            entry.approval_status === 'rejected'
                                ? 'border-red-500/30 bg-red-500/5'
                                : 'border-indigo-500/30 bg-indigo-500/5'
                        }
                    >
                        <AlertTitle>
                            {entry.approval_status === 'pending' &&
                                'Awaiting approval'}
                            {entry.approval_status === 'approved' && 'Approved'}
                            {entry.approval_status === 'rejected' &&
                                'Rejected — edit and resubmit'}
                            {!entry.approval_status &&
                                'Approval required before posting'}
                        </AlertTitle>
                        {entry.rejection_reason ? (
                            <AlertDescription>
                                {entry.rejection_reason}
                            </AlertDescription>
                        ) : null}
                    </Alert>
                ) : null}

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

                <div className="rounded-xl border bg-card p-4 shadow-xs">
                    <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            ['Date', entry.entry_date.slice(0, 10)],
                            ['Voucher type', entry.voucher_type ? `${entry.voucher_type.code} · ${entry.voucher_type.name}` : '—'],
                            ['Reference', entry.reference ?? '—'],
                            ['Branch', entry.branch ? `${entry.branch.code} · ${entry.branch.name}` : '—'],
                            ['Currency', entry.currency?.code ?? 'Base currency'],
                            ['Posted at', entry.status === 'posted' ? dateTime(entry.posted_at) : 'Set when posted'],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</dt>
                                <dd className="mt-1 text-sm font-medium">{value}</dd>
                            </div>
                        ))}
                        <div className="sm:col-span-2">
                            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Description</dt>
                            <dd className="mt-1 text-sm font-medium">{entry.description ?? '—'}</dd>
                        </div>
                    </dl>
                </div>

                <div className="overflow-x-auto rounded-xl border bg-card shadow-xs">
                    <table className="w-full min-w-[860px] text-sm [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2">
                        <thead className="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th className="p-3">#</th>
                                <th className="p-3">Account</th>
                                <th className="p-3">Cost Center</th>
                                <th className="p-3 text-right">Debit</th>
                                <th className="p-3 text-right">Credit</th>
                                <th className="p-3">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entry.lines.map((line) => (
                                <tr key={line.id} className="border-t">
                                    <td className="p-3">{line.line_no}</td>
                                    <td className="p-3">
                                        {line.account?.account_code} -{' '}
                                        {line.account?.account_name}
                                    </td>
                                    <td className="p-3">
                                        {line.cost_center
                                            ? `${line.cost_center.code} - ${line.cost_center.name}`
                                            : '-'}
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
                                    <td className="p-3">
                                        {line.description ?? '-'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t bg-muted/30 font-semibold">
                                <td className="p-3" colSpan={3}>
                                    Total{' '}
                                    {Math.abs(entry.lines.reduce((sum, line) => sum + Number(line.debit), 0) - entry.lines.reduce((sum, line) => sum + Number(line.credit), 0)) < 0.005 ? (
                                        <span className="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">✓ Balanced</span>
                                    ) : (
                                        <span className="ml-2 rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">Out of balance</span>
                                    )}
                                </td>
                                <td className="p-3 text-right tabular-nums">
                                    {money(
                                        entry.lines.reduce(
                                            (sum, line) =>
                                                sum + Number(line.debit),
                                            0,
                                        ),
                                    )}
                                </td>
                                <td className="p-3 text-right tabular-nums">
                                    {money(
                                        entry.lines.reduce(
                                            (sum, line) =>
                                                sum + Number(line.credit),
                                            0,
                                        ),
                                    )}
                                </td>
                                <td className="p-3" />
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {permissions['attachments.view'] ? (
                    <AttachmentsPanel
                        entryId={entry.id}
                        attachments={attachments}
                        canUpload={
                            permissions['attachments.create'] === true &&
                            entry.status !== 'void'
                        }
                        canRemove={
                            permissions['attachments.delete'] === true &&
                            isDraft
                        }
                        rules={attachmentRules}
                    />
                ) : null}

                {trail.length ? (
                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="mb-3 text-sm font-semibold">Audit trail</div>
                        <ol className="relative space-y-3 border-l pl-5 text-sm">
                            {trail.map((step, index) => (
                                <li key={index} className="relative">
                                    <span className="absolute -left-[1.6rem] top-1.5 size-2.5 rounded-full bg-primary ring-4 ring-background" />
                                    <span className="font-medium">{step.step}</span>
                                    <span className="ml-2 text-muted-foreground">
                                        {step.by ? `by ${step.by} · ` : ''}
                                        {dateTime(step.at)}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </div>
                ) : null}
            </div>
        </>
    );
}

JournalEntryShow.layout = {
    breadcrumbs: [
        { title: 'Journal Entry', href: '/accounting/journal-entries' },
    ],
};
