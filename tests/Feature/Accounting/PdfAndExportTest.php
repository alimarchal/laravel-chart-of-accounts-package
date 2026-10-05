<?php

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\ReportExport;
use Alimarchal\LaravelChartOfAccounts\Services\ReportExportService;
use Alimarchal\LaravelChartOfAccounts\Support\AmountInWords;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(AccountingDatabaseSeeder::class);

    $this->user = User::factory()->create(['name' => 'Nadia Accountant']);
    $this->user->assignRole('accountant');
    $this->actingAs($this->user);
    $this->withoutVite();

    // Capture what a PDF template receives (dompdf compresses the file itself).
    $this->captured = [];
    View::composer(['accounting::pdf.report', 'accounting::pdf.voucher'], function ($view): void {
        $this->captured[$view->getName()] = $view->getData();
    });
});

it('writes amounts in words, international and south asian', function (): void {
    expect(AmountInWords::convert('1250.50', 'PKR'))->toBe('PKR One Thousand Two Hundred Fifty and 50/100 Only')
        ->and(AmountInWords::convert('0'))->toBe('Zero Only')
        ->and(AmountInWords::convert('2500000', null, 'international'))->toBe('Two Million Five Hundred Thousand Only')
        ->and(AmountInWords::convert('2500000', null, 'south_asian'))->toBe('Twenty Five Lakh Only')
        ->and(AmountInWords::convert('123456789.05', 'Rs.', 'south_asian'))->toBe('Rs. Twelve Crore Thirty Four Lakh Fifty Six Thousand Seven Hundred Eighty Nine and 05/100 Only');
});

it('typesets report PDFs with the letterhead, filters and totals', function (): void {
    journal(['1101' => 1500, '4101' => -1500]);

    $response = $this->get('/accounting/reports/general-ledger/export/pdf?account_id='.account('1101')->id.'&date_from='.now()->startOfYear()->toDateString())
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');

    $data = $this->captured['accounting::pdf.report'];
    expect($data['title'])->toBe('General Ledger')
        ->and($data['company']['name'])->not->toBeEmpty()
        ->and($data['filters']['Account'])->toBe('1101 Cash In Hand')
        ->and($data['filters']['Period'])->toStartWith(now()->startOfYear()->toDateString())
        ->and($data['generatedBy'])->toBe('Nadia Accountant')
        ->and(collect($data['columns'])->firstWhere('key', 'debit')['numeric'])->toBeTrue()
        ->and(collect($data['columns'])->firstWhere('key', 'account_code')['numeric'])->toBeFalse()
        ->and($data['totals']['debit'])->toEqual(1500.0)
        // Printed columns: voucher number first; no internal ids, FX rate, empty or duplicate base columns.
        ->and($data['columns'][0]['key'])->toBe('voucher_number')
        ->and(collect($data['columns'])->pluck('key')->all())->not->toContain('journal_entry_id', 'account_id', 'company_id', 'fx_rate_to_base', 'base_debit', 'cost_center_code')
        ->and($data['rows']->first()['entry_date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

it('totals the trial balance debit and credit columns', function (): void {
    journal(['1101' => 40, '4101' => -40]);

    $this->get('/accounting/reports/trial-balance/export/pdf')->assertOk();

    expect($this->captured['accounting::pdf.report']['totals'])->toEqual(['total_debits' => 40.0, 'total_credits' => 40.0]);
});

it('falls back to the built-in renderer with the company in the header', function (): void {
    config(['accounting.pdf.engine' => 'builtin']);
    journal(['1101' => 10, '4101' => -10]);

    $pdf = $this->get('/accounting/reports/trial-balance/export/pdf')->assertOk()->getContent();

    expect($pdf)->toStartWith('%PDF-1.4')->toContain('Trial Balance')->toContain('page 1 of ')
        ->and($this->captured)->not->toHaveKey('accounting::pdf.report');
});

it('prints a voucher with amount in words, signatures and a draft watermark', function (): void {
    $entry = journal(['5104' => 1250.5, '1101' => -1250.5], post: false);
    $entry->forceFill(['source_document_type' => 'bill', 'source_document_number' => 'BILL-12'])->save();

    $this->get("/accounting/journal-entries/{$entry->id}/print")->assertOk()
        ->assertSee('Journal Voucher')->assertSee('Draft #'.$entry->id)
        ->assertSee('One Thousand Two Hundred Fifty and 50/100 Only')
        ->assertSee('Purchase bill BILL-12')
        ->assertSee('Prepared by')->assertSee('Approved by')->assertSee('Posted by')
        ->assertSee('DRAFT')
        ->assertSee('window.print()', false);

    app(PostJournalEntryAction::class)->execute($entry);
    $pdf = $this->get("/accounting/journal-entries/{$entry->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

    expect(substr($pdf->getContent(), 0, 5))->toBe('%PDF-')
        ->and($pdf->headers->get('content-disposition'))->toContain($entry->fresh()->voucher_number.'.pdf')
        ->and($this->captured['accounting::pdf.voucher']['watermark'])->toBeNull()
        ->and($this->captured['accounting::pdf.voucher']['entry']->poster->name)->toBe('Nadia Accountant');

    config(['accounting.pdf.engine' => 'builtin']);
    $this->get("/accounting/journal-entries/{$entry->id}/pdf")->assertStatus(501);
});

it('prepares large exports in the background and lets only their owner download them', function (): void {
    config(['accounting.export_max_rows.pdf' => 2]);
    journal(['1101' => 10, '4101' => -10]);
    journal(['1101' => 20, '4101' => -20]);

    $this->get('/accounting/reports/general-ledger/export/pdf')
        ->assertRedirect('/accounting/exports')
        ->assertSessionHas('success');

    $export = ReportExport::query()->sole();
    expect($export->status)->toBe('ready')     // the sync queue ran it already
        ->and($export->rows)->toBe(4)
        ->and($export->user_id)->toBe($this->user->id);
    Storage::disk('local')->assertExists($export->path);

    $this->get('/accounting/exports')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/exports/index')
        ->where('exports.0.status', 'ready')
        ->where('exports.0.title', 'General Ledger'));
    $this->get("/accounting/exports/{$export->id}/download")->assertOk()->assertDownload($export->filename());

    $other = User::factory()->create();
    $other->assignRole('accountant');
    $this->actingAs($other)->get("/accounting/exports/{$export->id}/download")->assertNotFound();

    // Small exports still download straight away; CSV always streams.
    config(['accounting.export_max_rows.pdf' => 2000]);
    $this->actingAs($this->user)->get('/accounting/reports/general-ledger/export/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get('/accounting/reports/general-ledger/export/csv')->assertOk();
});

it('queues large reports that are not paginated queries too', function (): void {
    config(['accounting.export_max_rows.pdf' => 1]);
    journal(['1101' => 10, '4101' => -10]);

    $this->get('/accounting/reports/trial-balance/export/pdf')->assertRedirect('/accounting/exports');
    expect(ReportExport::query()->sole())->status->toBe('ready')->report->toBe('trial-balance');

    config(['accounting.exports.queue_large' => false]);
    $this->get('/accounting/reports/trial-balance/export/pdf')->assertStatus(422);
});

it('queues exports over the API and fails them when the user lost the permission', function (): void {
    Sanctum::actingAs($this->user);
    journal(['1101' => 10, '4101' => -10]);

    $this->postJson('/api/v1/accounting/reports/trial-balance/exports/xlsx')->assertStatus(202)->assertJsonPath('data.report', 'trial-balance');
    $this->getJson('/api/v1/accounting/exports')->assertOk()->assertJsonPath('data.0.status', 'ready');

    // The job re-checks the permission when it runs.
    $export = ReportExport::query()->create(['user_id' => $this->user->id, 'report' => 'trial-balance', 'format' => 'pdf', 'status' => 'queued']);
    $this->user->roles()->detach();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app(ReportExportService::class)->generate($export->id);

    expect($export->fresh()->status)->toBe('failed')
        ->and($export->fresh()->error)->toBe('You are no longer allowed to run this report.');
});

it('prunes old exports with their files', function (): void {
    config(['accounting.export_max_rows.xlsx' => 1]);
    journal(['1101' => 10, '4101' => -10]);
    $this->get('/accounting/reports/general-ledger/export/xlsx')->assertRedirect();
    $export = ReportExport::query()->sole();
    $export->forceFill(['created_at' => now()->subDays(10)])->save();

    Artisan::call('accounting:prune-exports');

    expect(ReportExport::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($export->path);
});
