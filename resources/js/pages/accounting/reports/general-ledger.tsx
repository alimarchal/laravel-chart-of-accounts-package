import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function GeneralLedger({ entries, totals }: { entries: { data: Array<Record<string, unknown>> }; totals: Record<string, number> }) {
    return (
        <>
            <Head title="General Ledger" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">General Ledger</h1>
                    <div className="flex flex-wrap gap-2">
                        {['csv', 'xlsx', 'pdf'].map((format) => (
                            <Button key={format} asChild variant="outline">
                                <Link href={`/accounting/reports/general-ledger/export/${format}`}>
                                    <Download className="size-4" />
                                    {format.toUpperCase()}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </div>
                <div className="grid gap-3 md:grid-cols-3">
                    {Object.entries(totals).map(([key, value]) => (
                        <div key={key} className="rounded-lg border p-4">
                            <div className="text-sm text-muted-foreground">{key}</div>
                            <div className="text-xl font-semibold">{value.toFixed(2)}</div>
                        </div>
                    ))}
                </div>
                <pre className="overflow-auto rounded-lg border p-4 text-xs">{JSON.stringify(entries.data, null, 2)}</pre>
            </div>
        </>
    );
}

GeneralLedger.layout = {
    breadcrumbs: [{ title: 'General Ledger', href: '/accounting/reports/general-ledger' }],
};
