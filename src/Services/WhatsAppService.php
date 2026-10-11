<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Contracts\WhatsAppGateway;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Jobs\SendWhatsAppMessage;
use Alimarchal\LaravelChartOfAccounts\Models\WhatsAppMessage;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\WhatsApp\FakeWhatsAppGateway;
use Alimarchal\LaravelChartOfAccounts\Support\WhatsApp\HttpWhatsAppGateway;
use Alimarchal\LaravelChartOfAccounts\Support\WhatsApp\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp messages through the Cloud API (or the local sandbox) and keeps a record of each: the text or the approved template
 * with its parameters, what Meta answered, and how far the message got — sent, delivered, read, or failed with Meta's reason. The
 * status changes arrive on the webhook (see WhatsAppWebhookController); this class applies them.
 *
 * Meta's rules this class keeps for you: a business-initiated message must be an approved template; free text only goes to someone who
 * wrote to you in the last 24 hours; template parameters cannot hold line breaks, tabs or runs of spaces; numbers are digits with the
 * country code.
 */
class WhatsAppService
{
    public function __construct(private readonly WhatsAppGateway $gateway) {}

    public static function gatewayFor(string $mode): WhatsAppGateway
    {
        return $mode === 'live' ? new HttpWhatsAppGateway : new FakeWhatsAppGateway;
    }

    public function enabled(): bool
    {
        return (bool) config('accounting.whatsapp.enabled', false);
    }

    public function mode(): string
    {
        return config('accounting.whatsapp.mode') === 'live' ? 'live' : 'fake';
    }

    /**
     * What is set and what is missing, for the screen and `accounting:whatsapp`.
     *
     * @return list<array{key: string, env: string, set: bool, needed: bool, what: string}>
     */
    public function checks(): array
    {
        $live = $this->mode() === 'live';
        $set = fn (string $key): bool => (string) config("accounting.whatsapp.{$key}") !== '';

        return [
            ['key' => 'enabled', 'env' => 'ACCOUNTING_WHATSAPP_ENABLED', 'set' => $this->enabled(), 'needed' => true, 'what' => 'Switch the module on'],
            ['key' => 'token', 'env' => 'WHATSAPP_TOKEN', 'set' => $set('token'), 'needed' => $live, 'what' => 'Access token of a system user (permanent token) with the whatsapp_business_messaging permission'],
            ['key' => 'phone_number_id', 'env' => 'WHATSAPP_PHONE_NUMBER_ID', 'set' => $set('phone_number_id'), 'needed' => $live, 'what' => 'ID of the sending phone number (WhatsApp Manager → API Setup)'],
            ['key' => 'business_account_id', 'env' => 'WHATSAPP_BUSINESS_ACCOUNT_ID', 'set' => $set('business_account_id'), 'needed' => false, 'what' => 'WhatsApp Business Account ID (to list or create templates)'],
            ['key' => 'verify_token', 'env' => 'WHATSAPP_VERIFY_TOKEN', 'set' => $set('verify_token'), 'needed' => $live, 'what' => 'A word you choose; Meta sends it back when you register the webhook'],
            ['key' => 'app_secret', 'env' => 'WHATSAPP_APP_SECRET', 'set' => $set('app_secret'), 'needed' => $live, 'what' => 'App secret (Meta app → Settings → Basic): proves a webhook call comes from Meta'],
        ];
    }

    /** The configured templates: key => name, language, ordered parameter names. */
    public function templates(): array
    {
        return array_map(fn ($definition, $key) => [
            'key' => (string) $key, 'name' => (string) ($definition['name'] ?? $key), 'language' => (string) ($definition['language'] ?? config('accounting.whatsapp.default_language', 'en')),
            'parameters' => array_values((array) ($definition['parameters'] ?? [])), 'format' => (string) ($definition['format'] ?? 'positional'), 'example' => $definition['example'] ?? null,
        ], (array) config('accounting.whatsapp.templates', []), array_keys((array) config('accounting.whatsapp.templates', [])));
    }

    /**
     * The templates of your WhatsApp Business Account as Meta knows them (name, language, status, category) — live mode only.
     *
     * @return list<array{name: string, language: string, status: string, category: string}>
     */
    public function remoteTemplates(): array
    {
        $account = (string) config('accounting.whatsapp.business_account_id');
        $token = (string) config('accounting.whatsapp.token');

        if ($this->mode() !== 'live' || $account === '' || $token === '') {
            throw new AccountingException('Listing templates needs mode live, WHATSAPP_TOKEN and WHATSAPP_BUSINESS_ACCOUNT_ID.');
        }

        $base = rtrim((string) config('accounting.whatsapp.base_url', 'https://graph.facebook.com'), '/');
        $response = Http::timeout(max(1, (int) config('accounting.whatsapp.timeout', 15)))->withToken($token)->acceptJson()
            ->get($base.'/'.trim((string) config('accounting.whatsapp.api_version', 'v25.0'), '/').'/'.$account.'/message_templates', ['fields' => 'name,language,status,category', 'limit' => 100]);

        if (! $response->successful()) {
            throw new AccountingException($this->explain((string) data_get($response->json(), 'error.code'), (string) data_get($response->json(), 'error.message', 'WhatsApp answered HTTP '.$response->status().'.')));
        }

        return collect((array) $response->json('data'))->map(fn ($row): array => ['name' => (string) ($row['name'] ?? ''), 'language' => (string) ($row['language'] ?? ''), 'status' => (string) ($row['status'] ?? ''), 'category' => (string) ($row['category'] ?? '')])->values()->all();
    }

    // -- sending ------------------------------------------------------------------------------------------------------------

    public function text(string $to, string $body, ?Model $related = null): WhatsAppMessage
    {
        return $this->deliver($this->prepareText($to, $body, $related), throw: true);
    }

    /**
     * @param  array<int|string, mixed>  $parameters  a list (positional {{1}} …) or name => value (the configured parameter names)
     * @param  list<array<string, mixed>>|null  $components  the raw "components" of the template object, for headers and buttons
     */
    public function template(string $to, string $template, array $parameters = [], ?string $language = null, ?Model $related = null, ?array $components = null): WhatsAppMessage
    {
        return $this->deliver($this->prepareTemplate($to, $template, $parameters, $language, $related, $components), throw: true);
    }

    /** The same, but through the queue: returns the queued record at once and the message is sent by a worker. */
    public function queueText(string $to, string $body, ?Model $related = null): WhatsAppMessage
    {
        return $this->dispatch($this->prepareText($to, $body, $related));
    }

    /**
     * @param  array<int|string, mixed>  $parameters
     * @param  list<array<string, mixed>>|null  $components
     */
    public function queueTemplate(string $to, string $template, array $parameters = [], ?string $language = null, ?Model $related = null, ?array $components = null): WhatsAppMessage
    {
        return $this->dispatch($this->prepareTemplate($to, $template, $parameters, $language, $related, $components));
    }

    /**
     * Sends (or re-sends) a prepared record. With $throw a failure is thrown as a message you can show; without it (the queue worker)
     * the failure is kept on the record and the return value says whether another attempt can help.
     */
    public function deliver(WhatsAppMessage $message, bool $throw = false): WhatsAppMessage
    {
        if ($message->status === 'sent' || $message->status === 'delivered' || $message->status === 'read') {
            return $message;
        }

        $message->forceFill(['attempts' => $message->attempts + 1])->save();
        $result = $this->gateway->send((array) json_decode((string) $message->payload, true));

        if ($result['ok']) {
            $message->forceFill(['status' => 'sent', 'wamid' => $result['wamid'], 'response' => $result['response'], 'error' => null, 'error_code' => null, 'sent_at' => now(), 'failed_at' => null])->save();

            return $message;
        }

        $friendly = $this->explain($result['error_code'], $result['error']);
        $message->forceFill(['status' => 'failed', 'response' => $result['response'], 'error_code' => $result['error_code'], 'error' => $friendly, 'failed_at' => now()])->save();
        Log::warning('WhatsApp message failed', ['id' => $message->id, 'to' => $message->to_number, 'code' => $result['error_code'], 'error' => $result['error']]);

        if ($throw) {
            throw new AccountingException($friendly);
        }

        $message->setAttribute('retryable', $result['retryable']);

        return $message;
    }

    // -- webhook ------------------------------------------------------------------------------------------------------------

    /**
     * A status update from the webhook: { id: wamid, status: sent|delivered|read|failed, timestamp, recipient_id, errors: [...] }.
     * A status never moves a message backwards (a late "delivered" after "read" is ignored).
     *
     * @param  array<string, mixed>  $status
     */
    public function applyStatus(array $status): bool
    {
        $wamid = (string) ($status['id'] ?? '');
        $new = (string) ($status['status'] ?? '');
        $message = $wamid === '' ? null : WhatsAppMessage::query()->withoutGlobalScopes()->where('wamid', $wamid)->first();

        if ($message === null || ! in_array($new, ['sent', 'delivered', 'read', 'failed'], true)) {
            return false;
        }

        $at = isset($status['timestamp']) && is_numeric($status['timestamp']) ? now()->setTimestamp((int) $status['timestamp']) : now();
        $current = WhatsAppMessage::RANK[$message->status] ?? 0;

        if ($new === 'failed') {
            if ($current >= 2) {
                return false;
            }

            $error = (array) (($status['errors'][0] ?? null) ?: []);
            $detail = data_get($error, 'error_data.details');
            $message->forceFill([
                'status' => 'failed', 'failed_at' => $at, 'error_code' => isset($error['code']) ? (string) $error['code'] : null,
                'error' => $this->explain(isset($error['code']) ? (string) $error['code'] : null, trim((string) ($error['message'] ?? $error['title'] ?? 'WhatsApp could not deliver the message.').' '.(is_string($detail) ? $detail : ''))),
            ])->save();

            return true;
        }

        if (WhatsAppMessage::RANK[$new] <= $current && $message->status !== 'failed') {
            return false;
        }

        $column = ['sent' => 'sent_at', 'delivered' => 'delivered_at', 'read' => 'read_at'][$new];
        $message->forceFill(['status' => $new, $column => $at, 'error' => null, 'error_code' => null, 'failed_at' => null])->save();

        return true;
    }

    /**
     * A reply from a customer: kept (it opens the 24-hour window for free text) and filed under the company that last wrote to that number.
     *
     * @param  array<string, mixed>  $message  one entry of value.messages[]
     */
    public function receive(array $message, ?string $name = null): ?WhatsAppMessage
    {
        $from = preg_replace('/\D+/', '', (string) ($message['from'] ?? ''));
        $wamid = (string) ($message['id'] ?? '');

        if ($from === '' || $wamid === '' || WhatsAppMessage::query()->withoutGlobalScopes()->where('wamid', $wamid)->exists()) {
            return null;                                       // Meta repeats a webhook until it is answered: never store twice
        }

        $companies = app(CurrentCompany::class);
        $last = WhatsAppMessage::query()->withoutGlobalScopes()->where('to_number', $from)->where('direction', 'out')->latest('id')->first();
        $company = $last !== null ? $last->company_id : $companies->default()->getKey();
        $type = (string) ($message['type'] ?? 'text');
        $body = $type === 'text' ? (string) data_get($message, 'text.body', '') : '['.$type.']'.(string) data_get($message, "{$type}.caption", '');

        return $companies->runAs((int) $company, fn () => WhatsAppMessage::query()->create([
            'direction' => 'in', 'to_number' => $from, 'type' => $type, 'body' => $body, 'wamid' => $wamid, 'status' => 'received', 'mode' => $this->mode(),
            'payload' => (string) json_encode($message), 'sent_at' => isset($message['timestamp']) && is_numeric($message['timestamp']) ? now()->setTimestamp((int) $message['timestamp']) : now(),
        ]));
    }

    // -- internals ----------------------------------------------------------------------------------------------------------

    private function prepareText(string $to, string $body, ?Model $related): WhatsAppMessage
    {
        $this->assertEnabled();
        $number = PhoneNumber::normalise($to);
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > 4096) {
            throw new AccountingException('A WhatsApp text has 1 to 4096 characters.');
        }

        return $this->record($number, 'text', null, null, $body, ['to' => $number, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $body]], $related);
    }

    /**
     * @param  array<int|string, mixed>  $parameters
     * @param  list<array<string, mixed>>|null  $components
     */
    private function prepareTemplate(string $to, string $template, array $parameters, ?string $language, ?Model $related, ?array $components): WhatsAppMessage
    {
        $this->assertEnabled();
        $number = PhoneNumber::normalise($to);
        $definition = (array) config("accounting.whatsapp.templates.{$template}", []);
        $name = (string) ($definition['name'] ?? $template);
        $language = (string) ($language ?? $definition['language'] ?? config('accounting.whatsapp.default_language', 'en'));

        if (! preg_match('/^[a-z0-9_]{1,512}$/', $name)) {
            throw new AccountingException("'{$name}' is not a template name: WhatsApp templates are lowercase letters, digits and underscores.");
        }

        $components ??= $this->bodyComponents($definition, $parameters);
        $payload = ['to' => $number, 'type' => 'template', 'template' => ['name' => $name, 'language' => ['code' => $language]]];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        return $this->record($number, 'template', $name, $language, $parameters === [] ? null : (string) json_encode($parameters, JSON_UNESCAPED_UNICODE), $payload, $related);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int|string, mixed>  $parameters
     * @return list<array<string, mixed>>
     */
    private function bodyComponents(array $definition, array $parameters): array
    {
        $names = array_values((array) ($definition['parameters'] ?? []));
        $named = ($definition['format'] ?? (array_is_list($parameters) ? 'positional' : 'named')) === 'named';

        if ($names !== [] && ! array_is_list($parameters)) {
            $unknown = array_diff(array_map('strval', array_keys($parameters)), $names);

            if ($unknown !== []) {
                throw new AccountingException('The template has no parameter '.implode(', ', $unknown).'; it takes: '.implode(', ', $names).'.');
            }

            $missing = array_diff($names, array_map('strval', array_keys($parameters)));

            if ($missing !== []) {
                throw new AccountingException('The template needs the parameter '.implode(', ', $missing).'.');
            }

            $parameters = collect($names)->mapWithKeys(fn (string $key) => [$key => $parameters[$key]])->all();
        } elseif ($names !== [] && count($parameters) !== count($names)) {
            throw new AccountingException('The template takes '.count($names).' parameters ('.implode(', ', $names).'), '.count($parameters).' were given.');
        }

        if ($parameters === []) {
            return [];
        }

        $items = [];

        foreach ($parameters as $key => $value) {
            $item = ['type' => 'text', 'text' => $this->parameterText($value)];

            if ($named && ! is_int($key)) {
                $item = ['type' => 'text', 'parameter_name' => (string) $key, 'text' => $item['text']];
            }

            $items[] = $item;
        }

        return [['type' => 'body', 'parameters' => $items]];
    }

    /** Template parameters cannot have line breaks, tabs or long runs of spaces, cannot be empty and are cut at 1024 characters. */
    private function parameterText(mixed $value): string
    {
        if (is_array($value) || is_object($value) && ! method_exists($value, '__toString')) {
            throw new AccountingException('A template parameter must be text or a number.');
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $text === '' ? '-' : mb_substr($text, 0, 1024);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $number, string $type, ?string $template, ?string $language, ?string $body, array $payload, ?Model $related): WhatsAppMessage
    {
        return WhatsAppMessage::query()->create([
            'direction' => 'out', 'to_number' => $number, 'type' => $type, 'template' => $template, 'language' => $language, 'body' => $body,
            'payload' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE), 'status' => 'queued', 'mode' => $this->mode(),
            'related_type' => $related?->getMorphClass(), 'related_id' => $related?->getKey(), 'created_by' => Auth::id(),
        ]);
    }

    private function dispatch(WhatsAppMessage $message): WhatsAppMessage
    {
        SendWhatsAppMessage::dispatch($message->getKey(), (int) $message->company_id);

        return $message;
    }

    private function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new AccountingException('WhatsApp is switched off: set ACCOUNTING_WHATSAPP_ENABLED=true.');
        }
    }

    /** Meta's error code in words a person can act on; the code and Meta's own text stay on the record. */
    private function explain(?string $code, ?string $meta): string
    {
        $words = [
            '131047' => 'More than 24 hours have passed since this person last wrote to you: free text is not allowed any more, send an approved template instead.',
            '131051' => 'WhatsApp does not support this kind of message.',
            '131026' => 'The message could not be delivered: the number is not on WhatsApp, or the person has not accepted WhatsApp\'s latest terms.',
            '131030' => 'The number is not in the list of allowed recipients (a test number only sends to the numbers you add in WhatsApp Manager).',
            '132000' => 'The number of parameters does not match the template.',
            '132001' => 'The template does not exist in this language (or is not approved yet).',
            '132005' => 'The translated text of the template is too long.',
            '132007' => 'The template text breaks a WhatsApp content policy.',
            '132012' => 'A template parameter has the wrong format.',
            '132015' => 'The template is paused because of its quality: use another template.',
            '132016' => 'The template is disabled: use another template.',
            '132018' => 'A template parameter has a line break, tab or too many spaces.',
            '130429' => 'WhatsApp is limiting the sending rate: the message is retried later.',
            '131056' => 'Too many messages to this number in a short time: the message is retried later.',
            '80007' => 'The WhatsApp account hit its rate limit: the message is retried later.',
            '131048' => 'WhatsApp stopped the message as possible spam; wait before sending again.',
            '131042' => 'A payment or eligibility problem on the WhatsApp Business Account blocks sending: check billing in WhatsApp Manager.',
            '190' => 'The access token is invalid or has expired: create a new system-user token and set WHATSAPP_TOKEN.',
            '102' => 'The access token session has expired: create a new system-user token and set WHATSAPP_TOKEN.',
            '133010' => 'The sending phone number is not registered with the Cloud API.',
        ];
        if ($code === null || ! isset($words[$code])) {
            return (string) ($meta ?: 'WhatsApp did not accept the message.');
        }

        return $words[$code]." (WhatsApp error {$code})";
    }
}
