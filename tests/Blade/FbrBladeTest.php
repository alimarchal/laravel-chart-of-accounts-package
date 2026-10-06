<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\FbrSubmission;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Services\PartyDocumentService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('shows sales invoices and sends them to FBR in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
    account('1103')->forceFill(['control_type' => 'receivables'])->save();
    $service = app(PartyDocumentService::class);
    $party = Party::query()->create(['type' => 'customer', 'code' => 'C1', 'name' => 'Blade Customer', 'tax_number' => '123']);
    $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->toDateString();
    $document = $service->post($service->create($service->validate(['party_id' => $party->id, 'kind' => 'invoice', 'issue_date' => $start, 'lines' => [['description' => 'Goods', 'chart_of_account_id' => account('4101')->id, 'quantity' => 1, 'unit_price' => '250']]])));

    $this->get('/accounting/fbr')->assertOk()->assertSee('switched off');
    config(['accounting.fbr.enabled' => true, 'accounting.fbr.mode' => 'fake']);
    $this->get('/accounting')->assertSee('FBR Invoices');
    $this->get('/accounting/fbr')->assertOk()->assertSee('Test mode')->assertSee('Blade Customer')->assertSee('not sent');
    $this->post("/accounting/fbr/documents/{$document->id}/submit")->assertRedirect()->assertSessionHas('success');
    $this->get('/accounting/fbr')->assertSee('accepted')->assertSee('FAKE-');
    $this->post("/accounting/fbr/documents/{$document->id}/submit")->assertSessionHas('error');
    expect(FbrSubmission::query()->count())->toBe(1)->and(PartyDocument::query()->count())->toBe(1);
});
