import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useAccounting } from '@/lib/accounting';

type Role = {
    id: number;
    name: string;
    permissions_count: number | null;
    users_count: number | null;
    is_super_admin: boolean;
};

export default function Roles({ roles }: { roles: Role[] }) {
    const { flash } = useAccounting();

    const remove = (role: Role) => {
        if (
            window.confirm(
                `Delete role ${role.name}? ${role.users_count ?? 0} user(s) will lose it.`,
            )
        ) {
            router.delete(`/accounting/roles/${role.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Roles" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title="Roles"
                        description="Bundles of permissions. You can only grant what you hold yourself; only a super-admin manages the super-admin role."
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href="/accounting/users">Users</Link>
                        </Button>
                        <Button asChild>
                            <Link href="/accounting/roles/create">
                                <Plus className="size-4" /> New role
                            </Link>
                        </Button>
                    </div>
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

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[560px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Role</th>
                                <th className="p-3 text-right font-medium">
                                    Permissions
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Users
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {roles.map((role) => (
                                <tr
                                    key={role.id}
                                    className="border-t hover:bg-muted/30"
                                >
                                    <td className="p-3 font-medium">
                                        <span className="inline-flex items-center gap-2">
                                            {role.is_super_admin ? (
                                                <ShieldCheck className="size-4 text-indigo-600" />
                                            ) : null}
                                            {role.name}
                                        </span>
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {role.is_super_admin
                                            ? 'all'
                                            : role.permissions_count}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {role.users_count}
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link
                                                href={`/accounting/roles/${role.id}/edit`}
                                            >
                                                <Pencil className="size-4" />{' '}
                                                Edit
                                            </Link>
                                        </Button>
                                        {!role.is_super_admin ? (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                aria-label={`Delete ${role.name}`}
                                                onClick={() => remove(role)}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        ) : null}
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

Roles.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Roles', href: '/accounting/roles' },
    ],
};
