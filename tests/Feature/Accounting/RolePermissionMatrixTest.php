<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingPermissionSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
});

function userWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('grants each role exactly the intended capabilities', function (string $role, array $can, array $cannot): void {
    $user = userWithRole($role);

    foreach ($can as $permission) {
        expect($user->can($permission))->toBeTrue("{$role} should have {$permission}");
    }

    foreach ($cannot as $permission) {
        expect($user->can($permission))->toBeFalse("{$role} must not have {$permission}");
    }
})->with([
    'super-admin' => ['super-admin', ['journal-entries.post', 'journal-entries.approve', 'user.assign-role', 'periods.reopen', 'audit-logs.view'], []],
    'admin (IT, no accounting writes)' => ['admin', ['user.create', 'user.assign-role', 'accounting.manage-settings'], ['journal-entries.create', 'journal-entries.post', 'journal-entries.approve', 'periods.close', 'chart-of-accounts.create']],
    'accountant (maker)' => ['accountant', ['journal-entries.create', 'journal-entries.post', 'journal-entries.reverse', 'journal-entries.void', 'periods.close', 'bank-accounts.create', 'reconciliations.create', 'currencies.update', 'audit-logs.view'], ['journal-entries.approve', 'periods.reopen', 'user.create', 'user.assign-role', 'accounting.manage-settings', 'chart-of-accounts.delete']],
    'approver (checker)' => ['approver', ['journal-entries.approve', 'journal-entries.view', 'reports.trial-balance.view'], ['journal-entries.create', 'journal-entries.update', 'journal-entries.post', 'user.create']],
    'auditor (read-only + audit trail)' => ['auditor', ['audit-logs.view', 'journal-entries.view', 'reports.general-ledger.view', 'bank-accounts.view'], ['journal-entries.create', 'journal-entries.approve', 'currencies.update', 'user.view']],
    'viewer (read-only)' => ['viewer', ['journal-entries.view', 'reports.balance-sheet.view'], ['journal-entries.create', 'audit-logs.view', 'user.view', 'journal-entries.approve']],
]);

it('keeps makers and checkers apart: no role except super-admin can both create and approve', function (): void {
    $conflicts = Role::query()->with('permissions')->get()
        ->reject(fn (Role $role) => $role->name === 'super-admin')
        ->filter(fn (Role $role) => $role->hasPermissionTo('journal-entries.create') && $role->hasPermissionTo('journal-entries.approve'))
        ->pluck('name');

    expect($conflicts->all())->toBe([]);
    expect(Artisan::call('accounting:roles'))->toBe(0);
});

it('flags a customised role that breaks segregation of duties', function (): void {
    Role::findByName('accountant')->givePermissionTo('journal-entries.approve');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Artisan::call('accounting:roles'))->toBe(1)
        ->and(Artisan::output())->toContain('accountant');
});

it('enforces each role on the real API routes', function (): void {
    $check = function (string $role, string $method, string $uri, int $expected): void {
        Sanctum::actingAs(userWithRole($role));
        $status = $this->json($method, '/api/v1/accounting/'.$uri)->status();

        expect($status === 403)->toBe($expected === 403, "{$role} {$method} {$uri} returned {$status}");
    };

    $check('viewer', 'GET', 'reports/trial-balance', 200);
    $check('viewer', 'POST', 'journal-entries', 403);
    $check('approver', 'POST', 'journal-entries', 403);
    $check('admin', 'POST', 'journal-entries/simple', 403);
    $check('auditor', 'GET', 'reports/general-ledger', 200);
    // Real ids: on MySQL/Postgres auto-increment values are not reset between tests.
    $period = AccountingPeriod::query()->value('id');
    $check('auditor', 'POST', "periods/{$period}/close", 403);
    $check('accountant', 'POST', "periods/{$period}/reopen", 403);
    $check('accountant', 'GET', 'health', 200);
});

it('never removes permissions an admin customised when seeding again', function (): void {
    $viewer = Role::findByName('viewer');
    $viewer->revokePermissionTo('reports.cash-flow.view');
    $viewer->givePermissionTo('audit-logs.view');

    // Simulate an upgrade that introduces a brand-new permission for the viewer role.
    config([
        'accounting.permissions' => array_merge(config('accounting.permissions'), ['reports.new-report.view']),
        'accounting.roles.viewer' => array_merge(config('accounting.roles.viewer'), ['reports.new-report.view']),
    ]);

    $this->seed(AccountingPermissionSeeder::class);
    $viewer = Role::findByName('viewer')->fresh('permissions');

    expect($viewer->hasPermissionTo('reports.cash-flow.view'))->toBeFalse()   // admin's removal kept
        ->and($viewer->hasPermissionTo('audit-logs.view'))->toBeTrue()        // admin's addition kept
        ->and($viewer->hasPermissionTo('reports.new-report.view'))->toBeTrue() // new permission granted
        ->and(Role::findByName('super-admin')->hasPermissionTo('reports.new-report.view'))->toBeTrue()
        ->and(Permission::query()->where('name', 'reports.new-report.view')->exists())->toBeTrue();
});
