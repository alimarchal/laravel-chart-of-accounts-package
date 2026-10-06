import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAccounting } from '@/lib/accounting';

type Props = {
    preview: { up_to: string; total: string; months: Array<{ month: string; total: string; assets: Array<{ asset_id: number; code: string; name: string; amount: string }> }> };
    upTo: string;
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function FixedAssetDepreciation({ preview, upTo }: Props) {
    const { permissions, flash } = useAccounting();
    const [date, setDate] = useState(upTo);

    return (
        <>
            <Head title="Depreciation" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Depreciation" description="What is due, month by month, up to the end of the chosen month" />
                    <div className="flex items-center gap-2">
                        <Input type="date" value={date} onChange={(event) => setDate(event.target.value)} className="w-40" />
                        <Button variant="outline" onClick={() => router.get('/accounting/fixed-assets/depreciation', { up_to: date }, { preserveState: true })}>Preview</Button>
                        {permissions['fixed-assets.depreciate'] && preview.months.length > 0 && (
                            <Button onClick={() => confirm(`Book ${money(preview.total)} of depreciation?`) && router.post('/accounting/fixed-assets/depreciation', { up_to: preview.up_to })}>Book {money(preview.total)}</Button>
                        )}
                    </div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                {preview.months.length === 0 && <Card><CardContent className="py-8 text-center text-muted-foreground">Nothing to depreciate up to {preview.up_to}.</CardContent></Card>}
                {preview.months.map((month) => (
                    <Card key={month.month}>
                        <CardHeader><CardTitle>{month.month}</CardTitle><CardDescription>{money(month.total)} across {month.assets.length} asset(s)</CardDescription></CardHeader>
                        <CardContent>
                            <table className="w-full text-sm"><tbody>
                                {month.assets.map((asset) => (<tr key={asset.asset_id} className="border-t first:border-0"><td className="py-1">{asset.code}</td><td>{asset.name}</td><td className="text-right tabular-nums">{money(asset.amount)}</td></tr>))}
                            </tbody></table>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

FixedAssetDepreciation.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Fixed assets', href: '/accounting/fixed-assets' }, { title: 'Depreciation', href: '/accounting/fixed-assets/depreciation' }] };
