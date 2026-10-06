import { Head, Link } from '@inertiajs/react';
import { PiggyBank, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAccounting } from '@/lib/accounting';

type Budget = {
    id: number;
    name: string;
    status: 'draft' | 'approved' | 'closed';
    start_date: string;
    end_date: string;
    lines_count: number | null;
};

const style: Record<Budget['status'], string> = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    closed: 'bg-muted text-muted-foreground',
};

export default function Budgets({ budgets }: { budgets: Budget[] }) {
    const { permissions, flash } = useAccounting();

    return (
        <>
            <Head title="Budgets" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title="Budgets"
                        description="Plan income and expenses by account and month, then follow budget against actual."
                    />
                    {permissions['budgets.create'] ? (
                        <Button asChild className="gap-2">
                            <Link href="/accounting/budgets/create">
                                <Plus className="size-4" />
                                New budget
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
                                <th className="px-3 py-2 font-medium">Period</th>
                                <th className="px-3 py-2 text-right font-medium">Amounts</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {budgets.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-3 py-8 text-center text-muted-foreground">
                                        <PiggyBank className="mx-auto mb-2 size-5" />
                                        No budgets yet.
                                    </td>
                                </tr>
                            ) : null}
                            {budgets.map((budget) => (
                                <tr key={budget.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/accounting/budgets/${budget.id}`} className="font-medium hover:underline">
                                            {budget.name}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">
                                        {budget.start_date} → {budget.end_date}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">{budget.lines_count}</td>
                                    <td className="px-3 py-2">
                                        <Badge className={style[budget.status]}>{budget.status}</Badge>
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

Budgets.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Budgets', href: '/accounting/budgets' },
    ],
};
