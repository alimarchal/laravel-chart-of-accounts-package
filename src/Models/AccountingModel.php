<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\HasUserTracking;
use Alimarchal\LaravelChartOfAccounts\Concerns\LogsAccountingActivity;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

abstract class AccountingModel extends Model
{
    use HasUserTracking;
    use LogsAccountingActivity {
        LogsAccountingActivity::getActivitylogOptions insteadof LogsActivity;
    }
    use LogsActivity;
}
