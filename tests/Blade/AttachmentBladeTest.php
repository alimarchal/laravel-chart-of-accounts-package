<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\Attachment;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
});

it('attaches, lists, downloads and removes documents on the Blade entry page', function (): void {
    $entry = journal(['5104' => 30, '1101' => -30], post: false);

    $this->get("/accounting/journal-entries/{$entry->id}")->assertSee('Supporting documents (0)')->assertSee('No documents attached yet.');

    $this->post("/accounting/journal-entries/{$entry->id}/attachments", [
        'files' => [UploadedFile::fake()->createWithContent('receipt-31.pdf', 'receipt')],
        'description' => 'Petrol receipt',
    ])->assertSessionHas('success', 'File attached.');

    $attachment = Attachment::query()->firstOrFail();
    $this->get("/accounting/journal-entries/{$entry->id}")->assertSee('receipt-31.pdf')->assertSee('Petrol receipt')->assertSee('Remove');
    $this->get("/accounting/attachments/{$attachment->id}/download")->assertOk();

    $this->post("/accounting/journal-entries/{$entry->id}/attachments", ['files' => [UploadedFile::fake()->create('script.php', 1)]])
        ->assertSessionHasErrors('files.0');

    $this->delete("/accounting/attachments/{$attachment->id}")->assertSessionHas('success', 'Attachment removed.');
    expect(Attachment::query()->count())->toBe(0);
});
