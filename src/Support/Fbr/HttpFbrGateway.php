<?php

namespace Alimarchal\LaravelChartOfAccounts\Support\Fbr;

use Alimarchal\LaravelChartOfAccounts\Contracts\FbrGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Posts the payload as JSON with a bearer token to accounting.fbr.url and reads the invoice number from the response
 * key accounting.fbr.invoice_number_key (dot notation). Any 2xx response whose invoice number is present is accepted;
 * a transport error or non-2xx response is a failure that can be retried.
 */
class HttpFbrGateway implements FbrGateway
{
    public function submit(array $payload): array
    {
        $url = (string) config('accounting.fbr.url');

        if ($url === '') {
            return ['accepted' => false, 'invoice_number' => null, 'response' => '', 'error' => 'accounting.fbr.url is not set.'];
        }

        try {
            $response = Http::timeout(max(1, (int) config('accounting.fbr.timeout', 20)))->withToken((string) config('accounting.fbr.token'))->acceptJson()->asJson()->post($url, $payload);
        } catch (ConnectionException $exception) {
            return ['accepted' => false, 'invoice_number' => null, 'response' => '', 'error' => 'Could not reach FBR: '.$exception->getMessage()];
        }

        $number = $response->successful() ? data_get($response->json(), (string) config('accounting.fbr.invoice_number_key', 'invoiceNumber')) : null;

        if ($response->successful() && is_string($number) && $number !== '') {
            return ['accepted' => true, 'invoice_number' => $number, 'response' => $response->body(), 'error' => null];
        }

        $message = data_get($response->json(), 'validationResponse.error') ?? data_get($response->json(), 'message') ?? ($response->successful() ? 'FBR did not return an invoice number.' : 'FBR answered HTTP '.$response->status().'.');

        return ['accepted' => false, 'invoice_number' => null, 'response' => $response->body(), 'error' => (string) $message];
    }
}
