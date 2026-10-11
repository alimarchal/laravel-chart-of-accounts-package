<?php

namespace Alimarchal\LaravelChartOfAccounts\Support\WhatsApp;

use Alimarchal\LaravelChartOfAccounts\Contracts\WhatsAppGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Business Platform, Cloud API:
 *
 *   POST {base_url}/{api_version}/{phone_number_id}/messages
 *   Authorization: Bearer {access token}      Content-Type: application/json
 *   { "messaging_product": "whatsapp", "to": "923001234567", "type": "text" | "template", … }
 *
 * Success is HTTP 200 with { "messaging_product": "whatsapp", "contacts": [{ "input", "wa_id" }], "messages": [{ "id": "wamid.…" }] };
 * the id is only "accepted" — sent / delivered / read arrive later on the webhook. Failures are { "error": { "message", "type", "code",
 * "error_data": { "details" }, "fbtrace_id" } }. Only a dropped connection, HTTP 5xx and "too many requests" (429, error 130429 / 131056 /
 * 80007) are retried; a 4xx (a bad number, a template that does not match, the 24-hour window) never is.
 */
class HttpWhatsAppGateway implements WhatsAppGateway
{
    /** Meta error codes that are worth another try later (throughput and pair rate limits, temporary problems). */
    private const RETRYABLE_CODES = ['1', '2', '4', '17', '80007', '130429', '131016', '131048', '131056', '133004', '133016'];

    public function send(array $payload): array
    {
        $token = (string) config('accounting.whatsapp.token');
        $phoneNumberId = (string) config('accounting.whatsapp.phone_number_id');

        if ($token === '' || $phoneNumberId === '') {
            return ['ok' => false, 'wamid' => null, 'response' => '', 'error_code' => null, 'error' => 'WHATSAPP_TOKEN and WHATSAPP_PHONE_NUMBER_ID are not set.', 'retryable' => false];
        }

        $base = rtrim((string) config('accounting.whatsapp.base_url', 'https://graph.facebook.com'), '/');

        if (! str_starts_with(strtolower($base), 'https://')) {
            return ['ok' => false, 'wamid' => null, 'response' => '', 'error_code' => null, 'error' => 'accounting.whatsapp.base_url must be https://: the access token is never sent over plain http.', 'retryable' => false];
        }

        $url = $base.'/'.trim((string) config('accounting.whatsapp.api_version', 'v25.0'), '/').'/'.$phoneNumberId.'/messages';

        try {
            $response = Http::timeout(max(1, (int) config('accounting.whatsapp.timeout', 15)))
                ->retry(max(0, (int) config('accounting.whatsapp.retries', 2)) + 1, 500, fn ($exception, $request) => $this->retryable($exception), throw: false)
                ->withToken($token)->acceptJson()->asJson()->post($url, ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', ...$payload]);
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'wamid' => null, 'response' => '', 'error_code' => null, 'error' => 'Could not reach WhatsApp: '.$exception->getMessage(), 'retryable' => true];
        }

        $wamid = $response->successful() ? data_get($response->json(), 'messages.0.id') : null;

        if (is_string($wamid) && $wamid !== '') {
            return ['ok' => true, 'wamid' => $wamid, 'response' => $response->body(), 'error_code' => null, 'error' => null, 'retryable' => false];
        }

        $code = data_get($response->json(), 'error.code');
        $details = data_get($response->json(), 'error.error_data.details');
        $message = (string) (data_get($response->json(), 'error.message') ?? 'WhatsApp answered HTTP '.$response->status().'.');

        return [
            'ok' => false, 'wamid' => null, 'response' => $response->body(), 'error_code' => $code === null ? null : (string) $code,
            'error' => is_string($details) && $details !== '' ? "{$message} {$details}" : $message,
            'retryable' => $response->serverError() || $response->status() === 429 || in_array((string) $code, self::RETRYABLE_CODES, true),
        ];
    }

    private function retryable(mixed $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        $response = $exception->response ?? null;

        return $response instanceof Response && ($response->serverError() || $response->status() === 429);
    }
}
