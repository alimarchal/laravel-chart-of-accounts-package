<?php

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Attachment;
use Alimarchal\LaravelChartOfAccounts\Services\AttachmentService;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalApprovalService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(AccountingDatabaseSeeder::class);

    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    Sanctum::actingAs($this->accountant);

    $this->bill = fn (string $content = 'BILL 778 — total 120.00') => UploadedFile::fake()->createWithContent('bill-778.pdf', $content);
});

it('stores a document privately and serves it through the authorised route', function (): void {
    $entry = journal(['5104' => 120, '1101' => -120], post: false);

    $response = $this->post("/api/v1/accounting/journal-entries/{$entry->id}/attachments", ['file' => ($this->bill)(), 'description' => 'Supplier bill'], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.original_name', 'bill-778.pdf')
        ->assertJsonPath('data.description', 'Supplier bill')
        ->assertJsonPath('duplicates', []);

    $attachment = Attachment::query()->findOrFail($response->json('data.id'));
    expect($attachment->path)->toStartWith("accounting/{$entry->company_id}/".now()->format('Y/m').'/')
        ->and($attachment->sha256)->toBe(hash('sha256', 'BILL 778 — total 120.00'))
        ->and($response->json('data'))->not->toHaveKey('path');   // storage paths never leave the server
    Storage::disk('local')->assertExists($attachment->path);

    $this->getJson("/api/v1/accounting/journal-entries/{$entry->id}/attachments")->assertOk()->assertJsonCount(1, 'data');
    $download = $this->get("/api/v1/accounting/attachments/{$attachment->id}/download")->assertOk();
    expect($download->headers->get('content-disposition'))->toContain('bill-778.pdf')
        ->and($download->streamedContent())->toBe('BILL 778 — total 120.00');

    expect(AccountingAuditLog::query()->where('action', 'ATTACHMENT_ADDED')->where('record_id', $entry->id)->exists())->toBeTrue();
});

it('warns when the same file is already attached to another entry', function (): void {
    $first = journal(['5104' => 120, '1101' => -120]);
    $second = journal(['5104' => 120, '1101' => -120], post: false);

    app(AttachmentService::class)->attach($first, ($this->bill)());
    $result = app(AttachmentService::class)->attach($second, ($this->bill)());

    expect($result['duplicates'])->toBe([['id' => $first->id, 'voucher_number' => $first->voucher_number]]);
});

it('validates type and size', function (): void {
    config(['accounting.attachments.max_size_kb' => 1]);
    $entry = journal(['5104' => 10, '1101' => -10], post: false);

    $this->post("/api/v1/accounting/journal-entries/{$entry->id}/attachments", ['file' => UploadedFile::fake()->create('virus.exe', 1)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->post("/api/v1/accounting/journal-entries/{$entry->id}/attachments", ['file' => UploadedFile::fake()->create('big.pdf', 50)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('keeps the documents of posted entries as evidence', function (): void {
    $draft = journal(['5104' => 10, '1101' => -10], post: false);
    $onDraft = app(AttachmentService::class)->attach($draft, ($this->bill)('draft'))['attachment'];

    $this->deleteJson("/api/v1/accounting/attachments/{$onDraft->id}")->assertNoContent();
    Storage::disk('local')->assertMissing($onDraft->path);
    expect(AccountingAuditLog::query()->where('action', 'ATTACHMENT_REMOVED')->exists())->toBeTrue();

    $posted = journal(['5104' => 10, '1101' => -10]);
    $evidence = app(AttachmentService::class)->attach($posted, ($this->bill)('posted'))['attachment'];

    $this->deleteJson("/api/v1/accounting/attachments/{$evidence->id}")->assertUnprocessable()
        ->assertJsonPath('message', 'Attachments of posted or voided entries are kept as evidence and cannot be removed.');
    Storage::disk('local')->assertExists($evidence->path);
});

it('keeps each company\'s documents private and honours permissions', function (): void {
    $sub = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Subsidiary']);
    $subAttachment = app(CurrentCompany::class)->runAs($sub, function () {
        $entry = journal(['5104' => 10, '1101' => -10]);

        return app(AttachmentService::class)->attach($entry, ($this->bill)('sub'))['attachment'];
    });

    $this->get("/api/v1/accounting/attachments/{$subAttachment->id}/download")->assertNotFound();

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $entry = journal(['5104' => 10, '1101' => -10]);
    $this->post("/api/v1/accounting/journal-entries/{$entry->id}/attachments", ['file' => ($this->bill)()], ['Accept' => 'application/json'])->assertForbidden();
    $this->getJson("/api/v1/accounting/journal-entries/{$entry->id}/attachments")->assertOk();
});

it('requires a supporting document above the configured amount', function (): void {
    config(['accounting.attachments.required_above' => '1000']);

    $small = journal(['5104' => 999.99, '1101' => -999.99], post: false);
    expect(app(PostJournalEntryAction::class)->execute($small)->status)->toBe('posted');

    $large = journal(['5104' => 5000, '1101' => -5000], post: false);
    expect(fn () => app(PostJournalEntryAction::class)->execute($large))
        ->toThrow(AccountingException::class, 'Entries of 1000.00 or more need a supporting document');

    config(['accounting.approvals.enabled' => true, 'accounting.approvals.threshold' => '1000']);
    expect(fn () => app(JournalApprovalService::class)->submit($large))->toThrow(AccountingException::class, 'supporting document');

    app(AttachmentService::class)->attach($large, ($this->bill)());
    expect(app(JournalApprovalService::class)->submit($large->fresh())->approval_status)->toBe('pending');
});

it('attaches files from the React entry page', function (): void {
    $this->withoutVite();
    $first = journal(['5104' => 120, '1101' => -120]);
    app(AttachmentService::class)->attach($first, ($this->bill)());
    $entry = journal(['5104' => 120, '1101' => -120], post: false);

    $this->post("/accounting/journal-entries/{$entry->id}/attachments", ['files' => [($this->bill)(), UploadedFile::fake()->createWithContent('receipt.jpg', 'jpg-bytes')]])
        ->assertSessionHas('success', '2 files attached.')
        ->assertSessionHas('error', "The same file is already attached to {$first->voucher_number}: check this is not a duplicate bill or receipt.");

    $this->get("/accounting/journal-entries/{$entry->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->has('attachments', 2)
        ->where('attachments.0.original_name', 'bill-778.pdf')
        ->where('attachmentRules.max_size_kb', 10240));
});
