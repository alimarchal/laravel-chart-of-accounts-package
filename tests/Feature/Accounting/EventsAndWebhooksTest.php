<?php

use Alimarchal\LaravelChartOfAccounts\Actions\CloseAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReopenAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReverseJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Actions\VoidJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Events\AccountingEvent;
use Alimarchal\LaravelChartOfAccounts\Events\AccountingPeriodClosed;
use Alimarchal\LaravelChartOfAccounts\Events\AccountingPeriodReopened;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryPosted;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryReversed;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryVoided;
use Alimarchal\LaravelChartOfAccounts\Jobs\DeliverAccountingWebhook;
use Alimarchal\LaravelChartOfAccounts\Listeners\SendAccountingWebhook;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
});

it('dispatches a domain event for every ledger action', function (): void {
    Event::fake([JournalEntryPosted::class, JournalEntryReversed::class, JournalEntryVoided::class, AccountingPeriodClosed::class, AccountingPeriodReopened::class]);

    $entry = journal(['5104' => 10, '1101' => -10]);
    app(ReverseJournalEntryAction::class)->execute($entry);
    app(VoidJournalEntryAction::class)->execute(journal(['5104' => 1, '1101' => -1], post: false));

    $period = AccountingPeriod::query()->firstOrFail();
    app(CloseAccountingPeriodAction::class)->execute($period);
    app(ReopenAccountingPeriodAction::class)->execute($period);

    Event::assertDispatchedTimes(JournalEntryPosted::class, 2); // entry + its reversal
    Event::assertDispatched(JournalEntryReversed::class, fn ($e) => $e->original->id === $entry->id);
    Event::assertDispatched(JournalEntryVoided::class);
    Event::assertDispatched(AccountingPeriodClosed::class);
    Event::assertDispatched(AccountingPeriodReopened::class);
});

it('does not dispatch events for work that is rolled back', function (): void {
    $received = [];
    Event::listen(JournalEntryPosted::class, function ($event) use (&$received): void {
        $received[] = $event->journalEntry->id;
    });

    try {
        DB::transaction(function (): void {
            journal(['5104' => 10, '1101' => -10]);

            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
    }

    expect($received)->toBe([]);

    journal(['5104' => 10, '1101' => -10]);
    expect($received)->toHaveCount(1);
});

it('lets apps listen to every accounting event through one interface', function (): void {
    $names = [];
    Event::listen(AccountingEvent::class, function (AccountingEvent $event) use (&$names): void {
        $names[] = $event->name();
    });

    app(ReverseJournalEntryAction::class)->execute(journal(['5104' => 10, '1101' => -10]));

    expect($names)->toBe(['journal_entry.posted', 'journal_entry.posted', 'journal_entry.reversed']);
});

it('delivers signed webhooks that receivers can verify', function (): void {
    config(['accounting.webhooks.urls' => ['https://erp.example.com/hooks/accounting'], 'accounting.webhooks.secret' => 's3cret']);
    Http::fake(['erp.example.com/*' => Http::response(null, 204)]);

    $entry = journal(['5104' => 10, '1101' => -10]);
    app(SendAccountingWebhook::class)->handle(new JournalEntryPosted($entry));

    Http::assertSent(function (Request $request) use ($entry): bool {
        [$t, $v1] = array_map(fn ($part) => explode('=', $part, 2)[1], explode(',', $request->header('X-Accounting-Signature')[0]));
        $expected = hash_hmac('sha256', $t.'.'.$request->body(), 's3cret');
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://erp.example.com/hooks/accounting'
            && $request->header('X-Accounting-Event')[0] === 'journal_entry.posted'
            && hash_equals($expected, $v1)
            && $body['event'] === 'journal_entry.posted'
            && $body['data']['journal_entry']['id'] === $entry->id;
    });
});

it('fails the delivery job (so the queue retries) when the receiver errors', function (): void {
    config(['accounting.webhooks.secret' => 'x']);
    Http::fake(['*' => Http::response('down', 503)]);

    (new DeliverAccountingWebhook('https://erp.example.com/hooks', 'journal_entry.posted', 'd-1', '{}'))->handle();
})->throws(RequestException::class);

it('queues one delivery job per endpoint with retries and backoff', function (): void {
    config(['accounting.webhooks.urls' => ['https://a.example.com/h', 'https://b.example.com/h']]);
    Queue::fake();

    app(SendAccountingWebhook::class)->handle(new JournalEntryPosted(journal(['5104' => 10, '1101' => -10])));

    Queue::assertPushed(DeliverAccountingWebhook::class, 2);
    $jobs = Queue::pushed(DeliverAccountingWebhook::class);
    $ids = $jobs->map(fn ($job) => json_decode($job->body, true)['id'])->unique();

    expect($jobs->pluck('url')->all())->toBe(['https://a.example.com/h', 'https://b.example.com/h'])
        ->and($ids)->toHaveCount(1)                                  // one event id for every endpoint
        ->and($jobs->pluck('deliveryId')->unique())->toHaveCount(2)  // one delivery id per endpoint
        ->and($jobs->first())->toBeInstanceOf(ShouldQueue::class)
        ->and($jobs->first()->tries)->toBe(5)
        ->and($jobs->first()->backoff())->toBe([10, 60, 300, 900, 3600]);
});

it('keeps the body and delivery id stable across retries, with a fresh signature', function (): void {
    config(['accounting.webhooks.secret' => 's3cret']);
    Http::fake(['*' => Http::response(null, 204)]);
    $job = new DeliverAccountingWebhook('https://erp.example.com/h', 'journal_entry.posted', 'delivery-1', '{"id":"evt-1"}');

    $job->handle();
    $this->travel(5)->minutes();
    $job->handle();

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]);
    expect($sent->map(fn (Request $r) => $r->body())->unique()->all())->toBe(['{"id":"evt-1"}'])
        ->and($sent->map(fn (Request $r) => $r->header('X-Accounting-Delivery')[0])->unique()->all())->toBe(['delivery-1'])
        ->and($sent->map(fn (Request $r) => $r->header('X-Accounting-Signature')[0])->unique())->toHaveCount(2);
});

it('never fails the request when a webhook cannot be delivered on the sync queue', function (): void {
    config(['accounting.webhooks.urls' => ['https://down.example.com/h'], 'queue.default' => 'sync']);
    Http::fake(['*' => Http::response('down', 503)]);
    $this->app['events']->listen(AccountingEvent::class, SendAccountingWebhook::class);

    $entry = journal(['5104' => 10, '1101' => -10]);

    expect($entry->fresh()->status)->toBe('posted');
    Http::assertSentCount(1);
});
