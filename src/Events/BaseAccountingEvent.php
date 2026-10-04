<?php

namespace Alimarchal\LaravelChartOfAccounts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched only after the surrounding database transaction commits, so listeners never
 * see work that was rolled back.
 */
abstract class BaseAccountingEvent implements AccountingEvent
{
    use Dispatchable;
    use SerializesModels;
}
