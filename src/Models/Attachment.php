<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A supporting document (scanned bill, receipt, contract …) attached to an accounting record.
 *
 * @property int $id
 * @property int $company_id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property string $sha256
 * @property string|null $description
 * @property int|null $uploaded_by
 */
class Attachment extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_attachments';

    protected $fillable = ['description'];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo((string) config('auth.providers.users.model'), 'uploaded_by');
    }
}
