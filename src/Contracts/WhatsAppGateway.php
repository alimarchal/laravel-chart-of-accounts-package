<?php

namespace Alimarchal\LaravelChartOfAccounts\Contracts;

/**
 * Hands one message payload (the JSON body of WhatsApp Cloud API's POST /{phone-number-id}/messages) to WhatsApp and returns the outcome.
 * accounting.whatsapp.mode = live uses the built-in Cloud API gateway, fake keeps everything local; bind your own to use another provider.
 */
interface WhatsAppGateway
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, wamid: string|null, response: string, error_code: string|null, error: string|null, retryable: bool}
     */
    public function send(array $payload): array;
}
