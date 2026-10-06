import { Head, Link, router } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccounting } from '@/lib/accounting';

type Party = {
    id: number;
    type: 'customer' | 'supplier' | 'both';
    code: string;
    name: string;
    email: string | null;
    phone: string | null;
    payment_terms_days: number;
    credit_limit: string | null;
    is_active: boolean;
};
type Props = { parties: Party[]; filters: { type: string | null; search: string | null } };

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function Parties({ parties, filters }: Props) {
    const { permissions, flash } = useAccounting();
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.type ?? '');
    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get('/accounting/parties', { type: type || undefined, search: search || undefined }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Customers & Suppliers" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading title="Customers & Suppliers" description="Who owes you and whom you owe: invoices, bills, payments, statements and ageing." />
                    <div className="flex flex-wrap gap-2">
                        {permissions['parties.create'] ? (
                            <Button asChild className="gap-2">
                                <Link href="/accounting/parties/create">
                                    <Plus className="size-4" />
                                    New customer or supplier
                                </Link>
                            </Button>
                        ) : null}
                        <Button asChild variant="outline">
                            <Link href="/accounting/party-documents">Invoices & bills</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/accounting/party-payments">Receipts & payments</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/accounting/receivables/aging?side=receivable">Ageing</Link>
                        </Button>
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

                <form onSubmit={apply} className="flex flex-wrap items-end gap-3">
                    <Input className="w-64" placeholder="Search by name or code" value={search} onChange={(event) => setSearch(event.target.value)} aria-label="Search" />
                    <select className={selectClass} value={type} onChange={(event) => setType(event.target.value)} aria-label="Type">
                        <option value="">All</option>
                        <option value="customer">Customers</option>
                        <option value="supplier">Suppliers</option>
                        <option value="both">Both</option>
                    </select>
                    <Button type="submit" variant="outline">Filter</Button>
                </form>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Code</th>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">Contact</th>
                                <th className="px-3 py-2 text-right font-medium">Terms</th>
                                <th className="px-3 py-2 text-right font-medium">Credit limit</th>
                            </tr>
                        </thead>
                        <tbody>
                            {parties.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">
                                        <Users className="mx-auto mb-2 size-5" />
                                        No customers or suppliers yet.
                                    </td>
                                </tr>
                            ) : null}
                            {parties.map((party) => (
                                <tr key={party.id} className="border-t">
                                    <td className="px-3 py-2">{party.code}</td>
                                    <td className="px-3 py-2">
                                        <Link href={`/accounting/parties/${party.id}`} className="font-medium hover:underline">
                                            {party.name}
                                        </Link>
                                        {party.is_active ? null : <Badge className="ml-2 bg-muted text-muted-foreground">inactive</Badge>}
                                    </td>
                                    <td className="px-3 py-2 capitalize">{party.type}</td>
                                    <td className="px-3 py-2">{party.email ?? party.phone ?? '—'}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{party.payment_terms_days} days</td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {party.credit_limit === null ? '—' : Number(party.credit_limit).toLocaleString(undefined, { minimumFractionDigits: 2 })}
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

Parties.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Customers & Suppliers', href: '/accounting/parties' },
    ],
};
