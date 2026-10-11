<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A WhatsApp message sent through the Cloud API (direction out) or a reply received on the webhook (direction in).
 *
 * @property int $id
 * @property string $direction out|in
 * @property string $to_number digits only, with the country code
 * @property string $type text|template|…
 * @property string|null $template
 * @property string|null $language
 * @property string|null $body
 * @property string|null $payload JSON sent to Meta
 * @property string|null $response what Meta answered
 * @property string|null $wamid Meta's message id
 * @property string $status queued|sent|delivered|read|failed|received
 * @property string|null $error_code
 * @property string|null $error
 * @property int $attempts
 * @property string $mode fake|live
 * @property string|null $related_type
 * @property int|null $related_id
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $delivered_at
 * @property CarbonInterface|null $read_at
 * @property CarbonInterface|null $failed_at
 */
class WhatsAppMessage extends Model
{
    use BelongsToCompany;

    /** How far a message got; a later status never replaces a further one (a "delivered" that arrives after "read" is ignored). */
    public const RANK = ['queued' => 0, 'failed' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    protected $table = 'accounting_whatsapp_messages';

    protected $fillable = [
        'direction', 'to_number', 'type', 'template', 'language', 'body', 'payload', 'response', 'wamid', 'status', 'error_code', 'error', 'attempts', 'mode',
        'related_type', 'related_id', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
