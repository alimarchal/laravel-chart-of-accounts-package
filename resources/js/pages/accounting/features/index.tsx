import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Feature = { key: string; label: string; description: string; group: string; parent: string | null; enabled: boolean; own: boolean; default: boolean; source: string };
type Props = { features: Feature[] };

export default function AccountingFeatures({ features }: Props) {
    const { flash } = useAccounting();
    const [state, setState] = useState<Record<string, boolean>>(Object.fromEntries(features.map((feature) => [feature.key, feature.own])));
    const [saving, setSaving] = useState(false);
    const groups = [...new Set(features.map((feature) => feature.group))];
    const changed = features.some((feature) => state[feature.key] !== feature.own);
    const save = () => router.put('/accounting/features', { features: state }, { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) });

    return (
        <>
            <Head title="Features" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Features" description="Turn a module or a payroll feature off to hide its screens and close its API. Its data is kept and comes back when you turn it on again." />
                    <Button asChild variant="outline"><Link href="/accounting">Accounting</Link></Button>
                </div>
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {groups.map((group) => (
                    <Card key={group}>
                        <CardHeader><CardTitle>{group}</CardTitle></CardHeader>
                        <CardContent className="divide-y">
                            {features.filter((feature) => feature.group === group).map((feature) => (
                                <label key={feature.key} className={`flex items-start gap-3 py-3 text-sm ${feature.parent ? 'pl-6' : ''}`}>
                                    <input type="checkbox" className="mt-1" checked={state[feature.key]} onChange={(event) => setState({ ...state, [feature.key]: event.target.checked })} />
                                    <span>
                                        <span className="font-medium">{feature.label}</span>
                                        {feature.parent && !state[feature.parent] && state[feature.key] && <span className="ml-2 text-xs text-amber-700">off because {feature.parent} is off</span>}
                                        {feature.source === 'config' && !feature.default && <span className="ml-2 text-xs text-muted-foreground">off by default (config)</span>}
                                        <span className="block text-muted-foreground">{feature.description}</span>
                                    </span>
                                </label>
                            ))}
                        </CardContent>
                    </Card>
                ))}
                <Button disabled={!changed || saving} onClick={save}>Save</Button>
            </div>
        </>
    );
}

AccountingFeatures.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Features', href: '/accounting/features' }] };
