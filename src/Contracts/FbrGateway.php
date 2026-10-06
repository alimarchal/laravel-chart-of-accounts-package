<?php

namespace Alimarchal\LaravelChartOfAccounts\Contracts;

/**
 * Sends one invoice payload to FBR and returns the outcome. Bind your own implementation to talk to the service in the
 * way your registration requires (accounting.fbr.mode = live uses the built-in HTTP gateway).
 */
interface FbrGateway
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{accepted: bool, invoice_number: string|null, response: string, error: string|null}
     */
    public function submit(array $payload): array;
}
