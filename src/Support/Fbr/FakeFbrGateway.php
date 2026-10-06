<?php

namespace Alimarchal\LaravelChartOfAccounts\Support\Fbr;

use Alimarchal\LaravelChartOfAccounts\Contracts\FbrGateway;

/**
 * Accepts everything locally without calling FBR: for development, tests and demos. A payload whose first item has
 * the description "REJECT" is refused, so the failure path can be tried too.
 */
class FakeFbrGateway implements FbrGateway
{
    public function submit(array $payload): array
    {
        $items = $payload['items'] ?? [];

        if (($items[0]['productDescription'] ?? '') === 'REJECT') {
            return ['accepted' => false, 'invoice_number' => null, 'response' => json_encode(['validationResponse' => ['statusCode' => '01', 'error' => 'Rejected by the fake gateway']], JSON_THROW_ON_ERROR), 'error' => 'Rejected by the fake gateway'];
        }

        $number = 'FAKE-'.strtoupper(substr(hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 0, 12));

        return ['accepted' => true, 'invoice_number' => $number, 'response' => json_encode(['invoiceNumber' => $number, 'validationResponse' => ['statusCode' => '00', 'status' => 'Valid']], JSON_THROW_ON_ERROR), 'error' => null];
    }
}
