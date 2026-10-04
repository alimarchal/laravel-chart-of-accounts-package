import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { PermissionMatrix } from '@/components/accounting/permission-matrix';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Role = {
    id: number;
    name: string;
    permissions: string[] | null;
    users_count: number | null;
    is_super_admin: boolean;
};

export default function RoleForm({
    role,
    permissionGroups,
}: {
    role: Role | null;
    permissionGroups: Record<string, string[]>;
}) {
    const { flash } = useAccounting();
    const form = useForm({
        name: role?.name ?? '',
        permissions: role?.permissions ?? ([] as string[]),
    });

    return (
        <>
            <Head title={role ? `Role ${role.name}` : 'New role'} />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title={role ? `Role: ${role.name}` : 'New role'}
                        description={
                            role
                                ? `${role.users_count ?? 0} user(s) have this role. Changes apply to all of them and are audited.`
                                : 'Name the role and tick its permissions.'
                        }
                    />
                    <Button asChild variant="outline">
                        <Link href="/accounting/roles">All roles</Link>
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
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (role) {
                            form.put(`/accounting/roles/${role.id}`, {
                                preserveScroll: true,
                            });
                        } else {
                            form.post('/accounting/roles');
                        }
                    }}
                >
                    <div className="flex flex-wrap items-end gap-3 rounded-lg border p-4">
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                className="w-72"
                                value={form.data.name}
                                disabled={role?.is_super_admin}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <span className="text-sm text-muted-foreground">
                            {form.data.permissions.length} permission(s)
                            selected
                        </span>
                        <Button
                            type="submit"
                            className="ml-auto"
                            disabled={form.processing}
                        >
                            <Save className="size-4" />
                            {role ? 'Save role' : 'Create role'}
                        </Button>
                    </div>
                    {role?.is_super_admin ? (
                        <p className="text-sm text-muted-foreground">
                            super-admin has every permission through a gate,
                            whatever is ticked here.
                        </p>
                    ) : null}
                    <PermissionMatrix
                        groups={permissionGroups}
                        selected={form.data.permissions}
                        onChange={(value) => form.setData('permissions', value)}
                    />
                </form>
            </div>
        </>
    );
}

RoleForm.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Roles', href: '/accounting/roles' },
    ],
};
