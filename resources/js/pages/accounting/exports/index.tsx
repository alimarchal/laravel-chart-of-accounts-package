import { Head, router } from '@inertiajs/react';
import { Download, Loader2, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useAccounting } from '@/lib/accounting';

type ExportItem = {
    id: number;
    title: string;
    format: string;
    filters: Record<string, string>;
    status: 'queued' | 'running' | 'ready' | 'failed';
    rows: number | null;
    size: number | null;
    error: string | null;
    created_at: string | null;
    finished_at: string | null;
};

const statusStyle: Record<ExportItem['status'], string> = {
    queued: 'bg-muted text-muted-foreground',
    running: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    ready: 'bg-green-500/10 text-green-700 dark:text-green-400',
    failed: 'bg-red-500/10 text-red-700 dark:text-red-400',
};

export default function Exports({ exports }: { exports: ExportItem[] }) {
    const { flash } = useAccounting();
    const pending = exports.some(
        (item) => item.status === 'queued' || item.status === 'running',
    );

    // Refresh while something is still being prepared.
    useEffect(() => {
        if (!pending) {
            return;
        }

        const timer = window.setInterval(
            () => router.reload({ only: ['exports'] }),
            4000,
        );

        return () => window.clearInterval(timer);
    }, [pending]);

    return (
        <>
            <Head title="Exports" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title="My exports"
                        description="Large Excel and PDF reports are prepared in the background and kept here for a few days."
                    />
                    <Button
                        variant="outline"
                        onClick={() => router.reload({ only: ['exports'] })}
                    >
                        <RefreshCw className="size-4" /> Refresh
                    </Button>
                </div>

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Report</th>
                                <th className="p-3 font-medium">Filters</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 text-right font-medium">
                                    Rows
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {exports.length ? (
                                exports.map((item) => (
                                    <tr key={item.id} className="border-t">
                                        <td className="p-3">
                                            <div className="font-medium">
                                                {item.title}{' '}
                                                <span className="text-xs text-muted-foreground uppercase">
                                                    {item.format}
                                                </span>
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {item.created_at
                                                    ? new Date(
                                                          item.created_at,
                                                      ).toLocaleString()
                                                    : ''}
                                            </div>
                                        </td>
                                        <td className="p-3 text-xs text-muted-foreground">
                                            {Object.entries(item.filters)
                                                .map(([k, v]) => `${k}: ${v}`)
                                                .join(' · ') || '—'}
                                        </td>
                                        <td className="p-3">
                                            <span
                                                className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${statusStyle[item.status]}`}
                                            >
                                                {item.status === 'running' ||
                                                item.status === 'queued' ? (
                                                    <Loader2 className="size-3 animate-spin" />
                                                ) : null}
                                                {item.status}
                                            </span>
                                            {item.error ? (
                                                <div className="mt-1 text-xs text-red-600">
                                                    {item.error}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {item.rows ?? '—'}
                                        </td>
                                        <td className="p-3 text-right whitespace-nowrap">
                                            {item.status === 'ready' ? (
                                                <Button asChild size="sm">
                                                    <a
                                                        href={`/accounting/exports/${item.id}/download`}
                                                    >
                                                        <Download className="size-4" />{' '}
                                                        Download
                                                    </a>
                                                </Button>
                                            ) : null}
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                aria-label="Delete export"
                                                onClick={() =>
                                                    router.delete(
                                                        `/accounting/exports/${item.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        className="p-6 text-center text-muted-foreground"
                                        colSpan={5}
                                    >
                                        No exports yet. Very large Excel or PDF
                                        reports appear here when they are ready.
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

Exports.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Exports', href: '/accounting/exports' },
    ],
};
