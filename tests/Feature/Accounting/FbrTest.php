<?php

use Alimarchal\LaravelChartOfAccounts\Contracts\FbrGateway;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Jobs\SubmitFbrInvoice;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\FbrSubmission;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Services\FbrService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyDocumentService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    account('1103')->forceFill(['control_type' => 'receivables'])->save();
    account('2101')->forceFill(['control_type' => 'payables'])->save();
    config(['accounting.fbr.enabled' => true, 'accounting.fbr.mode' => 'fake', 'accounting.fbr.seller' => ['ntn' => '1234567-8', 'business_name' => 'Acme Ltd', 'province' => 'Punjab', 'address' => 'Lahore'], 'accounting.fbr.defaults.hs_code' => '8517.1300']);
    $this->start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->invoice = function (string $kind = 'invoice', string $description = 'Phone', ?string $taxNumber = '7654321-0', bool $post = true): PartyDocument {
        $party = Party::query()->create(['type' => $kind === 'bill' ? 'supplier' : 'customer', 'code' => 'C'.random_int(100, 999999), 'name' => 'Customer', 'tax_number' => $taxNumber, 'address' => 'Karachi']);
        $gst = TaxCode::query()->firstOrCreate(['code' => 'GST18'], ['name' => 'GST 18%', 'kind' => 'output', 'tax_account_id' => account('2104')->id, 'is_active' => true]);
        TaxRate::query()->where('tax_code_id', $gst->id)->exists() || TaxRate::query()->create(['tax_code_id' => $gst->id, 'effective_from' => $this->start->copy()->subYear()->toDateString(), 'rate' => '18']);
        $service = app(PartyDocumentService::class);
        $document = $service->create($service->validate([
            'party_id' => $party->id, 'kind' => $kind, 'issue_date' => $this->start->toDateString(),
            'lines' => [['description' => $description, 'chart_of_account_id' => account($kind === 'bill' ? '5102' : '4101')->id, 'quantity' => 2, 'unit_price' => '500', 'tax_code_id' => $kind === 'bill' ? null : $gst->id]],
        ]));

        return $post ? $service->post($document) : $document;
    };
});

it('builds the digital invoice payload from the posted invoice', function (): void {
    $payload = app(FbrService::class)->payload(($this->invoice)());

    expect($payload['invoiceType'])->toBe('Sale Invoice')->and($payload['sellerNTNCNIC'])->toBe('1234567-8')->and($payload['buyerNTNCNIC'])->toBe('7654321-0')->and($payload['buyerRegistrationType'])->toBe('Registered')
        ->and($payload['items'][0])->toMatchArray(['hsCode' => '8517.1300', 'productDescription' => 'Phone', 'rate' => '18%', 'quantity' => 2.0, 'valueSalesExcludingST' => 1000.0, 'salesTaxApplicable' => 180.0, 'totalValues' => 1180.0]);
    expect(app(FbrService::class)->payload(($this->invoice)('invoice', 'Phone', null))['buyerRegistrationType'])->toBe('Unregistered');
});

it('submits an invoice once and keeps the number FBR gave it', function (): void {
    $document = ($this->invoice)();
    $submission = app(FbrService::class)->submit($document);

    expect($submission->status)->toBe('accepted')->and($submission->fbr_invoice_number)->toStartWith('FAKE-')->and($submission->attempts)->toBe(1)->and(json_decode($submission->request, true)['items'])->toHaveCount(1);
    expect(fn () => app(FbrService::class)->submit($document))->toThrow(AccountingException::class, 'already accepted');
});

it('records a refusal and lets it be retried', function (): void {
    $document = ($this->invoice)('invoice', 'REJECT');
    $failed = app(FbrService::class)->submit($document);
    expect($failed->status)->toBe('failed')->and($failed->error)->toContain('Rejected');

    $document->lines()->getQuery()->where('document_id', $document->id)->update(['description' => 'Fixed']);
    $retried = app(FbrService::class)->submit($document);
    expect($retried->status)->toBe('accepted')->and($retried->attempts)->toBe(2)->and(FbrSubmission::query()->count())->toBe(1);
});

it('refuses drafts, bills and a switched-off integration', function (): void {
    expect(fn () => app(FbrService::class)->submit(($this->invoice)('invoice', 'Phone', '1', false)))->toThrow(AccountingException::class, 'Post the document first')
        ->and(fn () => app(FbrService::class)->submit(($this->invoice)('bill')))->toThrow(AccountingException::class, 'Only sales');
    config(['accounting.fbr.enabled' => false]);
    expect(fn () => app(FbrService::class)->submit(($this->invoice)()))->toThrow(AccountingException::class, 'switched off');
});

it('talks to the configured URL in live mode and reads the invoice number from the response', function (): void {
    config(['accounting.fbr.mode' => 'live', 'accounting.fbr.url' => 'https://fbr.test/invoices', 'accounting.fbr.token' => 'secret']);
    Http::fake(['fbr.test/*' => Http::sequence()->push(['invoiceNumber' => '7000007DI1747119701593'], 200)->push(['validationResponse' => ['error' => 'Invalid HS code']], 400)]);

    expect(app(FbrService::class)->submit(($this->invoice)())->fbr_invoice_number)->toBe('7000007DI1747119701593');
    $failed = app(FbrService::class)->submit(($this->invoice)());
    expect($failed->status)->toBe('failed')->and($failed->error)->toBe('Invalid HS code');
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret') && $request['sellerNTNCNIC'] === '1234567-8');
});

it('uses a gateway bound by the application', function (): void {
    app()->instance(FbrGateway::class, new class implements FbrGateway
    {
        public function submit(array $payload): array
        {
            return ['accepted' => true, 'invoice_number' => 'CUSTOM-1', 'response' => '{}', 'error' => null];
        }
    });

    expect(app(FbrService::class)->submit(($this->invoice)())->fbr_invoice_number)->toBe('CUSTOM-1');
});

it('queues submission when an invoice is posted and auto-submit is on', function (): void {
    Bus::fake();
    config(['accounting.fbr.auto_submit' => true]);
    $document = ($this->invoice)();
    Bus::assertDispatched(SubmitFbrInvoice::class, fn ($job) => $job->documentId === $document->id);
    Bus::assertDispatchedTimes(SubmitFbrInvoice::class, 1);

    (new SubmitFbrInvoice($document->id, CurrentCompany::currentId()))->handle(app(FbrService::class));
    expect(FbrSubmission::query()->where('party_document_id', $document->id)->value('status'))->toBe('accepted');
});

it('serves the page and the API', function (): void {
    $sent = ($this->invoice)();
    ($this->invoice)();
    app(FbrService::class)->submit($sent);

    $this->get('/accounting/fbr')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/fbr/index')->has('documents', 2)->where('enabled', true));
    Sanctum::actingAs($this->accountant);
    $this->getJson('/api/v1/accounting/fbr?status=none')->assertOk()->assertJsonCount(1, 'data.documents');
    $pending = PartyDocument::query()->where('id', '<>', $sent->id)->firstOrFail();
    $this->postJson("/api/v1/accounting/fbr/documents/{$pending->id}/submit")->assertOk()->assertJsonPath('data.status', 'accepted');
    $this->postJson("/api/v1/accounting/fbr/documents/{$pending->id}/submit")->assertStatus(422);
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/accounting/fbr')->assertOk();
    $this->postJson("/api/v1/accounting/fbr/documents/{$sent->id}/submit")->assertForbidden();
});
