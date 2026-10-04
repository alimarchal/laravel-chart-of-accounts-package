<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

/**
 * The accounting permissions and role grants. Apps usually publish config/accounting.php once, so it
 * lacks permissions added by later versions: the package's own list is always merged in.
 */
final class AccountingPermissions
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_values(array_unique([
            ...self::packageDefaults()['permissions'] ?? [],
            ...(array) config('accounting.permissions', []),
        ]));
    }

    /**
     * Role definitions from the app's config, plus the package's grants of permissions the app's
     * (older) config does not know about, and package roles the app does not define.
     *
     * @return array<string, array<int, string>>
     */
    public static function roles(): array
    {
        $roles = (array) config('accounting.roles', []);
        $appPermissions = (array) config('accounting.permissions', []);

        foreach (self::packageDefaults()['roles'] ?? [] as $role => $permissions) {
            if (! array_key_exists($role, $roles)) {
                $roles[$role] = $permissions;

                continue;
            }

            if ($roles[$role] !== ['*'] && $permissions !== ['*']) {
                $unknownToApp = array_diff($permissions, $appPermissions);
                $roles[$role] = array_values(array_unique([...$roles[$role], ...$unknownToApp]));
            }
        }

        return $roles;
    }

    /**
     * @return array<string, mixed>
     */
    private static function packageDefaults(): array
    {
        static $defaults = null;

        return $defaults ??= require __DIR__.'/../../config/accounting.php';
    }
}
