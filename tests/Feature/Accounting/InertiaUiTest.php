<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AccountingDatabaseSeeder::class);
});

it('shares permissions, flash messages and approval settings with every React page', function (): void {
    config(['accounting.approvals.enabled' => true, 'accounting.approvals.threshold' => '500']);
    $user = User::factory()->create();
    $user->assignRole('accountant');

    $this->actingAs($user)
        ->get('/accounting/journal-entries')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounting/journal-entries/index')
            ->where('accounting.permissions', fn ($permissions) => ($permissions['journal-entries.post'] ?? false) === true
                && ! isset($permissions['journal-entries.approve']))
            ->where('accounting.approvals.enabled', true)
            ->where('accounting.approvals.threshold', '500')
            ->has('accounting.flash'));
});

it('tells the journal page whether approval is required and runs the checker flow', function (): void {
    config(['accounting.approvals.enabled' => true, 'accounting.approvals.threshold' => '0']);
    $maker = User::factory()->create();
    $maker->assignRole('accountant');
    $checker = User::factory()->create();
    $checker->assignRole('approver');

    $this->actingAs($maker);
    $entry = journal(['5104' => 10, '1101' => -10], post: false);

    $this->get("/accounting/journal-entries/{$entry->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('requiresApproval', true)->where('entry.approval_status', null));

    $this->post("/accounting/journal-entries/{$entry->id}/submit")->assertSessionHas('success');

    $this->actingAs($checker)
        ->post("/accounting/journal-entries/{$entry->id}/reject", ['reason' => 'Wrong account'])
        ->assertSessionHas('success');

    $this->get("/accounting/journal-entries/{$entry->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('entry.approval_status', 'rejected')->where('entry.rejection_reason', 'Wrong account'));
});

it('shows who created, submitted, approved and posted an entry', function (): void {
    config(['accounting.approvals.enabled' => true, 'accounting.approvals.threshold' => '0']);
    $maker = User::factory()->create(['name' => 'Maya Maker']);
    $maker->assignRole('accountant');
    $checker = User::factory()->create(['name' => 'Chen Checker']);
    $checker->assignRole('approver');

    $this->actingAs($maker);
    $entry = journal(['5104' => 10, '1101' => -10], post: false);
    $this->post("/accounting/journal-entries/{$entry->id}/submit");
    $this->actingAs($checker)->post("/accounting/journal-entries/{$entry->id}/approve");

    $this->get("/accounting/journal-entries/{$entry->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('trail.0.step', 'Created')
            ->where('trail.0.by', 'Maya Maker')
            ->where('trail', fn ($trail) => collect($trail)->pluck('step')->all() === ['Created', 'Submitted for approval', 'Approved', 'Posted']
                && collect($trail)->last()['by'] === 'Chen Checker')
            ->missing('entry.creator')
            ->missing('entry.approver'));
});
