<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('blocks an admin from assigning the super-admin role to themselves', function (): void {
    $this->actingAs($this->admin)
        ->put("/settings/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'roles' => ['admin', 'super-admin'],
        ])
        ->assertForbidden();

    expect($this->admin->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('blocks an admin from creating a super-admin user', function (): void {
    $this->actingAs($this->admin)
        ->post('/settings/users', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['super-admin'],
        ])
        ->assertForbidden();

    expect(User::query()->where('email', 'mallory@example.com')->exists())->toBeFalse();
});

it('blocks assigning a role that grants permissions the actor does not hold', function (): void {
    // "accountant" can post journals; admin cannot, so admin may not hand that role out.
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->put("/settings/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['accountant'],
        ])
        ->assertForbidden();

    expect($target->fresh()->hasRole('accountant'))->toBeFalse();
});

it('blocks granting direct permissions the actor does not hold', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->post("/settings/users/{$target->id}/permissions", ['permissions' => ['journal-entries.post']])
        ->assertForbidden();

    expect($target->fresh()->hasDirectPermission('journal-entries.post'))->toBeFalse();
});

it('allows granting permissions the actor holds', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->post("/settings/users/{$target->id}/permissions", ['permissions' => ['journal-entries.view']])
        ->assertRedirect();

    expect($target->fresh()->hasDirectPermission('journal-entries.view'))->toBeTrue();
});

it('blocks an admin from taking over a super-admin account', function (): void {
    $root = User::factory()->create();
    $root->assignRole('super-admin');

    $this->actingAs($this->admin)
        ->put("/settings/users/{$root->id}", [
            'name' => $root->name,
            'email' => 'attacker@example.com',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
            'roles' => ['super-admin'],
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)->delete("/settings/users/{$root->id}")->assertForbidden();

    expect($root->fresh()->email)->not->toBe('attacker@example.com');
});

it('blocks a settings manager from widening a role beyond their own permissions', function (): void {
    // admin manages roles but holds no accounting write permissions.
    $role = Role::findByName('viewer');

    $this->actingAs($this->admin)
        ->put("/settings/roles/{$role->id}", [
            'name' => 'viewer',
            'permissions' => array_merge($role->permissions->pluck('name')->all(), ['journal-entries.post']),
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->put('/settings/roles/'.Role::findByName('super-admin')->id, ['name' => 'super-admin', 'permissions' => []])
        ->assertForbidden();

    // Managing a role whose permissions the admin does not hold (e.g. accountant) is refused too.
    $this->actingAs($this->admin)
        ->put('/settings/roles/'.Role::findByName('accountant')->id, ['name' => 'accountant', 'permissions' => []])
        ->assertForbidden();

    expect(Role::findByName('viewer')->hasPermissionTo('journal-entries.post'))->toBeFalse()
        ->and(Role::findByName('accountant')->permissions)->not->toBeEmpty();
});

it('lets users with accounting.manage-settings open role management', function (): void {
    $this->actingAs($this->admin)->get('/settings/roles')->assertSuccessful();

    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $this->actingAs($accountant)->get('/settings/roles')->assertForbidden();
});

it('lets a super-admin assign any role', function (): void {
    $root = User::factory()->create();
    $root->assignRole('super-admin');
    $target = User::factory()->create();

    $this->actingAs($root)
        ->put("/settings/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['accountant'],
        ])
        ->assertRedirect();

    expect($target->fresh()->hasRole('accountant'))->toBeTrue();
});
