<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $this->admin = User::factory()->create(['name' => 'Ayesha Admin']);
    $this->admin->assignRole('admin');
    $this->owner = User::factory()->create();
    $this->owner->assignRole('super-admin');

    Sanctum::actingAs($this->admin);
    $this->actingAs($this->admin);
    $this->audit = fn (string $action) => AccountingAuditLog::query()->where('action', $action)->latest('id')->first();
});

it('creates, lists and shows users over the API, audited', function (): void {
    // An admin cannot hand out "viewer": it holds report permissions the admin role lacks.
    $this->postJson('/api/v1/accounting/users', [
        'name' => 'Bilal Viewer', 'email' => 'bilal@example.com', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass', 'roles' => ['viewer'],
    ])->assertForbidden();

    Sanctum::actingAs($this->owner);
    $created = $this->postJson('/api/v1/accounting/users', [
        'name' => 'Bilal Viewer', 'email' => 'bilal@example.com', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass', 'roles' => ['viewer'],
    ])->assertCreated()->assertJsonPath('data.roles', ['viewer'])->json('data');

    $this->getJson('/api/v1/accounting/users?filter[role]=viewer')->assertOk()->assertJsonPath('data.0.email', 'bilal@example.com');
    $this->getJson("/api/v1/accounting/users/{$created['id']}")->assertOk()
        ->assertJsonPath('data.direct_permissions', [])
        ->assertJsonFragment(['reports.trial-balance.view']);

    $log = ($this->audit)('USER_CREATED');
    expect($log->record_id)->toBe($created['id'])
        ->and($log->user_id)->toBe($this->owner->id)
        ->and($log->new_values['roles'])->toBe(['viewer']);
});

it('changes roles over the API and records what was added and removed', function (): void {
    $user = User::factory()->create();
    $user->assignRole('viewer');

    $this->putJson("/api/v1/accounting/users/{$user->id}/roles", ['roles' => ['auditor']])->assertForbidden();   // auditor can read audit logs, admin cannot

    Sanctum::actingAs($this->owner);
    $this->putJson("/api/v1/accounting/users/{$user->id}/roles", ['roles' => ['auditor']])->assertOk()->assertJsonPath('data.roles', ['auditor']);

    $log = ($this->audit)('USER_ROLES_CHANGED');
    expect($log->old_values['roles'])->toBe(['viewer'])
        ->and($log->new_values['roles'])->toBe(['auditor'])
        ->and($log->metadata['added'])->toBe(['auditor'])
        ->and($log->metadata['removed'])->toBe(['viewer'])
        ->and($log->metadata['actor_id'])->toBe($this->owner->id);
});

it('stops privilege escalation through the API', function (): void {
    $user = User::factory()->create();

    // super-admin is only for super-admins; nor can an admin grant what they do not hold.
    $this->putJson("/api/v1/accounting/users/{$user->id}/roles", ['roles' => ['super-admin']])->assertForbidden();
    $this->putJson("/api/v1/accounting/users/{$this->admin->id}/roles", ['roles' => ['admin', 'accountant']])->assertForbidden();
    $this->putJson("/api/v1/accounting/users/{$user->id}/permissions", ['permissions' => ['journal-entries.post']])->assertForbidden();
    $this->postJson('/api/v1/accounting/roles', ['name' => 'poster', 'permissions' => ['journal-entries.post']])->assertForbidden();
    $this->putJson("/api/v1/accounting/users/{$this->owner->id}", ['name' => 'Taken over'])->assertForbidden();
    $this->deleteJson("/api/v1/accounting/users/{$this->admin->id}")->assertForbidden();   // not yourself

    // What the admin holds, the admin may grant.
    $this->putJson("/api/v1/accounting/users/{$user->id}/permissions", ['permissions' => ['reports.trial-balance.view']])
        ->assertOk()->assertJsonPath('data.direct_permissions', ['reports.trial-balance.view']);
    expect(($this->audit)('USER_PERMISSIONS_CHANGED')->metadata['added'])->toBe(['reports.trial-balance.view']);

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/accounting/users')->assertForbidden();
    $this->getJson('/api/v1/accounting/roles')->assertForbidden();
});

it('manages roles over the API, audited, and protects super-admin', function (): void {
    $role = $this->postJson('/api/v1/accounting/roles', ['name' => 'report-reader', 'permissions' => ['reports.trial-balance.view', 'reports.balance-sheet.view']])
        ->assertCreated()->json('data');

    $this->putJson("/api/v1/accounting/roles/{$role['id']}", ['permissions' => ['reports.trial-balance.view']])->assertOk()
        ->assertJsonPath('data.permissions', ['reports.trial-balance.view']);
    expect(($this->audit)('ROLE_UPDATED')->metadata['removed'])->toBe(['reports.balance-sheet.view']);

    $this->getJson('/api/v1/accounting/roles')->assertOk()->assertJsonFragment(['name' => 'report-reader', 'permissions_count' => 1]);
    $this->getJson('/api/v1/accounting/permissions')->assertOk()->assertJsonPath('data.voucher-types', ['voucher-types.create', 'voucher-types.delete', 'voucher-types.update', 'voucher-types.view']);

    $this->deleteJson("/api/v1/accounting/roles/{$role['id']}")->assertNoContent();
    expect(($this->audit)('ROLE_DELETED')->old_values['name'])->toBe('report-reader');

    Sanctum::actingAs($this->owner);
    $superAdmin = Role::findByName('super-admin', 'web');
    $this->putJson("/api/v1/accounting/roles/{$superAdmin->id}", ['name' => 'boss'])->assertUnprocessable();
    $this->deleteJson("/api/v1/accounting/roles/{$superAdmin->id}")->assertUnprocessable();
});

it('runs user and role management from the React screens', function (): void {
    $this->withoutVite();
    $this->actingAs($this->owner);

    $this->get('/accounting/users')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/users/index')
        ->where('users.total', 2));

    $this->post('/accounting/users', ['name' => 'Sana Clerk', 'email' => 'sana@example.com', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass', 'roles' => ['viewer']])
        ->assertRedirect();
    $sana = User::query()->where('email', 'sana@example.com')->firstOrFail();

    $this->get("/accounting/users/{$sana->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/users/form')
        ->where('user.roles', ['viewer'])
        ->where('user.role_permissions', fn ($permissions) => collect($permissions)->contains('reports.trial-balance.view'))
        ->has('permissionGroups.journal-entries'));

    // A refusal comes back as a message, not an error page.
    $this->actingAs($this->admin)->put("/accounting/users/{$sana->id}", ['name' => 'Sana Clerk', 'email' => 'sana@example.com', 'roles' => ['super-admin']])
        ->assertSessionHas('error', 'You cannot manage a user who holds permissions you do not hold.');
    $this->actingAs($this->owner)->put("/accounting/users/{$sana->id}", ['name' => 'Sana K.', 'email' => 'sana@example.com', 'roles' => ['viewer']])
        ->assertSessionHas('success', 'User updated.');
    expect($sana->fresh()->name)->toBe('Sana K.');

    $this->get('/accounting/roles')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/roles/index'));
    $this->post('/accounting/roles', ['name' => 'cashier', 'permissions' => ['journal-entries.view']])->assertRedirect();
    $cashier = Role::findByName('cashier', 'web');
    $this->get("/accounting/roles/{$cashier->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/roles/form')->where('role.permissions', ['journal-entries.view']));

    $this->delete("/accounting/users/{$sana->id}")->assertRedirect('/accounting/users');
    expect(User::query()->find($sana->id))->toBeNull();
});
