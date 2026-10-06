import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Account = { id: number; account_code: string; account_name: string; type: string };
type Asset = {
    id: number; code: string; name: string; category: string | null; acquisition_date: string; in_service_date: string; cost: string; salvage_value: string; useful_life_months: number;
    method: string; status: string; accumulated_depreciation: string; book_value: string; acquisition_entry_id: number | null; disposed_at: string | null; disposal_proceeds: string | null;
    disposal_gain_loss: string | null; disposal_entry_id: number | null;
};
type Props = { asset: Asset; history: Array<{ month: string; amount: string; journal_entry_id: number }>; accounts: Account[]; today: string };

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });
const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function FixedAssetShow({ asset, history, accounts, today }: Props) {
    const { permissions, flash } = useAccounting();
    const form = useForm({ disposal_date: today, proceeds: '', proceeds_account_id: '', gain_loss_account_id: '', notes: '' });
    const dispose = (event: FormEvent) => {
        event.preventDefault();
        if (confirm('Dispose of this asset? Cost and accumulated depreciation will be removed.')) form.post(`/accounting/fixed-assets/${asset.id}/dispose`);
    };
    const facts: Array<[string, string]> = [
        ['Acquired', asset.acquisition_date], ['In service', asset.in_service_date], ['Cost', money(asset.cost)], ['Salvage value', money(asset.salvage_value)],
        ['Life', `${asset.useful_life_months} months`], ['Method', asset.method.replace('_', ' ')], ['Accumulated depreciation', money(asset.accumulated_depreciation)], ['Book value', money(asset.book_value)],
    ];

    return (
        <>
            <Head title={asset.code} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title={`${asset.code} · ${asset.name}`} description={asset.category ?? undefined} />
                    <div className="flex items-center gap-2">
                        <Badge variant="outline">{asset.status}</Badge>
                        {asset.status === 'active' && permissions['fixed-assets.update'] && <Button asChild variant="outline"><Link href={`/accounting/fixed-assets/${asset.id}/edit`}>Edit</Link></Button>}
                        {permissions['fixed-assets.delete'] && asset.status === 'active' && !asset.acquisition_entry_id && history.length === 0 && (
                            <Button variant="ghost" onClick={() => confirm('Delete this asset?') && router.delete(`/accounting/fixed-assets/${asset.id}`)}>Delete</Button>
                        )}
                    </div>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <Card><CardContent className="grid gap-4 pt-6 sm:grid-cols-4">{facts.map(([label, value]) => (<div key={label}><p className="text-sm text-muted-foreground">{label}</p><p className="font-medium tabular-nums">{value}</p></div>))}</CardContent></Card>
                {asset.status === 'disposed' && (
                    <Card><CardHeader><CardTitle>Disposal</CardTitle></CardHeader><CardContent className="text-sm">
                        Disposed on {asset.disposed_at} for {money(asset.disposal_proceeds ?? '0')}; {Number(asset.disposal_gain_loss) >= 0 ? 'gain' : 'loss'} of {money(String(Math.abs(Number(asset.disposal_gain_loss))))}.
                    </CardContent></Card>
                )}
                <Card>
                    <CardHeader><CardTitle>Depreciation booked</CardTitle></CardHeader>
                    <CardContent>
                        <table className="w-full text-sm"><thead><tr className="text-left text-muted-foreground"><th className="py-1">Month</th><th className="text-right">Amount</th><th className="text-right">Entry</th></tr></thead>
                            <tbody>
                                {history.map((row) => (<tr key={row.month} className="border-t"><td className="py-1">{row.month}</td><td className="text-right tabular-nums">{money(row.amount)}</td><td className="text-right"><Link href={`/accounting/journal-entries/${row.journal_entry_id}`} className="hover:underline">#{row.journal_entry_id}</Link></td></tr>))}
                                {history.length === 0 && <tr><td colSpan={3} className="py-4 text-center text-muted-foreground">Nothing booked yet.</td></tr>}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
                {asset.status === 'active' && permissions['fixed-assets.dispose'] && (
                    <form onSubmit={dispose}>
                        <Card>
                            <CardHeader><CardTitle>Sell or scrap</CardTitle></CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-3">
                                <div className="space-y-1"><Label htmlFor="disposal_date">Date</Label><Input id="disposal_date" type="date" value={form.data.disposal_date} onChange={(event) => form.setData('disposal_date', event.target.value)} /><InputError message={form.errors.disposal_date} /></div>
                                <div className="space-y-1"><Label htmlFor="proceeds">Proceeds (blank when scrapped)</Label><Input id="proceeds" type="number" step="any" value={form.data.proceeds} onChange={(event) => form.setData('proceeds', event.target.value)} /><InputError message={form.errors.proceeds} /></div>
                                <div className="space-y-1"><Label htmlFor="proceeds_account_id">Proceeds received in</Label>
                                    <select id="proceeds_account_id" className={selectClass} value={form.data.proceeds_account_id} onChange={(event) => form.setData('proceeds_account_id', event.target.value)}>
                                        <option value="">—</option>{accounts.filter((account) => account.type === 'ASSET').map((account) => (<option key={account.id} value={account.id}>{account.account_code} {account.account_name}</option>))}
                                    </select><InputError message={form.errors.proceeds_account_id} /></div>
                                <div className="space-y-1"><Label htmlFor="gain_loss_account_id">Gain / loss account</Label>
                                    <select id="gain_loss_account_id" className={selectClass} value={form.data.gain_loss_account_id} onChange={(event) => form.setData('gain_loss_account_id', event.target.value)}>
                                        <option value="">Choose…</option>{accounts.filter((account) => ['INCOME', 'EXPENSE'].includes(account.type)).map((account) => (<option key={account.id} value={account.id}>{account.account_code} {account.account_name}</option>))}
                                    </select><InputError message={form.errors.gain_loss_account_id} /></div>
                                <div className="space-y-1 md:col-span-2"><Label htmlFor="notes">Notes</Label><Input id="notes" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div>
                                <div><Button type="submit" variant="destructive" disabled={form.processing}>Dispose</Button></div>
                            </CardContent>
                        </Card>
                    </form>
                )}
            </div>
        </>
    );
}

FixedAssetShow.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Fixed assets', href: '/accounting/fixed-assets' }, { title: 'Asset', href: '#' }] };
