<?php

namespace Alimarchal\LaravelChartOfAccounts\Concerns;

use Spatie\Activitylog\LogOptions;

trait LogsAccountingActivity
{
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('accounting')
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
