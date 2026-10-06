import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Props = { warehouses: Array<{ id: number; code: string; name: string; address: string | null; is_active: boolean }> };

export default function InventoryWarehouses({ warehouses }: Props) {
    const { permissions, flash } = useAccounting();
    const form = useForm({ code: '', name: '', address: '', is_active: true });
    const add = (event: FormEvent) => {
        event.preventDefault();
        form.post('/accounting/inventory/warehouses', { onSuccess: () => form.reset() });
    };

    return (
        <>
            <Head title="Warehouses" />
            <div className="space-y-6 p-4">
                <Heading title="Warehouses" description="Places stock is kept" />
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}
                <Card>
                    <CardContent className="pt-6">
                        <table className="w-full text-sm"><tbody>
                            {warehouses.map((row) => (
                                <tr key={row.id} className="border-t first:border-0">
                                    <td className="py-2 font-medium">{row.code}</td><td>{row.name}</td><td className="text-muted-foreground">{row.address}</td><td>{row.is_active ? '' : 'inactive'}</td>
                                    <td className="text-right">
                                        {permissions['inventory.manage'] && <Button variant="ghost" size="sm" onClick={() => router.put(`/accounting/inventory/warehouses/${row.id}`, { code: row.code, name: row.name, address: row.address, is_active: !row.is_active })}>{row.is_active ? 'Deactivate' : 'Activate'}</Button>}
                                        {permissions['inventory.manage'] && <Button variant="ghost" size="sm" onClick={() => confirm('Delete this warehouse?') && router.delete(`/accounting/inventory/warehouses/${row.id}`)}>Delete</Button>}
                                    </td>
                                </tr>
                            ))}
                            {warehouses.length === 0 && <tr><td className="py-4 text-center text-muted-foreground">No warehouses yet.</td></tr>}
                        </tbody></table>
                    </CardContent>
                </Card>
                {permissions['inventory.manage'] && (
                    <form onSubmit={add}>
                        <Card>
                            <CardHeader><CardTitle>Add a warehouse</CardTitle></CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-4">
                                <div className="space-y-1"><Label htmlFor="code">Code</Label><Input id="code" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /><InputError message={form.errors.code} /></div>
                                <div className="space-y-1"><Label htmlFor="name">Name</Label><Input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /><InputError message={form.errors.name} /></div>
                                <div className="space-y-1"><Label htmlFor="address">Address</Label><Input id="address" value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} /></div>
                                <div className="pt-6"><Button type="submit" disabled={form.processing}>Add</Button></div>
                            </CardContent>
                        </Card>
                    </form>
                )}
            </div>
        </>
    );
}

InventoryWarehouses.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Inventory', href: '/accounting/inventory' }, { title: 'Warehouses', href: '/accounting/inventory/warehouses' }] };
