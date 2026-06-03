import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Field = {
    name: string;
    label: string;
    type: 'text' | 'number' | 'date' | 'textarea' | 'checkbox' | 'select';
    options?: Record<string, string>;
    step?: string;
};

type FormValue = string | number | boolean | null;

type Props = {
    title: string;
    routeName: string;
    fields: Field[];
    record: Record<string, FormValue> | null;
    method: 'post' | 'put';
    action: string;
};

export default function AccountingResourceForm({ title, routeName, fields, record, method, action }: Props) {
    const initialData = Object.fromEntries(
        fields.map((field) => {
            const value = record?.[field.name];

            if (field.type === 'checkbox') {
                return [field.name, Boolean(value)];
            }

            return [field.name, value === null || value === undefined ? '' : String(value)];
        }),
    ) as Record<string, string | boolean>;

    const form = useForm(initialData);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (method === 'put') {
            form.put(action);

            return;
        }

        form.post(action);
    };

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading title={title} description="Keep setup records accurate before they are used by ledger transactions." />
                    <Button asChild variant="outline">
                        <Link href={`/accounting/${routeName}`}>Back</Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                    <Card className="rounded-lg lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                            <CardDescription>Primary accounting record information.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {fields.filter((field) => field.type !== 'checkbox').map((field) => {
                                const error = form.errors[field.name];

                                if (field.type === 'textarea') {
                                    return (
                                        <div key={field.name} className="flex flex-col gap-2 md:col-span-2">
                                            <Label htmlFor={field.name}>{field.label}</Label>
                                            <textarea
                                                id={field.name}
                                                value={String(form.data[field.name] ?? '')}
                                                onChange={(event) => form.setData(field.name, event.target.value)}
                                                className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            />
                                            <InputError message={error} />
                                        </div>
                                    );
                                }

                                if (field.type === 'select') {
                                    return (
                                        <div key={field.name} className="flex flex-col gap-2">
                                            <Label>{field.label}</Label>
                                            <Select value={String(form.data[field.name] ?? '')} onValueChange={(value) => form.setData(field.name, value)}>
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder={`Select ${field.label.toLowerCase()}`} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(field.options ?? {}).map(([value, label]) => (
                                                        <SelectItem key={value} value={value}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={error} />
                                        </div>
                                    );
                                }

                                return (
                                    <div key={field.name} className="flex flex-col gap-2">
                                        <Label htmlFor={field.name}>{field.label}</Label>
                                        <Input
                                            id={field.name}
                                            type={field.type}
                                            step={field.step}
                                            value={String(form.data[field.name] ?? '')}
                                            onChange={(event) => form.setData(field.name, event.target.value)}
                                        />
                                        <InputError message={error} />
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    {fields.some((field) => field.type === 'checkbox') ? (
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle>Options</CardTitle>
                                <CardDescription>Operational status and flags.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {fields.filter((field) => field.type === 'checkbox').map((field) => {
                                    const error = form.errors[field.name];

                                    return (
                                        <div key={field.name} className="flex items-center gap-3 rounded-md border p-3">
                                    <Checkbox
                                        id={field.name}
                                        checked={Boolean(form.data[field.name])}
                                        onCheckedChange={(checked) => form.setData(field.name, checked === true)}
                                    />
                                    <Label htmlFor={field.name}>{field.label}</Label>
                                    <InputError message={error} />
                                </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    ) : null}

                    <div className="flex gap-2 lg:col-span-3">
                        <Button type="submit" disabled={form.processing}>
                            <Save className="size-4" />
                            Save
                        </Button>
                        <Button type="button" variant="outline" onClick={() => form.reset()}>Reset</Button>
                    </div>
                </form>
            </div>
        </>
    );
}

AccountingResourceForm.layout = {
    breadcrumbs: [{ title: 'Accounting', href: '/accounting' }],
};
