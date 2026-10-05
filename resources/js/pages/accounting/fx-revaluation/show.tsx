import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useAccounting } from '@/lib/accounting';

type Props = {
    revaluation: {
        id: number;
        as_of_date: string;
        total_gain: string;
        total_loss: string;
        journal_entry_id: number;
        voucher_number: string | null;
        reversal_entry_id: number | null;
        reversal_voucher_number: string | null;
        reversal_date: string | null;
        gain_loss_account: string | null;
        notes: string | null;
        lines: Array<{
            account: string;
            currency: string | null;
            foreign_balance: string;
            rate: string;
            carrying_base: string;
            revalued_base: string;
            adjustment: string;
        }>;
    };
};

const money = (value: string) =>
    Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function FxRevaluationShow({ revaluation }: Props) {
    const { permissions, flash } = useAccounting();

    return (
        <>
            <Head title={`Revaluation ${revaluation.as_of_date}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title={`Revaluation as of ${revaluation.as_of_date}`}
                        description={`Posted as ${revaluation.voucher_number ?? `entry #${revaluation.journal_entry_id}`} against ${revaluation.gain_loss_account ?? 'the gain/loss account'}.`}
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={`/accounting/journal-entries/${revaluation.journal_entry_id}`}>View entry</Link>
                        </Button>
                        {permissions['fx-revaluation.run'] && !revaluation.reversal_entry_id ? (
                            <Button
                                onClick={() =>
                                    router.post(`/accounting/fx-revaluation/${revaluation.id}/reverse`, {}, { preserveScroll: true })
                                }
                            >
                                Reverse the day after
                            </Button>
                        ) : null}
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

                <div className="text-sm text-muted-foreground">
                    Gain {money(revaluation.total_gain)} · Loss {money(revaluation.total_loss)} ·{' '}
                    {revaluation.reversal_date
                        ? `reversed on ${revaluation.reversal_date} (${revaluation.reversal_voucher_number ?? ''})`
                        : 'not reversed'}
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Account</th>
                                <th className="px-3 py-2 text-right font-medium">Foreign balance</th>
                                <th className="px-3 py-2 text-right font-medium">Rate</th>
                                <th className="px-3 py-2 text-right font-medium">Carrying</th>
                                <th className="px-3 py-2 text-right font-medium">Revalued</th>
                                <th className="px-3 py-2 text-right font-medium">Adjustment</th>
                            </tr>
                        </thead>
                        <tbody>
                            {revaluation.lines.map((line) => (
                                <tr key={line.account} className="border-t">
                                    <td className="px-3 py-2">{line.account}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {money(line.foreign_balance)} {line.currency}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">{Number(line.rate)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(line.carrying_base)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(line.revalued_base)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{money(line.adjustment)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

FxRevaluationShow.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Currency Revaluation', href: '/accounting/fx-revaluation' },
    ],
};
