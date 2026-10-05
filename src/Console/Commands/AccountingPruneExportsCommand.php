<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Services\ReportExportService;
use Illuminate\Console\Command;

class AccountingPruneExportsCommand extends Command
{
    protected $signature = 'accounting:prune-exports';

    protected $description = 'Delete background report exports (and their files) older than accounting.exports.keep_days.';

    public function handle(ReportExportService $exports): int
    {
        $this->info('Deleted '.$exports->prune().' export(s).');

        return self::SUCCESS;
    }
}
