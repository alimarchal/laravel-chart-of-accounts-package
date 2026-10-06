<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * What happened when a sales invoice or credit note was sent to FBR.
 *
 * @property int $id
 * @property int $party_document_id
 * @property string $status pending|accepted|failed
 * @property string $mode fake|live
 * @property int $attempts
 * @property string|null $fbr_invoice_number
 * @property string|null $request JSON sent
 * @property string|null $response JSON or text received
 * @property string|null $error
 * @property CarbonInterface|null $submitted_at
 */
class FbrSubmission extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_fbr_submissions';

    protected $fillable = ['party_document_id', 'status', 'mode', 'attempts', 'fbr_invoice_number', 'request', 'response', 'error', 'submitted_at', 'created_by'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }
}
