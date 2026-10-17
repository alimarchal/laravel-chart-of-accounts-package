<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Jobs\SendWhatsAppMessage;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\WhatsAppMessage;
use Alimarchal\LaravelChartOfAccounts\Services\PartyPaymentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyService;
use Alimarchal\LaravelChartOfAccounts\Services\WhatsAppService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\WhatsApp;
use Alimarchal\LaravelChartOfAccounts\Support\WhatsApp\PhoneNumber;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    config([
        'accounting.whatsapp.enabled' => true, 'accounting.whatsapp.mode' => 'fake', 'accounting.whatsapp.token' => 'EAAG-test-token',
        'accounting.whatsapp.phone_number_id' => '1055550000', 'accounting.whatsapp.verify_token' => 'my-verify-word',
        'accounting.whatsapp.app_secret' => 'app-secret', 'accounting.whatsapp.api_version' => 'v25.0', 'accounting.whatsapp.default_country_code' => '92',
        'accounting.whatsapp.notifications.receipt' => null,
    ]);
    $this->user = User::factory()->create();
    $this->user->assignRole('accountant');
    Sanctum::actingAs($this->user);
    $this->live = function (): void {
        config(['accounting.whatsapp.mode' => 'live']);
    };
    $this->ok = fn (string $id = 'wamid.HBgLTEST1') => Http::response(['messaging_product' => 'whatsapp', 'contacts' => [['input' => '923001234567', 'wa_id' => '923001234567']], 'messages' => [['id' => $id]]], 200);
    $this->signed = fn (array $payload, string $secret = 'app-secret') => ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', json_encode($payload), $secret)];
    $this->statusEvent = fn (string $wamid, string $status, array $extra = []) => ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
        'messaging_product' => 'whatsapp', 'metadata' => ['display_phone_number' => '15550000000', 'phone_number_id' => '1055550000'],
        'statuses' => [['id' => $wamid, 'status' => $status, 'timestamp' => (string) now()->timestamp, 'recipient_id' => '923001234567', ...$extra]],
    ]]]]]];
});

// -- phone numbers ------------------------------------------------------------------------------------------------------------

it('turns every way of writing a number into digits with the country code', function (): void {
    expect(PhoneNumber::normalise('+92 300 1234567'))->toBe('923001234567')
        ->and(PhoneNumber::normalise('0300-1234567', '92'))->toBe('923001234567')
        ->and(PhoneNumber::normalise('00923001234567'))->toBe('923001234567')
        ->and(PhoneNumber::normalise('(+1) 415-555-0100'))->toBe('14155550100');
});

it('refuses numbers that cannot be a WhatsApp number', function (string $input): void {
    expect(fn () => PhoneNumber::normalise($input, '92'))->toThrow(AccountingException::class);
})->with(['', 'abc', '12345', '+0123456789012', '1234567890123456789']);

it('refuses a local number without a country code when none is configured', function (): void {
    config(['accounting.whatsapp.default_country_code' => '']);
    expect(fn () => PhoneNumber::normalise('0300 1234567'))->toThrow(AccountingException::class);
});

// -- the live request ---------------------------------------------------------------------------------------------------------

it('posts an approved template to the Graph API exactly as the Cloud API expects', function (): void {
    ($this->live)();
    Http::fake(['graph.facebook.com/*' => ($this->ok)()]);

    $message = WhatsApp::template('0300-1234567', 'fee_received', ['student' => 'Ali Khan', 'amount' => 'Rs 5,000', 'receipt' => 'RV-1', 'date' => '10 Oct 2026']);

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->url() === 'https://graph.facebook.com/v25.0/1055550000/messages'
            && $request->hasHeader('Authorization', 'Bearer EAAG-test-token')
            && $body['messaging_product'] === 'whatsapp' && $body['to'] === '923001234567' && $body['type'] === 'template'
            && $body['template']['name'] === 'fee_received' && $body['template']['language'] === ['code' => 'en']
            && $body['template']['components'] === [['type' => 'body', 'parameters' => [
                ['type' => 'text', 'text' => 'Ali Khan'], ['type' => 'text', 'text' => 'Rs 5,000'], ['type' => 'text', 'text' => 'RV-1'], ['type' => 'text', 'text' => '10 Oct 2026'],
            ]]];
    });
    expect($message->status)->toBe('sent')->and($message->wamid)->toBe('wamid.HBgLTEST1')->and($message->to_number)->toBe('923001234567')
        ->and($message->mode)->toBe('live')->and($message->attempts)->toBe(1)->and($message->sent_at)->not->toBeNull();
});

it('sends free text and a template without parameters', function (): void {
    ($this->live)();
    Http::fake(['graph.facebook.com/*' => Http::sequence()->push(['messages' => [['id' => 'wamid.ONE']]])->push(['messages' => [['id' => 'wamid.TWO']]])]);

    WhatsApp::text('923001234567', 'Your receipt is ready.');
    WhatsApp::template('923001234567', 'hello_world');

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data())->values();
    expect($sent[0]['type'])->toBe('text')->and($sent[0]['text'])->toBe(['preview_url' => false, 'body' => 'Your receipt is ready.'])
        ->and($sent[1]['template'])->toBe(['name' => 'hello_world', 'language' => ['code' => 'en_US']]);          // no empty components
});

it('adds parameter_name for templates with named parameters', function (): void {
    ($this->live)();
    config(['accounting.whatsapp.templates.fee_named' => ['name' => 'fee_named', 'language' => 'en', 'format' => 'named', 'parameters' => ['student', 'amount']]]);
    Http::fake(['graph.facebook.com/*' => ($this->ok)()]);

    WhatsApp::template('923001234567', 'fee_named', ['amount' => '500', 'student' => 'Sara']);              // order of the keys does not matter

    Http::assertSent(fn ($request) => $request->data()['template']['components'][0]['parameters'] === [
        ['type' => 'text', 'parameter_name' => 'student', 'text' => 'Sara'], ['type' => 'text', 'parameter_name' => 'amount', 'text' => '500'],
    ]);
});

it('cleans template parameters the way WhatsApp requires', function (): void {
    ($this->live)();
    Http::fake(['graph.facebook.com/*' => ($this->ok)()]);

    WhatsApp::template('923001234567', 'fee_received', ["Ali\nKhan\t  Jr", 5000, '', str_repeat('x', 2000)]);

    Http::assertSent(function ($request): bool {
        $texts = array_column($request->data()['template']['components'][0]['parameters'], 'text');

        return $texts[0] === 'Ali Khan Jr' && $texts[1] === '5000' && $texts[2] === '-' && mb_strlen($texts[3]) === 1024;
    });
});

it('checks the parameters against the template before calling WhatsApp', function (): void {
    ($this->live)();
    Http::fake();

    expect(fn () => WhatsApp::template('923001234567', 'fee_received', ['only one']))->toThrow(AccountingException::class, 'takes 4 parameters')
        ->and(fn () => WhatsApp::template('923001234567', 'fee_received', ['student' => 'A', 'amount' => '1', 'receipt' => 'R', 'date' => 'D', 'extra' => 'x']))->toThrow(AccountingException::class, 'no parameter extra')
        ->and(fn () => WhatsApp::template('923001234567', 'fee_received', ['student' => 'A']))->toThrow(AccountingException::class, 'needs the parameter')
        ->and(fn () => WhatsApp::template('923001234567', 'Fee Received!', []))->toThrow(AccountingException::class, 'not a template name')
        ->and(fn () => WhatsApp::text('923001234567', '   '))->toThrow(AccountingException::class)
        ->and(fn () => WhatsApp::text('abc', 'hi'))->toThrow(AccountingException::class);
    Http::assertNothingSent();
    expect(WhatsAppMessage::query()->count())->toBe(0);
});

it('sends a template that is not registered in the config under its own name', function (): void {
    ($this->live)();
    Http::fake(['graph.facebook.com/*' => ($this->ok)()]);

    WhatsApp::template('923001234567', 'exam_result', ['Ali', '85%'], 'ur');

    Http::assertSent(fn ($request) => $request->data()['template']['name'] === 'exam_result' && $request->data()['template']['language']['code'] === 'ur'
        && count($request->data()['template']['components'][0]['parameters']) === 2);
});

it('does nothing and says so when WhatsApp is switched off', function (): void {
    config(['accounting.whatsapp.enabled' => false]);
    Http::fake();

    expect(fn () => WhatsApp::text('923001234567', 'hi'))->toThrow(AccountingException::class, 'switched off')->and(WhatsApp::enabled())->toBeFalse();
    Http::assertNothingSent();
});

// -- errors -------------------------------------------------------------------------------------------------------------------

it('keeps a failed message with Meta\'s reason in words and throws it', function (): void {
    ($this->live)();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => '(#131047) Re-engagement message', 'type' => 'OAuthException', 'code' => 131047, 'fbtrace_id' => 'abc']], 400)]);

    expect(fn () => WhatsApp::text('923001234567', 'hi'))->toThrow(AccountingException::class, '24 hours');

    $record = WhatsAppMessage::query()->firstOrFail();
    expect($record->status)->toBe('failed')->and($record->error_code)->toBe('131047')->and($record->error)->toContain('approved template')->and($record->wamid)->toBeNull()
        ->and($record->failed_at)->not->toBeNull();
    Http::assertSentCount(1);                                                                              // a 4xx is never retried
});

it('names the problem for a recipient that is not on the test list and for an expired token', function (): void {
    ($this->live)();
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'Recipient phone number not in allowed list', 'code' => 131030]], 400)
        ->push(['error' => ['message' => 'Error validating access token', 'code' => 190]], 401)]);

    expect(fn () => WhatsApp::template('923001234567', 'hello_world'))->toThrow(AccountingException::class, 'allowed recipients')
        ->and(fn () => WhatsApp::template('923001234567', 'hello_world'))->toThrow(AccountingException::class, 'WHATSAPP_TOKEN');
});

it('retries a server error and a dropped connection a few times, then gives up', function (): void {
    ($this->live)();
    config(['accounting.whatsapp.retries' => 2]);
    Http::fake(['graph.facebook.com/*' => Http::sequence()->push('down', 503)->push('down', 503)->push(['messages' => [['id' => 'wamid.LATE']]], 200)
        ->push('down', 500)->push('down', 500)->push('down', 500)]);

    expect(WhatsApp::template('923001234567', 'hello_world')->wamid)->toBe('wamid.LATE');
    Http::assertSentCount(3);

    expect(fn () => WhatsApp::template('923001234567', 'hello_world'))->toThrow(AccountingException::class);
    expect(WhatsAppMessage::query()->latest('id')->first()->status)->toBe('failed');
});

it('never sends the token over plain http and says what is missing', function (): void {
    ($this->live)();
    Http::fake();
    config(['accounting.whatsapp.base_url' => 'http://graph.example.test']);
    expect(fn () => WhatsApp::template('923001234567', 'hello_world'))->toThrow(AccountingException::class, 'https');

    config(['accounting.whatsapp.base_url' => 'https://graph.facebook.com', 'accounting.whatsapp.token' => '']);
    expect(fn () => WhatsApp::template('923001234567', 'hello_world'))->toThrow(AccountingException::class, 'WHATSAPP_TOKEN');
    Http::assertNothingSent();
});

it('lets a failed message be sent again and does not send a delivered one twice', function (): void {
    ($this->live)();
    Http::fake(['graph.facebook.com/*' => Http::sequence()->push(['error' => ['message' => 'bad', 'code' => 131026]], 400)->push(['messages' => [['id' => 'wamid.SECOND']]], 200)]);
    try {
        WhatsApp::template('923001234567', 'hello_world');
    } catch (AccountingException) {
    }
    $record = WhatsAppMessage::query()->firstOrFail();

    $this->postJson("/api/v1/accounting/whatsapp/messages/{$record->id}/retry")->assertOk()->assertJsonPath('data.status', 'sent');
    $this->postJson("/api/v1/accounting/whatsapp/messages/{$record->id}/retry")->assertStatus(422);        // only a failed message
    expect($record->refresh()->wamid)->toBe('wamid.SECOND')->and($record->attempts)->toBe(2);
    Http::assertSentCount(2);
});

// -- sandbox (mode fake) ------------------------------------------------------------------------------------------------------

it('works without Meta in fake mode and keeps the 24-hour rule of the real thing', function (): void {
    Http::fake();

    $template = WhatsApp::template('0300-1234567', 'fee_received', ['Ali', '5,000', 'RV-1', '10 Oct 2026']);
    expect($template->status)->toBe('sent')->and($template->wamid)->toStartWith('wamid.FAKE')->and($template->mode)->toBe('fake');

    expect(fn () => WhatsApp::text('0300-1234567', 'Hello'))->toThrow(AccountingException::class, '24 hours');       // nobody wrote to us yet

    app(WhatsAppService::class)->receive(['from' => '923001234567', 'id' => 'wamid.IN1', 'type' => 'text', 'text' => ['body' => 'Salam'], 'timestamp' => (string) now()->timestamp]);
    expect(WhatsApp::text('0300-1234567', 'Hello')->status)->toBe('sent');                                          // the window is open now

    WhatsAppMessage::query()->where('direction', 'in')->update(['created_at' => now()->subHours(25)]);
    expect(fn () => WhatsApp::text('0300-1234567', 'Hello again'))->toThrow(AccountingException::class, '24 hours');  // and closed again
    Http::assertNothingSent();
});

it('moves a sandbox message on as WhatsApp would, through the API', function (): void {
    $message = WhatsApp::template('923001234567', 'hello_world');

    $this->postJson("/api/v1/accounting/whatsapp/messages/{$message->id}/simulate", ['status' => 'delivered'])->assertOk()->assertJsonPath('data.status', 'delivered');
    $this->postJson("/api/v1/accounting/whatsapp/messages/{$message->id}/simulate", ['status' => 'read'])->assertOk()->assertJsonPath('data.status', 'read');
    $this->postJson("/api/v1/accounting/whatsapp/messages/{$message->id}/simulate", ['status' => 'bogus'])->assertStatus(422);
    $this->postJson('/api/v1/accounting/whatsapp/simulate-reply', ['from' => '0300-1234567', 'text' => 'Thanks'])->assertCreated()->assertJsonPath('data.direction', 'in');
    $this->postJson('/api/v1/accounting/whatsapp/messages', ['to' => '0300-1234567', 'text' => 'You are welcome'])->assertCreated()->assertJsonPath('data.status', 'sent');

    ($this->live)();
    $this->postJson("/api/v1/accounting/whatsapp/messages/{$message->id}/simulate", ['status' => 'delivered'])->assertNotFound();      // never in live mode
    $this->postJson('/api/v1/accounting/whatsapp/simulate-reply', ['from' => '0300-1234567'])->assertNotFound();
});

// -- webhook ------------------------------------------------------------------------------------------------------------------

it('answers Meta\'s verification with the challenge, and only for your verify token', function (): void {
    $this->get('/api/v1/accounting/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=my-verify-word&hub.challenge=1158201444')
        ->assertOk()->assertSee('1158201444', false)->assertHeader('Content-Type');
    expect(strtolower((string) $this->get('/api/v1/accounting/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=my-verify-word&hub.challenge=1')->headers->get('Content-Type')))->toStartWith('text/plain');
    $this->get('/api/v1/accounting/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1')->assertForbidden();
    $this->get('/api/v1/accounting/whatsapp/webhook?hub.mode=unsubscribe&hub.verify_token=my-verify-word&hub.challenge=1')->assertForbidden();

    config(['accounting.whatsapp.verify_token' => '']);
    $this->get('/api/v1/accounting/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=&hub.challenge=1')->assertForbidden();             // an empty token is never a match
});

it('is not there at all while WhatsApp is switched off', function (): void {
    config(['accounting.whatsapp.enabled' => false]);

    $this->get('/api/v1/accounting/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=my-verify-word&hub.challenge=1')->assertNotFound();
    $this->postJson('/api/v1/accounting/whatsapp/webhook', [])->assertNotFound();
});

it('takes status updates only when the signature is Meta\'s', function (): void {
    ($this->live)();
    Http::fake(['graph.facebook.com/*' => ($this->ok)('wamid.ABC')]);
    $record = WhatsApp::template('923001234567', 'hello_world');
    $event = ($this->statusEvent)('wamid.ABC', 'delivered');
    $raw = json_encode($event);

    $this->call('POST', '/api/v1/accounting/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw)->assertForbidden();                                    // unsigned
    $this->call('POST', '/api/v1/accounting/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'other')], $raw)->assertForbidden();
    expect($record->refresh()->status)->toBe('sent');

    $this->call('POST', '/api/v1/accounting/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'app-secret')], $raw)->assertOk()->assertSee('EVENT_RECEIVED');
    expect($record->refresh()->status)->toBe('delivered')->and($record->delivered_at)->not->toBeNull();
});

it('refuses every webhook call in live mode when no app secret is set', function (): void {
    ($this->live)();
    config(['accounting.whatsapp.app_secret' => '']);

    $this->postJson('/api/v1/accounting/whatsapp/webhook', ($this->statusEvent)('wamid.X', 'read'))->assertForbidden();
});

it('never moves a message backwards and records why one failed', function (): void {
    $record = WhatsApp::template('923001234567', 'hello_world');
    $service = app(WhatsAppService::class);
    $status = fn (string $state, array $extra = []) => $service->applyStatus(['id' => $record->wamid, 'status' => $state, 'timestamp' => (string) now()->timestamp, ...$extra]);

    expect($status('read'))->toBeTrue()
        ->and($status('delivered'))->toBeFalse()                                                           // a late "delivered" after "read"
        ->and($status('sent'))->toBeFalse()
        ->and($status('failed', ['errors' => [['code' => 131026, 'title' => 'Undeliverable']]]))->toBeFalse()  // and a message that was read did not fail
        ->and($record->refresh()->status)->toBe('read')->and($record->read_at)->not->toBeNull();

    $other = WhatsApp::template('923001234567', 'hello_world');
    $service->applyStatus(['id' => $other->wamid, 'status' => 'failed', 'errors' => [['code' => 131026, 'title' => 'Message undeliverable', 'error_data' => ['details' => 'Not on WhatsApp']]]]);
    expect($other->refresh()->status)->toBe('failed')->and($other->error_code)->toBe('131026')->and($other->error)->toContain('not on WhatsApp');

    expect($service->applyStatus(['id' => 'wamid.UNKNOWN', 'status' => 'read']))->toBeFalse()->and($service->applyStatus(['id' => $other->wamid, 'status' => 'nonsense']))->toBeFalse();
});

it('stores a customer\'s reply once, however often Meta repeats it, and files it under the right company', function (): void {
    ($this->live)();
    $inbound = ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
        'metadata' => ['phone_number_id' => '1055550000'], 'contacts' => [['profile' => ['name' => 'Parent'], 'wa_id' => '923001234567']],
        'messages' => [['from' => '923001234567', 'id' => 'wamid.IN-ONCE', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'Thank you']]],
    ]]]]]];
    $send = fn () => $this->call('POST', '/api/v1/accounting/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', json_encode($inbound), 'app-secret')], json_encode($inbound));

    $send()->assertOk();
    $send()->assertOk();

    $stored = WhatsAppMessage::query()->where('direction', 'in')->get();
    expect($stored)->toHaveCount(1)->and($stored[0]->body)->toBe('Thank you')->and($stored[0]->to_number)->toBe('923001234567')->and($stored[0]->wamid)->toBe('wamid.IN-ONCE');
});

it('ignores events of another phone number of the same Meta app', function (): void {
    ($this->live)();
    $event = ($this->statusEvent)('wamid.NOPE', 'read');
    $event['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '999';
    $event['entry'][0]['changes'][0]['value']['messages'] = [['from' => '923009999999', 'id' => 'wamid.OTHER', 'type' => 'text', 'text' => ['body' => 'x']]];

    $this->postJson('/api/v1/accounting/whatsapp/webhook', $event, ($this->signed)($event))->assertOk();

    expect(WhatsAppMessage::query()->count())->toBe(0);
});

// -- queue --------------------------------------------------------------------------------------------------------------------

it('queues a message and lets the worker send it', function (): void {
    Queue::fake();

    $queued = WhatsApp::queueTemplate('0300-1234567', 'hello_world');

    Queue::assertPushed(SendWhatsAppMessage::class, fn ($job) => $job->messageId === $queued->id && $job->companyId === (int) $queued->company_id);
    expect($queued->status)->toBe('queued')->and($queued->wamid)->toBeNull();

    (new SendWhatsAppMessage($queued->id, (int) $queued->company_id))->handle(app(WhatsAppService::class), app(CurrentCompany::class));
    expect($queued->refresh()->status)->toBe('sent');

    (new SendWhatsAppMessage($queued->id, (int) $queued->company_id))->handle(app(WhatsAppService::class), app(CurrentCompany::class));
    expect($queued->refresh()->attempts)->toBe(1);                                                         // a sent message is not sent again
});

it('keeps a permanent failure of a queued message on its record without retrying', function (): void {
    $queued = WhatsApp::queueText('0300-1234567', 'free text to a stranger');

    (new SendWhatsAppMessage($queued->id, (int) $queued->company_id))->handle(app(WhatsAppService::class), app(CurrentCompany::class));

    expect($queued->refresh()->status)->toBe('failed')->and($queued->error_code)->toBe('131047');
});

// -- API ----------------------------------------------------------------------------------------------------------------------

it('sends and lists through the API, with filters', function (): void {
    $this->postJson('/api/v1/accounting/whatsapp/messages', ['to' => '0300-1234567', 'template' => 'fee_received', 'parameters' => ['student' => 'Ali', 'amount' => '500', 'receipt' => 'R1', 'date' => 'today']])
        ->assertCreated()->assertJsonPath('data.template', 'fee_received')->assertJsonPath('data.status', 'sent');
    $this->postJson('/api/v1/accounting/whatsapp/messages', ['to' => '0300-1234567', 'text' => 'stranger'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '24 hours'));
    $this->postJson('/api/v1/accounting/whatsapp/messages', ['to' => '0300-1234567'])->assertStatus(422);                                 // neither
    $this->postJson('/api/v1/accounting/whatsapp/messages', ['to' => '0300-1234567', 'text' => 'a', 'template' => 'b'])->assertStatus(422);  // both
    $this->postJson('/api/v1/accounting/whatsapp/messages', ['to' => 'nonsense', 'template' => 'hello_world'])->assertStatus(422);

    $ids = fn (string $query) => collect($this->getJson("/api/v1/accounting/whatsapp?{$query}")->assertOk()->json('data'))->pluck('id')->all();
    expect($ids(''))->toHaveCount(2)->and($ids('status=failed'))->toHaveCount(1)->and($ids('status=sent'))->toHaveCount(1)
        ->and($ids('template=fee_received'))->toHaveCount(1)->and($ids('search=24 hours'))->toHaveCount(1)->and($ids('number=300123'))->toHaveCount(2)
        ->and($ids('direction=in'))->toBe([]);
    $this->getJson('/api/v1/accounting/whatsapp?date_from=nope')->assertStatus(422);
    $this->getJson('/api/v1/accounting/whatsapp')->assertJsonPath('whatsapp.mode', 'fake')->assertJsonPath('whatsapp.ready', true)->assertJsonPath('templates.1.key', 'fee_received');
});

it('reports what is still missing for live mode', function (): void {
    ($this->live)();
    config(['accounting.whatsapp.token' => '', 'accounting.whatsapp.app_secret' => '']);

    $response = $this->getJson('/api/v1/accounting/whatsapp')->assertOk()->assertJsonPath('whatsapp.ready', false);
    $missing = collect($response->json('whatsapp.checks'))->filter(fn ($check) => $check['needed'] && ! $check['set'])->pluck('env')->all();

    expect($missing)->toEqualCanonicalizing(['WHATSAPP_TOKEN', 'WHATSAPP_APP_SECRET'])
        ->and($response->json('whatsapp.webhook_url'))->toContain('/api/v1/accounting/whatsapp/webhook');
});

it('lets only people with the permission see or send', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);

    $this->getJson('/api/v1/accounting/whatsapp')->assertOk();
    $this->postJson('/api/v1/accounting/whatsapp/messages', ['to' => '0300-1234567', 'template' => 'hello_world'])->assertForbidden();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/accounting/whatsapp')->assertForbidden();
});

it('is hidden when the whatsapp feature switch is off', function (): void {
    config(['accounting.features.disabled' => ['whatsapp']]);

    $this->getJson('/api/v1/accounting/whatsapp')->assertNotFound();
});

// -- the school fee example ---------------------------------------------------------------------------------------------------

it('tells the parent when a fee receipt is recorded, and never lets WhatsApp stop the receipt', function (): void {
    account('1103')->forceFill(['control_type' => 'receivables'])->save();
    config(['accounting.whatsapp.notifications.receipt' => 'fee_received']);
    $parties = app(PartyService::class);
    $parent = $parties->create($parties->validate(['type' => 'customer', 'code' => 'P001', 'name' => 'Ali Khan', 'phone' => '0300-1234567']));
    $noPhone = $parties->create($parties->validate(['type' => 'customer', 'code' => 'P002', 'name' => 'No Phone']));
    $payments = app(PartyPaymentService::class);
    $date = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->toDateString();
    $receipt = fn ($party) => $payments->create($payments->validate(['party_id' => $party->id, 'kind' => 'receipt', 'payment_date' => $date, 'amount' => 5000, 'account_id' => account('1101')->id]));

    $payment = $receipt($parent);
    $message = WhatsAppMessage::query()->firstOrFail();
    expect($message->template)->toBe('fee_received')->and($message->to_number)->toBe('923001234567')->and($message->status)->toBe('sent')
        ->and($message->related_type)->toBe($payment->getMorphClass())->and($message->related_id)->toBe($payment->id)
        ->and(json_decode($message->payload, true)['template']['components'][0]['parameters'][0]['text'])->toBe('Ali Khan')
        ->and(json_decode($message->payload, true)['template']['components'][0]['parameters'][1]['text'])->toBe('5,000.00')
        ->and(json_decode($message->payload, true)['template']['components'][0]['parameters'][2]['text'])->toBe($payment->number);

    $receipt($noPhone);                                                                                    // no phone number: no message, receipt saved
    expect(WhatsAppMessage::query()->count())->toBe(1);

    config(['accounting.whatsapp.mode' => 'live', 'accounting.whatsapp.token' => '']);                      // misconfigured: the receipt is still saved
    $saved = $receipt($parent);
    expect($saved->exists)->toBeTrue()->and(WhatsAppMessage::query()->latest('id')->first()->status)->toBe('failed');

    config(['accounting.whatsapp.enabled' => false]);                                                       // switched off: nothing recorded
    $before = WhatsAppMessage::query()->count();
    $receipt($parent);
    expect(WhatsAppMessage::query()->count())->toBe($before);
});

it('queues the receipt message when the queue is on', function (): void {
    Queue::fake();
    account('1103')->forceFill(['control_type' => 'receivables'])->save();
    config(['accounting.whatsapp.notifications.receipt' => 'fee_received', 'accounting.whatsapp.queue' => true]);
    $parties = app(PartyService::class);
    $parent = $parties->create($parties->validate(['type' => 'customer', 'code' => 'P001', 'name' => 'Ali Khan', 'phone' => '+923001234567']));
    $payments = app(PartyPaymentService::class);
    $payments->create($payments->validate(['party_id' => $parent->id, 'kind' => 'receipt', 'payment_date' => AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->toDateString(), 'amount' => 100, 'account_id' => account('1101')->id]));

    Queue::assertPushed(SendWhatsAppMessage::class);
    expect(WhatsAppMessage::query()->firstOrFail()->status)->toBe('queued');
});

// -- command ------------------------------------------------------------------------------------------------------------------

it('shows the setup and sends a test message from the command line', function (): void {
    $this->artisan('accounting:whatsapp', ['action' => 'status'])->assertExitCode(0)->expectsOutputToContain('fake');
    $this->artisan('accounting:whatsapp', ['action' => 'test', 'to' => '0300-1234567'])->assertExitCode(0)->expectsOutputToContain('wamid.FAKE');
    $this->artisan('accounting:whatsapp', ['action' => 'test', 'to' => '0300-1234567', '--text' => 'free text'])->assertExitCode(1);       // 24-hour rule
    $this->artisan('accounting:whatsapp', ['action' => 'templates'])->assertExitCode(0)->expectsOutputToContain('fee_received');

    expect(WhatsAppMessage::query()->count())->toBe(2);
});

// -- screens ------------------------------------------------------------------------------------------------------------------

it('shows the messages, the set-up checks and the templates on the React screen and sends from its form', function (): void {
    $this->withoutVite();
    $this->actingAs($this->user);
    WhatsApp::template('0300-1234567', 'hello_world');

    $this->get('/accounting/whatsapp?status=sent')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/whatsapp/index')->has('messages', 1)->where('whatsapp.mode', 'fake')->where('whatsapp.ready', true)->has('templates', 3)->where('filters.status', 'sent'));
    $this->get('/accounting/whatsapp?status=failed')->assertInertia(fn (AssertableInertia $page) => $page->has('messages', 0));
    $this->post('/accounting/whatsapp/messages', ['to' => '0300-1234567', 'text' => 'free text'])->assertRedirect()->assertSessionHas('error');
    $this->post('/accounting/whatsapp/messages', ['to' => '0300-1234567', 'template' => 'hello_world'])->assertRedirect()->assertSessionHas('success');
})->skip(fn () => config('accounting.ui_driver') === 'blade', 'React screens are not served with the Blade driver');

it('renders the Blade screen and sends from its form', function (): void {
    $this->withoutVite();
    $this->actingAs($this->user);
    WhatsApp::template('0300-1234567', 'hello_world');

    $this->get(route('accounting.whatsapp.index'))->assertOk()->assertSee('WhatsApp')->assertSee('WHATSAPP_TOKEN')->assertSee('Sandbox (mode fake)')->assertSee('hello_world')->assertSee('wamid.FAKE');
    $this->post(route('accounting.whatsapp.send'), ['to' => '0300-1234567', 'template' => 'fee_received', 'parameters' => ['student' => 'Ali', 'amount' => '1', 'receipt' => 'R', 'date' => 'D']])->assertRedirect()->assertSessionHas('success');

    expect(WhatsAppMessage::query()->count())->toBe(2);
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');

it('refuses unsigned webhook calls outside a local sandbox, even in fake mode', function (): void {
    config(['accounting.whatsapp.mode' => 'fake', 'accounting.whatsapp.app_secret' => '']);
    $raw = json_encode(['object' => 'whatsapp_business_account', 'entry' => []]);
    $post = fn () => $this->call('POST', '/api/v1/accounting/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw);

    $post()->assertOk();                                            // the testing application is a local sandbox

    app()['env'] = 'production';
    $post()->assertForbidden();                                     // a production application never takes an unsigned call
});
