import { Head, Link } from '@inertiajs/react';
import FilterBar from '@/components/accounting/filter-bar';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Row = { id: number; code: string; name: string; category: string | null; acquisition_date: string; status: string; cost: string; accumulated: string; book_value: string };
type Props = {
    register: { as_of: string; rows: Row[]; totals: { cost: string; accumulated: string; book_value: string } };
    reconcile: { ledger: string; register: string; difference: string };
    categories: string[];
    filters: Record<string, string | null>;
};

const money = (value: string) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function FixedAssetsIndex({ register, reconcile, filters, categories }: Props) {
    const { permissions, flash } = useAccounting();

    return (
        <>
            <Head title="Fixed assets" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Fixed assets" description="Register of what the company owns, with depreciation and book value" />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline"><a href={`/accounting/fixed-assets/export/xlsx?as_of=${register.as_of}`}>Export</a></Button>
                        {permissions['fixed-assets.view'] && <Button asChild variant="outline"><Link href="/accounting/fixed-assets/depreciation">Depreciation</Link></Button>}
                        {permissions['fixed-assets.create'] && <Button asChild><Link href="/accounting/fixed-assets/create">New asset</Link></Button>}
                    </div>
                </div>
                <FilterBar
                    path="/accounting/fixed-assets"
                    values={filters}
                    fields={[
                        { name: 'search', label: 'Search', placeholder: 'Code, name or category' },
                        { name: 'as_of', label: 'As of', type: 'date', quiet: true },
                        { name: 'status', label: 'Status', type: 'select', options: { active: 'Active', disposed: 'Disposed' } },
                        { name: 'category', label: 'Category', type: 'select', options: Object.fromEntries(categories.map((name) => [name, name])) },
                        { name: 'acquired_from', label: 'Acquired from', type: 'date' },
                        { name: 'acquired_to', label: 'Acquired to', type: 'date' },
                        { name: 'min_cost', label: 'Cost from', type: 'number' },
                        { name: 'max_cost', label: 'Cost up to', type: 'number' },
                    ]}
                />
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                <div className="grid gap-4 sm:grid-cols-3">
                    {([['Cost', register.totals.cost], ['Accumulated depreciation', register.totals.accumulated], ['Book value', register.totals.book_value]] as const).map(([label, value]) => (
                        <Card key={label}><CardContent className="pt-6"><p className="text-sm text-muted-foreground">{label}</p><p className="mt-1 text-2xl font-semibold tabular-nums">{money(value)}</p></CardContent></Card>
                    ))}
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Assets</CardTitle>
                        <CardDescription>
                            Ledger vs register difference <span className={Number(reconcile.difference) !== 0 ? 'font-medium text-destructive' : ''}>{money(reconcile.difference)}</span> (ledger {money(reconcile.ledger)}, register {money(reconcile.register)})
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="text-left text-muted-foreground"><th className="py-2">Code</th><th>Name</th><th>Category</th><th>Acquired</th><th>Status</th><th className="text-right">Cost</th><th className="text-right">Accumulated</th><th className="text-right">Book value</th></tr></thead>
                            <tbody>
                                {register.rows.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="py-2"><Link href={`/accounting/fixed-assets/${row.id}`} className="font-medium hover:underline">{row.code}</Link></td>
                                        <td>{row.name}</td><td>{row.category}</td><td>{row.acquisition_date}</td>
                                        <td><Badge variant="outline">{row.status}</Badge></td>
                                        <td className="text-right tabular-nums">{money(row.cost)}</td><td className="text-right tabular-nums">{money(row.accumulated)}</td><td className="text-right tabular-nums">{money(row.book_value)}</td>
                                    </tr>
                                ))}
                                {register.rows.length === 0 && <tr><td colSpan={8} className="py-6 text-center text-muted-foreground">No assets yet.</td></tr>}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

FixedAssetsIndex.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Fixed assets', href: '/accounting/fixed-assets' }] };
