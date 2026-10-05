<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A report export prepared in the background.
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string $report
 * @property string $format
 * @property array<string, mixed>|null $filters
 * @property string $status queued|running|ready|failed
 * @property string|null $disk
 * @property string|null $path
 * @property int|null $rows
 * @property int|null $size
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 */
class ReportExport extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_report_exports';

    protected $guarded = ['id'];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'rows' => 'integer',
            'size' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function filename(): string
    {
        return $this->report.'-'.$this->created_at?->format('Ymd-His').'.'.$this->format;
    }
}
