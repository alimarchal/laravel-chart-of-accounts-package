<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('manages voucher types from the Blade screen', function (): void {
    $year = now()->year;

    $this->get('/accounting/voucher-types')->assertSuccessful()
        ->assertSee('Cash Payment Voucher')->assertSee("JV-{$year}-00001")->assertSee('New voucher type');

    $this->post('/accounting/voucher-types', ['code' => 'dn', 'name' => 'Debit Note', 'prefix' => 'DN', 'format' => '{PREFIX}-{SEQ}', 'reset' => 'yearly', 'is_active' => '1'])
        ->assertSessionHasErrors('format');
    $this->post('/accounting/voucher-types', ['code' => 'dn', 'name' => 'Debit Note', 'prefix' => 'DN', 'format' => '{PREFIX}-{FY}-{SEQ:4}', 'reset' => 'yearly', 'is_active' => '1'])
        ->assertRedirect('/accounting/voucher-types')->assertSessionHas('success');

    $debitNote = VoucherType::query()->where('code', 'DN')->firstOrFail();
    $this->get("/accounting/voucher-types?edit={$debitNote->id}")->assertSee('Edit DN')->assertSee("DN-{$year}-0001");

    $this->put("/accounting/voucher-types/{$debitNote->id}", ['code' => 'DN', 'name' => 'Debit Note (purchases)', 'prefix' => 'DN', 'format' => '{PREFIX}-{FY}-{SEQ:4}', 'reset' => 'yearly', 'is_active' => '1'])
        ->assertSessionHas('success');
    expect($debitNote->fresh()->name)->toBe('Debit Note (purchases)');

    $this->delete("/accounting/voucher-types/{$debitNote->id}")->assertSessionHas('success');
    expect(VoucherType::query()->where('code', 'DN')->exists())->toBeFalse();
});

it('shows voucher numbers in the Blade journal list and entry page', function (): void {
    $year = now()->year;
    $posted = journal(['1101' => 10, '4101' => -10]);
    $draft = journal(['1101' => 5, '4101' => -5], post: false);

    $this->get('/accounting/journal-entries')->assertSee("JV-{$year}-00001")->assertSee("JV draft #{$draft->id}");
    $this->get("/accounting/journal-entries?filter[voucher_number]=JV-{$year}-00001")->assertSee("JV-{$year}-00001")->assertDontSee("draft #{$draft->id}");
    $this->get("/accounting/journal-entries/{$posted->id}")->assertSee("Journal Voucher JV-{$year}-00001");
});
