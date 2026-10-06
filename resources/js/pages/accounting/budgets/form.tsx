import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccountingI18n } from '@/lib/i18n';

type Line = {
    chart_of_account_id: string;
    cost_center_id: string;
    annual: string;
    monthly: boolean;
    amounts: Record<string, string>;
};
type Props = {
    budget: {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
        notes: string | null;
        lines: Array<{ chart_of_account_id: number; cost_center_id: number | null; annual: string; amounts: Record<string, string> }>;
    } | null;
    accounts: Array<{ id: number; account_code: string; account_name: string; type: string }>;
    costCenters: Array<{ id: number; code: string; name: string }>;
    today: string;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const monthsBetween = (start: string, end: string): string[] => {
    const months: string[] = [];
    if (!start || !end) return months;
    const cursor = new Date(`${start.slice(0, 7)}-01T00:00:00`);
    const last = new Date(`${end.slice(0, 7)}-01T00:00:00`);
    while (cursor <= last && months.length <= 24) {
        months.push(`${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}`);
        cursor.setMonth(cursor.getMonth() + 1);
    }
    return months;
};
const emptyLine = (): Line => ({ chart_of_account_id: '', cost_center_id: '', annual: '', monthly: false, amounts: {} });

export default function BudgetForm({ budget, accounts, costCenters, today }: Props) {
    useAccountingI18n();
    const year = today.slice(0, 4);
    const form = useForm<{
        name: string;
        start_date: string;
        end_date: string;
        notes: string;
        from_actuals: boolean;
        uplift_percent: string;
        lines: Line[];
    }>({
        name: budget?.name ?? '',
        start_date: budget?.start_date ?? `${year}-01-01`,
        end_date: budget?.end_date ?? `${year}-12-31`,
        notes: budget?.notes ?? '',
        from_actuals: false,
        uplift_percent: '',
        lines: budget
            ? budget.lines.map((line) => ({
                  chart_of_account_id: String(line.chart_of_account_id),
                  cost_center_id: line.cost_center_id ? String(line.cost_center_id) : '',
                  annual: line.annual,
                  monthly: true,
                  amounts: line.amounts,
              }))
            : [emptyLine()],
    });
    const months = monthsBetween(form.data.start_date, form.data.end_date);
    const setLine = (index: number, patch: Partial<Line>) =>
        form.setData('lines', form.data.lines.map((line, position) => (position === index ? { ...line, ...patch } : line)));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            name: data.name,
            start_date: data.start_date,
            end_date: data.end_date,
            notes: data.notes || null,
            from_actuals: data.from_actuals ? { uplift_percent: data.uplift_percent === '' ? 0 : Number(data.uplift_percent) } : null,
            lines: data.lines
                .filter((line) => line.chart_of_account_id !== '')
                .map((line) => ({
                    chart_of_account_id: Number(line.chart_of_account_id),
                    cost_center_id: line.cost_center_id === '' ? null : Number(line.cost_center_id),
                    ...(line.monthly
                        ? { amounts: Object.fromEntries(Object.entries(line.amounts).filter(([, value]) => value !== '')) }
                        : { annual: line.annual === '' ? null : line.annual }),
                })),
        }));
        if (budget) {
            form.put(`/accounting/budgets/${budget.id}`);
        } else {
            form.post('/accounting/budgets');
        }
    };

    return (
        <>
            <Head title={budget ? 'Edit Budget' : 'New Budget'} />
            <form onSubmit={submit} className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <Heading
                    title={budget ? 'Edit Budget' : 'New Budget'}
                    description="Amounts are in the base currency: income earned and expense spent. An annual figure is spread evenly over the months."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Budget</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-4">
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="name">Name</Label>
                            <Input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="start_date">First month</Label>
                            <Input id="start_date" type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} />
                            <InputError message={form.errors.start_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="end_date">Last month</Label>
                            <Input id="end_date" type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} />
                            <InputError message={form.errors.end_date} />
                        </div>
                        <div className="grid gap-2 md:col-span-4">
                            <Label htmlFor="notes">Notes</Label>
                            <Input id="notes" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Accounts</CardTitle>
                        <CardDescription>One line per account (and cost center). Only income and expense accounts are budgeted.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {form.data.lines.map((line, index) => (
                            <div key={index} className="grid gap-2 rounded-md border p-3">
                                <div className="grid gap-2 md:grid-cols-[2fr_1.2fr_1fr_auto_auto] md:items-center">
                                    <select className={selectClass} value={line.chart_of_account_id} onChange={(event) => setLine(index, { chart_of_account_id: event.target.value })} aria-label="Account">
                                        <option value="">Select an account</option>
                                        {accounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.account_code} - {account.account_name} ({account.type.toLowerCase()})
                                            </option>
                                        ))}
                                    </select>
                                    <select className={selectClass} value={line.cost_center_id} onChange={(event) => setLine(index, { cost_center_id: event.target.value })} aria-label="Cost center">
                                        <option value="">No cost center</option>
                                        {costCenters.map((center) => (
                                            <option key={center.id} value={center.id}>
                                                {center.code} - {center.name}
                                            </option>
                                        ))}
                                    </select>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="Annual"
                                        disabled={line.monthly}
                                        value={line.annual}
                                        onChange={(event) => setLine(index, { annual: event.target.value })}
                                        aria-label="Annual amount"
                                    />
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setLine(index, { monthly: !line.monthly })}>
                                        {line.monthly ? 'Use annual' : 'By month'}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        title="Remove"
                                        onClick={() => form.setData('lines', form.data.lines.filter((_, position) => position !== index))}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                                {line.monthly ? (
                                    <div className="grid grid-cols-2 gap-2 md:grid-cols-6">
                                        {months.map((month) => (
                                            <div key={month} className="grid gap-1">
                                                <span className="text-xs text-muted-foreground">{month}</span>
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={line.amounts[month] ?? ''}
                                                    onChange={(event) => setLine(index, { amounts: { ...line.amounts, [month]: event.target.value } })}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                ) : null}
                                <InputError message={form.errors[`lines.${index}.chart_of_account_id` as keyof typeof form.errors]} />
                            </div>
                        ))}
                        <InputError message={form.errors.lines} />
                        <div>
                            <Button type="button" variant="outline" className="gap-2" onClick={() => form.setData('lines', [...form.data.lines, emptyLine()])}>
                                <Plus className="size-4" />
                                Add account
                            </Button>
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.from_actuals} onChange={(event) => form.setData('from_actuals', event.target.checked)} />
                            Also add every account that had income or expenses in the same months last year, raised by
                            <Input
                                type="number"
                                step="any"
                                className="w-20"
                                disabled={!form.data.from_actuals}
                                value={form.data.uplift_percent}
                                onChange={(event) => form.setData('uplift_percent', event.target.value)}
                                aria-label="Uplift percent"
                            />
                            %
                        </label>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" className="gap-2" disabled={form.processing}>
                        <Save className="size-4" />
                        {budget ? 'Save changes' : 'Create budget'}
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href="/accounting/budgets">Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

BudgetForm.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Budgets', href: '/accounting/budgets' },
    ],
};
