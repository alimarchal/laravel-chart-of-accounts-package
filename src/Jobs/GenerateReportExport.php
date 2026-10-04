<?php

namespace Alimarchal\LaravelChartOfAccounts\Jobs;

use Alimarchal\LaravelChartOfAccounts\Services\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Builds a large report export in the background (see ReportExportService::generate()).
 */
class GenerateReportExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $exportId) {}

    public function handle(ReportExportService $exports): void
    {
        $exports->generate($this->exportId);
    }
}
