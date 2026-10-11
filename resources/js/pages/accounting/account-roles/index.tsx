import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Role = { role: string; label: string; multiple: boolean; groups: boolean; config: string | null; overridden: boolean; accounts: Array<{ id: number; account_code: string; account_name: string }> };
type Account = { id: number; account_code: string; account_name: string; is_group: boolean };
type Props = { roles: Role[]; chart: string; presets: Record<string, string>; accounts: Account[] };

const selectClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function AccountRoles({ roles, chart, presets, accounts }: Props) {
    const { flash } = useAccounting();
    const [state, setState] = useState<Record<string, number[]>>(Object.fromEntries(roles.map((row) => [row.role, row.accounts.map((account) => account.id)])));
    const [saving, setSaving] = useState(false);
    const changed = roles.some((row) => JSON.stringify(state[row.role]) !== JSON.stringify(row.accounts.map((account) => account.id)));
    const editable = Object.fromEntries(roles.filter((row) => !row.overridden).map((row) => [row.role, state[row.role]]));
    const save = () => router.put('/accounting/account-roles', { roles: editable }, { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) });

    return (
        <>
            <Head title="Account roles" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Account roles" description={`Which account the modules use for cash, bank, salaries, receivables and so on. Chart: ${presets[chart] ?? chart}.`} />
                    <Button asChild variant="outline"><Link href="/accounting">Accounting</Link></Button>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                <Card>
                    <CardHeader><CardTitle>Roles</CardTitle></CardHeader>
                    <CardContent className="divide-y">
                        {roles.map((row) => (
                            <label key={row.role} className="grid gap-2 py-3 text-sm md:grid-cols-[18rem_1fr] md:items-start">
                                <span>
                                    <span className="font-medium">{row.label}</span>
                                    {row.overridden && <span className="block text-xs text-amber-700">Fixed in the configuration ({row.config})</span>}
                                </span>
                                <select
                                    className={selectClass}
                                    multiple={row.multiple}
                                    size={row.multiple ? 6 : 1}
                                    disabled={row.overridden}
                                    aria-label={row.label}
                                    value={(state[row.role] ?? []).map(String)}
                                    onChange={(event) => setState({ ...state, [row.role]: Array.from(event.target.selectedOptions).map((option) => Number(option.value)).filter((id) => id > 0) })}
                                >
                                    {!row.multiple && <option value="0">— the chart's default —</option>}
                                    {accounts.filter((account) => row.groups || !account.is_group).map((account) => (<option key={account.id} value={account.id}>{account.account_code} {account.account_name}{account.is_group ? ' (group)' : ''}</option>))}
                                </select>
                            </label>
                        ))}
                    </CardContent>
                </Card>
                <Button disabled={!changed || saving} onClick={save}>Save</Button>
            </div>
        </>
    );
}

AccountRoles.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Account roles', href: '/accounting/account-roles' }] };
