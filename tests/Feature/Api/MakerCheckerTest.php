<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryApproved;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryPosted;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryRejected;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntrySubmitted;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

const MC_API = '/api/v1/accounting';

beforeEach(function (): void {
    config(['accounting.approvals.enabled' => true, 'accounting.approvals.threshold' => '1000']);
    $this->seed(AccountingDatabaseSeeder::class);

    $this->maker = User::factory()->create();
    $this->maker->assignRole('accountant');
    $this->checker = User::factory()->create();
    $this->checker->assignRole('approver');
});

function draftPayload(int|string $amount): array
{
    return [
        'entry_date' => now()->toDateString(),
        'reference' => 'PAY-'.$amount,
        'lines' => [
            ['account_code' => '5102', 'debit' => $amount, 'credit' => 0],
            ['account_code' => '1101', 'debit' => 0, 'credit' => $amount],
        ],
    ];
}

it('lets entries below the threshold post directly', function (): void {
    Sanctum::actingAs($this->maker);

    $this->postJson(MC_API.'/journal-entries', array_merge(draftPayload(999.99), ['auto_post' => true]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'posted');
});

it('blocks direct posting at or above the threshold', function (): void {
    Sanctum::actingAs($this->maker);

    $this->postJson(MC_API.'/journal-entries', array_merge(draftPayload(1000), ['auto_post' => true]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This journal entry requires approval: submit it for approval instead of posting it directly.');

    $id = $this->postJson(MC_API.'/journal-entries', draftPayload(5000))
        ->assertCreated()
        ->assertJsonPath('data.approval.required', true)
        ->json('data.id');

    $this->postJson(MC_API."/journal-entries/{$id}/post")->assertUnprocessable();
    $this->postJson(MC_API.'/journal-entries/simple', ['debit_account_code' => '5102', 'credit_account_code' => '1101', 'amount' => 5000])
        ->assertUnprocessable();
});

it('runs the maker → checker flow and posts on approval', function (): void {
    Event::fake([JournalEntrySubmitted::class, JournalEntryApproved::class, JournalEntryPosted::class]);

    Sanctum::actingAs($this->maker);
    $id = $this->postJson(MC_API.'/journal-entries', draftPayload(5000))->json('data.id');
    $this->postJson(MC_API."/journal-entries/{$id}/submit")
        ->assertOk()
        ->assertJsonPath('data.approval.status', 'pending')
        ->assertJsonPath('data.approval.submitted_by', $this->maker->id);

    // The maker cannot approve (no permission) and the queue shows the entry to checkers.
    $this->postJson(MC_API."/journal-entries/{$id}/approve")->assertForbidden();

    Sanctum::actingAs($this->checker);
    $this->getJson(MC_API.'/journal-entries?filter[approval_status]=pending')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->postJson(MC_API."/journal-entries/{$id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'posted')
        ->assertJsonPath('data.approval.status', 'approved')
        ->assertJsonPath('data.approval.approved_by', $this->checker->id)
        ->assertJsonPath('data.posted_by', $this->checker->id);

    Event::assertDispatched(JournalEntrySubmitted::class);
    Event::assertDispatched(JournalEntryApproved::class);
    Event::assertDispatched(JournalEntryPosted::class, fn ($event) => $event->journalEntry->id === $id);

    expect(AccountingAuditLog::query()->where('record_id', $id)->whereIn('action', ['JOURNAL_SUBMITTED', 'JOURNAL_APPROVED', 'JOURNAL_POSTED'])->count())->toBe(3);
});

it('never lets a user approve their own entry, even a super-admin', function (): void {
    $boss = User::factory()->create();
    $boss->assignRole('super-admin');
    Sanctum::actingAs($boss);

    $id = $this->postJson(MC_API.'/journal-entries', draftPayload(5000))->json('data.id');
    $this->postJson(MC_API."/journal-entries/{$id}/submit")->assertOk();

    $this->postJson(MC_API."/journal-entries/{$id}/approve")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You cannot approve or reject a journal entry you created or submitted.');

    expect(JournalEntry::query()->find($id)->status)->toBe('draft');
});

it('allows self-approval only when explicitly configured', function (): void {
    config(['accounting.approvals.allow_self_approval' => true]);
    $boss = User::factory()->create();
    $boss->assignRole('super-admin');
    Sanctum::actingAs($boss);

    $id = $this->postJson(MC_API.'/journal-entries', draftPayload(5000))->json('data.id');
    $this->postJson(MC_API."/journal-entries/{$id}/submit")->assertOk();
    $this->postJson(MC_API."/journal-entries/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'posted');
});

it('rejects with a reason, lets the maker fix and resubmit', function (): void {
    Event::fake([JournalEntryRejected::class]);

    Sanctum::actingAs($this->maker);
    $id = $this->postJson(MC_API.'/journal-entries', draftPayload(5000))->json('data.id');
    $this->postJson(MC_API."/journal-entries/{$id}/submit")->assertOk();

    Sanctum::actingAs($this->checker);
    $this->postJson(MC_API."/journal-entries/{$id}/reject")->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(MC_API."/journal-entries/{$id}/reject", ['reason' => 'Attach the invoice'])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.approval.status', 'rejected')
        ->assertJsonPath('data.approval.rejection_reason', 'Attach the invoice');
    Event::assertDispatched(JournalEntryRejected::class);

    Sanctum::actingAs($this->maker);
    $this->putJson(MC_API."/journal-entries/{$id}", draftPayload(4800))->assertOk()->assertJsonPath('data.approval.status', null);
    $this->postJson(MC_API."/journal-entries/{$id}/submit")->assertOk()->assertJsonPath('data.approval.status', 'pending');
});

it('clears an approval when a submitted draft is edited', function (): void {
    Sanctum::actingAs($this->maker);
    $id = $this->postJson(MC_API.'/journal-entries', draftPayload(5000))->json('data.id');
    $this->postJson(MC_API."/journal-entries/{$id}/submit")->assertOk();

    $this->putJson(MC_API."/journal-entries/{$id}", draftPayload(9000))->assertOk()->assertJsonPath('data.approval.status', null);

    Sanctum::actingAs($this->checker);
    $this->postJson(MC_API."/journal-entries/{$id}/approve")->assertUnprocessable();
});

it('validates entries at submission time', function (): void {
    Sanctum::actingAs($this->maker);
    $payload = draftPayload(5000);
    $payload['lines'][1]['credit'] = 4000;
    $id = $this->postJson(MC_API.'/journal-entries', $payload)->json('data.id');

    $this->postJson(MC_API."/journal-entries/{$id}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Journal entry is not balanced.');

    $small = $this->postJson(MC_API.'/journal-entries', draftPayload(10))->json('data.id');
    $this->postJson(MC_API."/journal-entries/{$small}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This journal entry does not require approval: post it directly.');
});

it('does not require approval for reversals and year-end closing entries', function (): void {
    config(['accounting.approvals.threshold' => '0']);
    $boss = User::factory()->create();
    $boss->assignRole('super-admin');
    Sanctum::actingAs($boss);

    $entry = $this->postJson(MC_API.'/journal-entries', draftPayload(100))->json('data.id');
    $this->postJson(MC_API."/journal-entries/{$entry}/submit")->assertOk();
    Sanctum::actingAs($this->checker);
    $this->postJson(MC_API."/journal-entries/{$entry}/approve")->assertOk();

    Sanctum::actingAs($boss);
    $this->postJson(MC_API."/journal-entries/{$entry}/reverse")->assertOk()->assertJsonPath('data.status', 'posted');

    $period = AccountingPeriod::query()->firstOrFail();
    $this->postJson(MC_API."/periods/{$period->id}/close-fiscal-year")->assertOk();
});

it('leaves behaviour unchanged when approvals are disabled', function (): void {
    config(['accounting.approvals.enabled' => false]);
    Sanctum::actingAs($this->maker);

    $id = $this->postJson(MC_API.'/journal-entries', array_merge(draftPayload(1000000), ['auto_post' => true]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'posted')
        ->json('data.id');

    $draft = $this->postJson(MC_API.'/journal-entries', draftPayload(5))->json('data.id');
    $this->postJson(MC_API."/journal-entries/{$draft}/submit")->assertUnprocessable();
});
