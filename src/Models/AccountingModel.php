<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use App\Concerns\Accounting\HasUserTracking;
use App\Concerns\Accounting\LogsAccountingActivity;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

abstract class AccountingModel extends Model
{
    use HasUserTracking;
    use LogsAccountingActivity {
        LogsAccountingActivity::getActivitylogOptions insteadof LogsActivity;
    }
    use LogsActivity;
}
