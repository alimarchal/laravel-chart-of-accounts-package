<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\ReportExport;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
});

it('offers exports on the Blade report pages and lists background exports', function (): void {
    journal(['1101' => 10, '4101' => -10]);

    $this->get('/accounting/reports/trial-balance')->assertOk()
        ->assertSee('/accounting/reports/trial-balance/export/pdf', false)
        ->assertSee('/accounting/reports/trial-balance/export/xlsx', false)
        ->assertSee('My exports');

    config(['accounting.export_max_rows.pdf' => 1]);
    $this->get('/accounting/reports/trial-balance/export/pdf')->assertRedirect('/accounting/exports');

    $export = ReportExport::query()->sole();
    $this->get('/accounting/exports')->assertOk()->assertSee('Trial Balance')->assertSee('ready')
        ->assertSee("/accounting/exports/{$export->id}/download", false);
});

it('prints vouchers from the Blade entry page', function (): void {
    $entry = journal(['5104' => 75, '1101' => -75]);

    $this->get("/accounting/journal-entries/{$entry->id}")->assertOk()
        ->assertSee("/accounting/journal-entries/{$entry->id}/print", false)
        ->assertSee("/accounting/journal-entries/{$entry->id}/pdf", false);
    $this->get("/accounting/journal-entries/{$entry->id}/print")->assertOk()->assertSee('Seventy Five')->assertSee($entry->voucher_number);
});
