<?php

namespace Alimarchal\LaravelChartOfAccounts\Support\WhatsApp;

use Alimarchal\LaravelChartOfAccounts\Contracts\WhatsAppGateway;
use Alimarchal\LaravelChartOfAccounts\Models\WhatsAppMessage;
use Illuminate\Support\Str;

/**
 * Sandbox without Meta: accepts every message locally with a made-up message id, and behaves like WhatsApp where it matters for
 * testing — free text to someone who has not written to you in the last 24 hours is refused (131047), a template is not.
 * The status of a fake message can be moved on from the screen or with `accounting:whatsapp simulate`.
 */
class FakeWhatsAppGateway implements WhatsAppGateway
{
    public function send(array $payload): array
    {
        $to = (string) ($payload['to'] ?? '');

        if (($payload['type'] ?? 'text') === 'text' && ! self::windowOpen($to)) {
            $error = ['code' => 131047, 'title' => 'Re-engagement message', 'message' => 'More than 24 hours have passed since the recipient last replied to the sender number.'];

            return ['ok' => false, 'wamid' => null, 'response' => (string) json_encode(['error' => $error]), 'error_code' => '131047', 'error' => $error['message'], 'retryable' => false];
        }

        $wamid = 'wamid.FAKE'.strtoupper(Str::random(24));

        return [
            'ok' => true, 'wamid' => $wamid, 'retryable' => false, 'error_code' => null, 'error' => null,
            'response' => (string) json_encode(['messaging_product' => 'whatsapp', 'contacts' => [['input' => $to, 'wa_id' => $to]], 'messages' => [['id' => $wamid]]]),
        ];
    }

    /** True when this number wrote to us in the last 24 hours (the "customer service window" in which free text is allowed). */
    public static function windowOpen(string $number): bool
    {
        return WhatsAppMessage::query()->where('direction', 'in')->where('to_number', $number)->where('created_at', '>=', now()->subDay())->exists();
    }
}
