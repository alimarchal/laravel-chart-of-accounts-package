import { Head, Link, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

type Candidate = {
    id: number;
    debit: string;
    credit: string;
    description: string | null;
    journal_entry?: {
        entry_date: string;
        reference: string | null;
    };
};

type Props = {
    reconciliation: { id: number; statement_balance: string; book_balance: string };
    candidates: Candidate[];
};

export default function ReconciliationMatch({ reconciliation, candidates }: Props) {
    const form = useForm<{ line_ids: number[] }>({ line_ids: [] });

    const toggle = (id: number, checked: boolean) => {
        form.setData('line_ids', checked ? [...form.data.line_ids, id] : form.data.line_ids.filter((lineId) => lineId !== id));
    };

    return (
        <>
            <Head title="Match Reconciliation" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">Match Reconciliation #{reconciliation.id}</h1>
                    <Button asChild variant="outline">
                        <Link href="/accounting/reconciliations">Back</Link>
                    </Button>
                </div>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/accounting/reconciliations/${reconciliation.id}/reconcile`);
                    }}
                    className="flex flex-col gap-4"
                >
                    <div className="overflow-hidden rounded-lg border">
                        <table className="w-full min-w-[860px] text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="w-12 p-3"></th>
                                    <th className="p-3">Date</th>
                                    <th className="p-3">Reference</th>
                                    <th className="p-3 text-right">Debit</th>
                                    <th className="p-3 text-right">Credit</th>
                                    <th className="p-3">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                {candidates.map((candidate) => (
                                    <tr key={candidate.id} className="border-t">
                                        <td className="p-3">
                                            <Checkbox checked={form.data.line_ids.includes(candidate.id)} onCheckedChange={(checked) => toggle(candidate.id, checked === true)} />
                                        </td>
                                        <td className="p-3">{candidate.journal_entry?.entry_date}</td>
                                        <td className="p-3">{candidate.journal_entry?.reference ?? '-'}</td>
                                        <td className="p-3 text-right font-mono">{candidate.debit}</td>
                                        <td className="p-3 text-right font-mono">{candidate.credit}</td>
                                        <td className="p-3">{candidate.description ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing || form.data.line_ids.length === 0}>
                            <Check className="size-4" />
                            Reconcile selected
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ReconciliationMatch.layout = {
    breadcrumbs: [{ title: 'Reconciliation Match', href: '/accounting/reconciliations' }],
};
