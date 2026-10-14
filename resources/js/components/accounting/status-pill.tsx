import { Badge } from '@/components/ui/badge';

const tones = {
    ok: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    warn: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    bad: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
    info: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
};

const map: Record<string, keyof typeof tones> = {
    posted: 'ok', paid: 'ok', approved: 'ok', active: 'ok', matched: 'ok', reconciled: 'ok', closed: 'ok', completed: 'ok', settled: 'ok', open: 'ok', accepted: 'ok', sent: 'ok',
    draft: 'warn', pending: 'warn', submitted: 'warn', partial: 'warn', skipped: 'warn', queued: 'warn', scheduled: 'warn', unmatched: 'warn',
    void: 'bad', rejected: 'bad', cancelled: 'bad', canceled: 'bad', failed: 'bad', overdue: 'bad', inactive: 'bad', missing: 'bad',
};

/** One status word as a coloured pill, so every list reads the same way. */
export function StatusPill({ status, label }: { status: string; label?: string }) {
    return (
        <Badge variant="outline" className={`capitalize ${tones[map[status.toLowerCase()] ?? 'info']}`}>
            {label ?? status}
        </Badge>
    );
}

export default StatusPill;
