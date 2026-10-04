import { Head, Link, useForm } from '@inertiajs/react';
import { KeyRound, Save } from 'lucide-react';
import { PermissionMatrix } from '@/components/accounting/permission-matrix';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type User = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    direct_permissions: string[] | null;
    role_permissions: string[];
};

export default function UserForm({
    user,
    roles,
    permissionGroups,
    isSelf = false,
}: {
    user: User | null;
    roles: string[];
    permissionGroups: Record<string, string[]> | null;
    isSelf?: boolean;
}) {
    const { permissions, flash } = useAccounting();
    const canAssignRoles = permissions['user.assign-role'] === true;
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        roles: user?.roles ?? ([] as string[]),
    });
    const direct = useForm({
        permissions: user?.direct_permissions ?? ([] as string[]),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => form.reset('password', 'password_confirmation'),
        };

        if (user) {
            form.put(`/accounting/users/${user.id}`, options);
        } else {
            form.post('/accounting/users', options);
        }
    };

    const toggleRole = (role: string) =>
        form.setData(
            'roles',
            form.data.roles.includes(role)
                ? form.data.roles.filter((r) => r !== role)
                : [...form.data.roles, role],
        );

    return (
        <>
            <Head title={user ? `Edit ${user.name}` : 'New user'} />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title={user ? user.name : 'New user'}
                        description={
                            user
                                ? 'Profile, roles and direct permissions. Changes are audited.'
                                : 'Create a login and give it the roles it needs.'
                        }
                    />
                    <Button asChild variant="outline">
                        <Link href="/accounting/users">All users</Link>
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

                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-lg border p-4"
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="email">E-mail</Label>
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                            />
                            <InputError message={form.errors.email} />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="password">
                                {user ? 'New password (optional)' : 'Password'}
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                            />
                            <InputError message={form.errors.password} />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password_confirmation}
                                onChange={(event) =>
                                    form.setData(
                                        'password_confirmation',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div>
                        <div className="mb-2 text-sm font-medium">Roles</div>
                        <div className="flex flex-wrap gap-2">
                            {roles.map((role) => (
                                <label
                                    key={role}
                                    className={`flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm ${form.data.roles.includes(role) ? 'border-primary bg-primary/5' : ''}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.roles.includes(role)}
                                        disabled={!canAssignRoles}
                                        onChange={() => toggleRole(role)}
                                    />
                                    {role}
                                </label>
                            ))}
                        </div>
                        <InputError
                            message={
                                errors.roles ??
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('roles.'),
                                )?.[1]
                            }
                        />
                        {!canAssignRoles ? (
                            <p className="mt-1 text-xs text-muted-foreground">
                                You cannot change roles (user.assign-role).
                            </p>
                        ) : null}
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        <Save className="size-4" />
                        {user ? 'Save user' : 'Create user'}
                    </Button>
                </form>

                {user && permissionGroups ? (
                    <form
                        className="space-y-4 rounded-lg border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            direct.put(
                                `/accounting/users/${user.id}/permissions`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div className="flex items-center gap-2 font-medium">
                                    <KeyRound className="size-4" /> Direct
                                    permissions
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    Extra permissions on top of the roles.
                                    Prefer roles; use this for exceptions.
                                    {isSelf
                                        ? ' You cannot widen your own access beyond what you hold.'
                                        : ''}
                                </div>
                            </div>
                            {permissions['user.assign-permission'] ? (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={direct.processing}
                                >
                                    Save permissions
                                </Button>
                            ) : null}
                        </div>
                        <PermissionMatrix
                            groups={permissionGroups}
                            selected={direct.data.permissions}
                            inherited={user.role_permissions}
                            disabled={!permissions['user.assign-permission']}
                            onChange={(value) =>
                                direct.setData('permissions', value)
                            }
                        />
                    </form>
                ) : null}
            </div>
        </>
    );
}

UserForm.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Users', href: '/accounting/users' },
    ],
};
