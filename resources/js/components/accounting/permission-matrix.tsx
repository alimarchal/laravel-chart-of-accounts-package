import { Check } from 'lucide-react';

const label = (value: string): string =>
    value
        .split(/[-_]/)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

/**
 * Permissions grouped by area, with a "select all" per area. Permissions in `inherited` (from roles) are shown
 * ticked and locked, so the screen explains where every grant comes from.
 */
export function PermissionMatrix({
    groups,
    selected,
    onChange,
    inherited = [],
    disabled = false,
}: {
    groups: Record<string, string[]>;
    selected: string[];
    onChange: (permissions: string[]) => void;
    inherited?: string[];
    disabled?: boolean;
}) {
    const has = (permission: string) => selected.includes(permission);
    const toggle = (permission: string) =>
        onChange(
            has(permission)
                ? selected.filter((p) => p !== permission)
                : [...selected, permission],
        );
    const toggleGroup = (permissions: string[]) => {
        const free = permissions.filter((p) => !inherited.includes(p));
        const all = free.every(has);
        onChange(
            all
                ? selected.filter((p) => !free.includes(p))
                : Array.from(new Set([...selected, ...free])),
        );
    };

    return (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {Object.entries(groups).map(([group, permissions]) => {
                const count = permissions.filter(
                    (p) => has(p) || inherited.includes(p),
                ).length;

                return (
                    <div key={group} className="rounded-lg border p-3">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-sm font-medium">
                                {label(group)}
                            </span>
                            <button
                                type="button"
                                disabled={disabled}
                                onClick={() => toggleGroup(permissions)}
                                className="text-xs text-muted-foreground underline-offset-4 hover:underline disabled:opacity-50"
                            >
                                {count}/{permissions.length} · toggle all
                            </button>
                        </div>
                        <ul className="space-y-1">
                            {permissions.map((permission) => {
                                const fromRole = inherited.includes(permission);

                                return (
                                    <li key={permission}>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                className="size-4"
                                                checked={
                                                    fromRole || has(permission)
                                                }
                                                disabled={disabled || fromRole}
                                                onChange={() =>
                                                    toggle(permission)
                                                }
                                            />
                                            <span className="font-mono text-xs">
                                                {permission.slice(
                                                    group.length + 1,
                                                ) || permission}
                                            </span>
                                            {fromRole ? (
                                                <span className="inline-flex items-center gap-0.5 text-xs text-muted-foreground">
                                                    <Check className="size-3" />
                                                    via role
                                                </span>
                                            ) : null}
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                );
            })}
        </div>
    );
}
