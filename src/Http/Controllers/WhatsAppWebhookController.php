<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * The address Meta calls (WhatsApp → Configuration → Webhook): once to verify it (GET), then for every status change of a message
 * you sent (sent, delivered, read, failed) and every reply from a customer (POST). No login: the calls are proven by the verify token
 * and by the X-Hub-Signature-256 header, an HMAC-SHA256 of the raw body made with your app secret.
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(private readonly WhatsAppService $whatsapp) {}

    /** GET: Meta sends hub.mode=subscribe, hub.verify_token and hub.challenge; answer with the challenge, as plain text, when the token is yours. */
    public function verify(Request $request): Response
    {
        abort_unless($this->whatsapp->enabled(), 404);

        // PHP turns the dots of "hub.mode" into underscores; read both so a proxy that keeps them still works.
        $mode = (string) ($request->query('hub_mode') ?? $request->query('hub.mode'));
        $token = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token'));
        $challenge = (string) ($request->query('hub_challenge') ?? $request->query('hub.challenge'));
        $expected = (string) config('accounting.whatsapp.verify_token');

        if ($mode !== 'subscribe' || $expected === '' || ! hash_equals($expected, $token)) {
            return response('Forbidden', 403);
        }

        return response($challenge, 200, ['Content-Type' => 'text/plain']);
    }

    /** POST: the events. Always answered 200 once the signature is right, so Meta does not repeat them. */
    public function receive(Request $request): Response
    {
        abort_unless($this->whatsapp->enabled(), 404);

        $secret = (string) config('accounting.whatsapp.app_secret');

        // A signature is mandatory, except for the local sandbox: no app secret, not the live gateway and a local/testing application.
        $unsignedAllowed = $secret === '' && $this->whatsapp->mode() !== 'live' && app()->environment(['local', 'testing']);

        if (! $unsignedAllowed) {
            $given = (string) $request->header('X-Hub-Signature-256');

            if ($secret === '' || ! str_starts_with($given, 'sha256=') || ! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $given)) {
                return response('Invalid signature', 403);
            }
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response('EVENT_RECEIVED', 200);
        }

        $ours = (string) config('accounting.whatsapp.phone_number_id');

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                if ($ours !== '' && isset($value['metadata']['phone_number_id']) && (string) $value['metadata']['phone_number_id'] !== $ours) {
                    continue;                                   // another number of the same app
                }

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $this->whatsapp->applyStatus((array) $status);
                }

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    $this->whatsapp->receive((array) $message);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }
}
