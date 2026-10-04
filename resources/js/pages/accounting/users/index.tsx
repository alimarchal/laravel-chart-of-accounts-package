import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccounting } from '@/lib/accounting';

type User = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
};

export default function Users({
    users,
    roles,
    filters,
}: {
    users: Paginated<User>;
    roles: string[];
    filters: Record<string, string>;
}) {
    const { permissions, flash } = useAccounting();
    const [search, setSearch] = useState({
        name: filters.name ?? '',
        email: filters.email ?? '',
        role: filters.role ?? '',
    });

    const apply = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/accounting/users',
            Object.fromEntries(
                Object.entries(search)
                    .filter(([, value]) => value !== '')
                    .map(([key, value]) => [`filter[${key}]`, value]),
            ),
            { preserveState: true, replace: true },
        );
    };

    const remove = (user: User) => {
        if (window.confirm(`Delete ${user.name} (${user.email})?`)) {
            router.delete(`/accounting/users/${user.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Users" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <Heading
                        title="Users"
                        description="Who can use accounting, and with which roles. Every change is recorded in the audit trail."
                    />
                    <div className="flex gap-2">
                        {permissions['accounting.manage-settings'] ? (
                            <Button asChild variant="outline">
                                <Link href="/accounting/roles">Roles</Link>
                            </Button>
                        ) : null}
                        {permissions['user.create'] ? (
                            <Button asChild>
                                <Link href="/accounting/users/create">
                                    <Plus className="size-4" /> New user
                                </Link>
                            </Button>
                        ) : null}
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

                <form
                    onSubmit={apply}
                    className="flex flex-wrap items-end gap-2 rounded-lg border p-3"
                >
                    <Input
                        placeholder="Name"
                        className="w-48"
                        value={search.name}
                        onChange={(event) =>
                            setSearch({ ...search, name: event.target.value })
                        }
                    />
                    <Input
                        placeholder="E-mail"
                        className="w-56"
                        value={search.email}
                        onChange={(event) =>
                            setSearch({ ...search, email: event.target.value })
                        }
                    />
                    <select
                        aria-label="Role"
                        className="h-9 rounded-md border bg-transparent px-3 text-sm"
                        value={search.role}
                        onChange={(event) =>
                            setSearch({ ...search, role: event.target.value })
                        }
                    >
                        <option value="">All roles</option>
                        {roles.map((role) => (
                            <option key={role} value={role}>
                                {role}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="outline">
                        <Search className="size-4" /> Search
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 font-medium">E-mail</th>
                                <th className="p-3 font-medium">Roles</th>
                                <th className="p-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-t hover:bg-muted/30"
                                >
                                    <td className="p-3 font-medium">
                                        {user.name}
                                    </td>
                                    <td className="p-3">{user.email}</td>
                                    <td className="p-3">
                                        <div className="flex flex-wrap gap-1">
                                            {user.roles.length ? (
                                                user.roles.map((role) => (
                                                    <span
                                                        key={role}
                                                        className="rounded-full bg-muted px-2 py-0.5 text-xs"
                                                    >
                                                        {role}
                                                    </span>
                                                ))
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    no role
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        {permissions['user.update'] ? (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <Link
                                                    href={`/accounting/users/${user.id}/edit`}
                                                >
                                                    <Pencil className="size-4" />{' '}
                                                    Edit
                                                </Link>
                                            </Button>
                                        ) : null}
                                        {permissions['user.delete'] ? (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                aria-label={`Delete ${user.name}`}
                                                onClick={() => remove(user)}
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

                {users.links.length > 3 ? (
                    <div className="flex flex-wrap gap-1">
                        {users.links.map((link, index) => (
                            <Button
                                key={index}
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                disabled={!link.url}
                                onClick={() =>
                                    link.url &&
                                    router.get(
                                        link.url,
                                        {},
                                        { preserveState: true },
                                    )
                                }
                            >
                                <span
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            </Button>
                        ))}
                    </div>
                ) : null}
            </div>
        </>
    );
}

Users.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Users', href: '/accounting/users' },
    ],
};
