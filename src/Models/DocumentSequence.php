<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * The last number given for a document kind in a year (see DocumentNumberService).
 *
 * @property int $id
 * @property string $key
 * @property int $year
 * @property int $last_number
 */
class DocumentSequence extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_document_sequences';

    protected $fillable = ['key', 'year', 'last_number'];
}
