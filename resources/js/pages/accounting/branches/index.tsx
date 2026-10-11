import { Head, router, useForm } from '@inertiajs/react';
import { GitBranch, Plus, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import FilterBar from '@/components/accounting/filter-bar';
import { SearchableSelect } from '@/components/accounting/searchable-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';
import { confirmThen } from '@/lib/confirm';

type Member = { id: number; name: string; email: string };

type Branch = {
    id: number;
    code: string;
    name: string;
    city: string | null;
    address: string | null;
    phone: string | null;
    is_head_office: boolean;
    is_active: boolean;
    members: Member[];
};

type User = { id: number; name: string; email: string };

type BranchFields = {
    code: string;
    name: string;
    city: string;
    address: string;
    phone: string;
    is_head_office: boolean;
    is_active: boolean;
};

const emptyBranch: BranchFields = {
    code: '',
    name: '',
    city: '',
    address: '',
    phone: '',
    is_head_office: false,
    is_active: true,
};

function BranchForm({
    values,
    submitLabel,
    processing,
    errors,
    onChange,
    onSubmit,
}: {
    values: BranchFields;
    submitLabel: string;
    processing: boolean;
    errors: Partial<Record<keyof BranchFields, string>>;
    onChange: (field: keyof BranchFields, value: string | boolean) => void;
    onSubmit: () => void;
}) {
    const text = (field: 'code' | 'name' | 'city' | 'address' | 'phone', label: string) => (
        <div className="flex flex-col gap-1">
            <Label htmlFor={`branch-${field}`}>{label}</Label>
            <Input
                id={`branch-${field}`}
                value={values[field]}
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
            {text('code', 'Code')}
            {text('name', 'Name')}
            {text('city', 'City')}
            {text('phone', 'Phone')}
            {text('address', 'Address')}
            <div className="flex items-end gap-4 text-sm">
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={values.is_head_office}
                        onChange={(event) => onChange('is_head_office', event.target.checked)}
                    />
                    Head office
                </label>
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={values.is_active}
                        onChange={(event) => onChange('is_active', event.target.checked)}
                    />
                    Active
                </label>
            </div>
            <div className="md:col-span-3">
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

function BranchCard({ branch, users }: { branch: Branch; users: User[] }) {
    const { permissions } = useAccounting();
    const [editing, setEditing] = useState(false);
    const [newMember, setNewMember] = useState('');
    const form = useForm<BranchFields>({
        code: branch.code,
        name: branch.name,
        city: branch.city ?? '',
        address: branch.address ?? '',
        phone: branch.phone ?? '',
        is_head_office: Boolean(branch.is_head_office),
        is_active: Boolean(branch.is_active),
    });
    const memberIds = new Set(branch.members.map((member) => member.id));
    const candidates = users
        .filter((user) => !memberIds.has(user.id))
        .map((user) => ({ value: String(user.id), label: `${user.name} — ${user.email}` }));

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <GitBranch className="size-5 text-muted-foreground" />
                    <div>
                        <div className="font-semibold">
                            {branch.name}{' '}
                            <span className="font-mono text-xs text-muted-foreground">{branch.code}</span>
                            {branch.is_head_office ? (
                                <span className="ml-2 rounded bg-muted px-1.5 py-0.5 text-xs">head office</span>
                            ) : null}
                            {!branch.is_active ? (
                                <span className="ml-2 rounded bg-muted px-1.5 py-0.5 text-xs">inactive</span>
                            ) : null}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {[branch.city, branch.phone, branch.address].filter(Boolean).join(' · ')}
                        </div>
                    </div>
                </div>
                <div className="flex gap-2">
                    {permissions['branches.update'] ? (
                        <Button variant="outline" size="sm" onClick={() => setEditing(!editing)}>
                            {editing ? 'Close' : 'Edit'}
                        </Button>
                    ) : null}
                    {permissions['branches.delete'] ? (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Delete ${branch.name}`}
                            onClick={() => {
                                confirmThen(`Delete ${branch.name}?`, () => {
                                    router.delete(`/accounting/branches/${branch.id}`, { preserveScroll: true });
                                });
                            }}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    ) : null}
                </div>
            </div>

            {editing ? (
                <BranchForm
                    values={form.data}
                    submitLabel="Save changes"
                    processing={form.processing}
                    errors={form.errors}
                    onChange={(field, value) => form.setData(field, value as never)}
                    onSubmit={() =>
                        form.put(`/accounting/branches/${branch.id}`, {
                            preserveScroll: true,
                            onSuccess: () => setEditing(false),
                        })
                    }
                />
            ) : null}

            <div>
                <div className="mb-1 text-sm font-medium">People who work in this branch only</div>
                <p className="mb-2 text-xs text-muted-foreground">
                    A person with no branch works in all branches. Once added to a branch, they work in their branches only.
                </p>
                {branch.members.length ? (
                    <ul className="divide-y rounded-md border text-sm">
                        {branch.members.map((member) => (
                            <li key={member.id} className="flex items-center justify-between gap-3 p-2">
                                <span>
                                    {member.name} <span className="text-muted-foreground">{member.email}</span>
                                </span>
                                {permissions['branches.update'] ? (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Remove ${member.name}`}
                                        onClick={() => {
                                            confirmThen(`Remove ${member.name} from ${branch.name}?`, () => {
                                                router.delete(`/accounting/branches/${branch.id}/users/${member.id}`, {
                                                    preserveScroll: true,
                                                });
                                            });
                                        }}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-muted-foreground">Nobody is limited to this branch yet.</p>
                )}
                {permissions['branches.update'] ? (
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
                                    `/accounting/branches/${branch.id}/users`,
                                    { user_id: Number(newMember) },
                                    { preserveScroll: true, onSuccess: () => setNewMember('') },
                                )
                            }
                        >
                            <UserPlus className="size-4" /> Give access
                        </Button>
                    </div>
                ) : null}
            </div>
        </div>
    );
}

export default function Branches({
    filters,
    branches,
    users,
}: {
    filters: Record<string, string | null>;
    branches: Branch[];
    users: User[];
}) {
    const { flash, permissions } = useAccounting();
    const [creating, setCreating] = useState(false);
    const form = useForm<BranchFields>({ ...emptyBranch });

    return (
        <>
            <Head title="Branches" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title="Branches"
                        description="Head office, campuses or shops of this company. Entries, invoices and payments are booked to a branch, and reports can be limited to one."
                    />
                    {permissions['branches.create'] ? (
                        <Button onClick={() => setCreating(!creating)}>
                            <Plus className="size-4" /> New branch
                        </Button>
                    ) : null}
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
                        <div className="font-medium">New branch</div>
                        <BranchForm
                            values={form.data}
                            submitLabel="Create branch"
                            processing={form.processing}
                            errors={form.errors}
                            onChange={(field, value) => form.setData(field, value as never)}
                            onSubmit={() =>
                                form.post('/accounting/branches', {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        form.reset();
                                        setCreating(false);
                                    },
                                })
                            }
                        />
                    </div>
                ) : null}

                <FilterBar
                    path="/accounting/branches"
                    values={filters}
                    fields={[
                        { name: 'search', label: 'Search', placeholder: 'Code, name, city, phone or address' },
                        { name: 'is_active', label: 'Status', type: 'select', options: { '1': 'Active', '0': 'Inactive' } },
                    ]}
                />
                <div className="space-y-4">
                    {branches.map((branch) => (
                        <BranchCard key={branch.id} branch={branch} users={users} />
                    ))}
                    {branches.length === 0 ? <p className="text-sm text-muted-foreground">No branches yet.</p> : null}
                </div>
            </div>
        </>
    );
}

Branches.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Branches', href: '/accounting/branches' },
    ],
};
