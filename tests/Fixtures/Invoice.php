<?php

namespace Alimarchal\LaravelChartOfAccounts\Tests\Fixtures;

use Alimarchal\LaravelChartOfAccounts\Concerns\HasJournalEntries;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasJournalEntries;

    protected $guarded = [];
}
