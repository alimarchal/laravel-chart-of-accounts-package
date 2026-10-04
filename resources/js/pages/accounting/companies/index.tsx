import { Head, router, useForm } from '@inertiajs/react';
import { Building2, Plus, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { SearchableSelect } from '@/components/accounting/searchable-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Member = {
    id: number;
    name: string;
    email: string;
    is_default: boolean | number;
};

type Company = {
    id: number;
    code: string;
    name: string;
    legal_name: string | null;
    tax_number: string | null;
    registration_number: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    fiscal_year_start_month: number;
    is_active: boolean;
    members: Member[];
};

type User = { id: number; name: string; email: string };

const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

type CompanyFields = {
    code: string;
    name: string;
    legal_name: string;
    tax_number: string;
    registration_number: string;
    email: string;
    phone: string;
    address: string;
    fiscal_year_start_month: number;
};

const emptyCompany: CompanyFields = {
    code: '',
    name: '',
    legal_name: '',
    tax_number: '',
    registration_number: '',
    email: '',
    phone: '',
    address: '',
    fiscal_year_start_month: 1,
};

function CompanyForm({
    initial,
    submitLabel,
    onSubmit,
    processing,
    errors,
    onChange,
}: {
    initial: CompanyFields;
    submitLabel: string;
    onSubmit: () => void;
    processing: boolean;
    errors: Partial<Record<keyof CompanyFields, string>>;
    onChange: (field: keyof CompanyFields, value: string | number) => void;
}) {
    const text = (
        field: keyof CompanyFields,
        label: string,
        placeholder = '',
    ) => (
        <div className="flex flex-col gap-1">
            <Label htmlFor={field}>{label}</Label>
            <Input
                id={field}
                value={String(initial[field] ?? '')}
                placeholder={placeholder}
                onChange={(event) => onChange(field, event.target.value)}
            />
            <InputError message={errors[field]} />
        </div>
    );

    return (
        <form
            className="grid gap-3 md:grid-cols-3"
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit();
            }}
        >
            {text('code', 'Code', 'SUB')}
            {text('name', 'Name', 'Subsidiary Ltd')}
            {text('legal_name', 'Legal name')}
            {text('tax_number', 'Tax number (NTN)')}
            {text('registration_number', 'Registration number')}
            {text('email', 'Email')}
            {text('phone', 'Phone')}
            {text('address', 'Address')}
            <div className="flex flex-col gap-1">
                <Label htmlFor="fiscal_year_start_month">
                    Fiscal year starts in
                </Label>
                <select
                    id="fiscal_year_start_month"
                    className="h-9 rounded-md border bg-transparent px-3 text-sm"
                    value={initial.fiscal_year_start_month}
                    onChange={(event) =>
                        onChange(
                            'fiscal_year_start_month',
                            Number(event.target.value),
                        )
                    }
                >
                    {months.map((month, index) => (
                        <option key={month} value={index + 1}>
                            {month}
                        </option>
                    ))}
                </select>
                <InputError message={errors.fiscal_year_start_month} />
            </div>
            <div className="md:col-span-3">
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

function CompanyCard({ company, users }: { company: Company; users: User[] }) {
    const [editing, setEditing] = useState(false);
    const [newMember, setNewMember] = useState('');
    const form = useForm<CompanyFields>({
        code: company.code,
        name: company.name,
        legal_name: company.legal_name ?? '',
        tax_number: company.tax_number ?? '',
        registration_number: company.registration_number ?? '',
        email: company.email ?? '',
        phone: company.phone ?? '',
        address: company.address ?? '',
        fiscal_year_start_month: company.fiscal_year_start_month,
    });
    const memberIds = new Set(company.members.map((member) => member.id));
    const candidates = users
        .filter((user) => !memberIds.has(user.id))
        .map((user) => ({
            value: String(user.id),
            label: `${user.name} — ${user.email}`,
        }));

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Building2 className="size-5 text-muted-foreground" />
                    <div>
                        <div className="font-semibold">
                            {company.name}{' '}
                            <span className="font-mono text-xs text-muted-foreground">
                                {company.code}
                            </span>
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Fiscal year starts in{' '}
                            {months[company.fiscal_year_start_month - 1]}
                            {company.tax_number
                                ? ` · NTN ${company.tax_number}`
                                : ''}
                        </div>
                    </div>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setEditing(!editing)}
                >
                    {editing ? 'Close' : 'Edit'}
                </Button>
            </div>

            {editing ? (
                <CompanyForm
                    initial={form.data}
                    submitLabel="Save changes"
                    processing={form.processing}
                    errors={form.errors}
                    onChange={(field, value) =>
                        form.setData(field, value as never)
                    }
                    onSubmit={() =>
                        form.put(`/accounting/companies/${company.id}`, {
                            preserveScroll: true,
                        })
                    }
                />
            ) : null}

            <div>
                <div className="mb-2 text-sm font-medium">
                    People with access
                </div>
                {company.members.length ? (
                    <ul className="divide-y rounded-md border text-sm">
                        {company.members.map((member) => (
                            <li
                                key={member.id}
                                className="flex items-center justify-between gap-3 p-2"
                            >
                                <span>
                                    {member.name}{' '}
                                    <span className="text-muted-foreground">
                                        {member.email}
                                    </span>
                                    {member.is_default ? (
                                        <span className="ml-2 rounded bg-muted px-1.5 py-0.5 text-xs">
                                            default
                                        </span>
                                    ) : null}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove ${member.name}`}
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `Remove ${member.name}'s access to ${company.name}?`,
                                            )
                                        ) {
                                            router.delete(
                                                `/accounting/companies/${company.id}/users/${member.id}`,
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Only super-admins can use this company so far.
                    </p>
                )}
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <div className="min-w-64 flex-1">
                        <SearchableSelect
                            value={newMember}
                            options={candidates}
                            onChange={setNewMember}
                            placeholder="Add a person…"
                        />
                    </div>
                    <Button
                        variant="secondary"
                        disabled={!newMember}
                        onClick={() =>
                            router.post(
                                `/accounting/companies/${company.id}/users`,
                                { user_id: Number(newMember) },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setNewMember(''),
                                },
                            )
                        }
                    >
                        <UserPlus className="size-4" /> Give access
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function Companies({
    companies,
    users,
}: {
    companies: Company[];
    users: User[];
}) {
    const { flash } = useAccounting();
    const [creating, setCreating] = useState(false);
    const form = useForm<CompanyFields & { seed: boolean }>({
        ...emptyCompany,
        seed: true,
    });

    return (
        <>
            <Head title="Companies" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title="Companies"
                        description="Each company has its own chart of accounts, periods, journal and reports."
                    />
                    <Button onClick={() => setCreating(!creating)}>
                        <Plus className="size-4" /> New company
                    </Button>
                </div>

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                {creating ? (
                    <div className="space-y-3 rounded-lg border p-4">
                        <div className="font-medium">New company</div>
                        <CompanyForm
                            initial={form.data}
                            submitLabel="Create company"
                            processing={form.processing}
                            errors={form.errors}
                            onChange={(field, value) =>
                                form.setData(field, value as never)
                            }
                            onSubmit={() =>
                                form.post('/accounting/companies', {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        form.reset();
                                        setCreating(false);
                                    },
                                })
                            }
                        />
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.seed}
                                onChange={(event) =>
                                    form.setData('seed', event.target.checked)
                                }
                            />
                            Start with the standard chart of accounts, cost
                            centers, tax codes and the current fiscal year
                        </label>
                    </div>
                ) : null}

                <div className="space-y-4">
                    {companies.map((company) => (
                        <CompanyCard
                            key={company.id}
                            company={company}
                            users={users}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

Companies.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Companies', href: '/accounting/companies' },
    ],
};
