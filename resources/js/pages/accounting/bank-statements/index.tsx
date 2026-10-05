import { Head, Link } from '@inertiajs/react';
import { Landmark, Upload } from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAccounting } from '@/lib/accounting';

type Statement = {
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

const money = (value: string | null) =>
    value === null
        ? '—'
        : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function BankStatements({
    statements,
}: {
    statements: Statement[];
}) {
    const { permissions, flash } = useAccounting();

    return (
        <>
            <Head title="Bank Statements" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title="Bank Statements"
                        description="Import statements from your bank, match them to the ledger and reconcile."
                    />
                    {permissions['bank-statements.import'] ? (
                        <Button asChild className="gap-2">
                            <Link href="/accounting/bank-statements/import">
                                <Upload className="size-4" />
                                Import statement
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
                                <th className="px-3 py-2 font-medium">File</th>
                                <th className="px-3 py-2 font-medium">Bank account</th>
                                <th className="px-3 py-2 font-medium">Period</th>
                                <th className="px-3 py-2 text-right font-medium">Closing balance</th>
                                <th className="px-3 py-2 text-right font-medium">Transactions</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {statements.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">
                                        <Landmark className="mx-auto mb-2 size-5" />
                                        No statements imported yet.
                                    </td>
                                </tr>
                            ) : null}
                            {statements.map((statement) => (
                                <tr key={statement.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link
                                            href={`/accounting/bank-statements/${statement.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {statement.file_name}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{statement.bank}</td>
                                    <td className="px-3 py-2">
                                        {statement.from_date} → {statement.to_date}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {money(statement.closing_balance)}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {statement.lines_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        {statement.reconciliation_id ? (
                                            <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                reconciled
                                            </Badge>
                                        ) : statement.unmatched_count > 0 ? (
                                            <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                                {statement.unmatched_count} to match
                                            </Badge>
                                        ) : (
                                            <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300">
                                                ready to reconcile
                                            </Badge>
                                        )}
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

BankStatements.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Bank Statements', href: '/accounting/bank-statements' },
    ],
};
