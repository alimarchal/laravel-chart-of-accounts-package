<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Support\AccountingPermissions;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Prints who can do what: the role × permission matrix as it is in the database (or in the config
 * with --config), plus a segregation-of-duties check.
 */
class AccountingRolesCommand extends Command
{
    protected $signature = 'accounting:roles {--config : Show the defaults from config/accounting.php instead of the database}';

    protected $description = 'Show the accounting role/permission matrix and check segregation of duties.';

    public function handle(): int
    {
        $matrix = $this->option('config') ? $this->fromConfig() : $this->fromDatabase();
        $roles = array_keys($matrix);
        $permissions = AccountingPermissions::all();

        $this->table(
            array_merge(['Permission'], $roles),
            array_map(fn (string $permission) => array_merge(
                [$permission],
                array_map(fn (string $role) => in_array($permission, $matrix[$role], true) ? '✔' : '', $roles),
            ), $permissions),
        );

        $conflicts = collect($matrix)
            ->except('super-admin')
            ->filter(fn (array $granted) => in_array('journal-entries.create', $granted, true) && in_array('journal-entries.approve', $granted, true))
            ->keys();

        if ($conflicts->isNotEmpty()) {
            $this->warn('Segregation of duties: these roles can both create and approve journal entries: '.$conflicts->implode(', '));

            return self::FAILURE;
        }

        $this->info('Segregation of duties OK: no role (except super-admin) can both create and approve journal entries.');
        $this->line('Under maker-checker the approver must also be a different user from the maker, super-admin included.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function fromConfig(): array
    {
        $all = AccountingPermissions::all();

        return collect(AccountingPermissions::roles())
            ->map(fn (array $permissions) => $permissions === ['*'] ? $all : $permissions)
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function fromDatabase(): array
    {
        if (! class_exists(Role::class) || Permission::query()->doesntExist()) {
            return $this->fromConfig();
        }

        return Role::query()->with('permissions')->orderBy('name')->get()
            ->mapWithKeys(fn (Role $role) => [$role->name => $role->permissions->pluck('name')->all()])
            ->all();
    }
}
