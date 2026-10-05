<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Services\ReportMappingService;
use Illuminate\Database\Seeder;

/**
 * The standard statement lines of the current company, and the recommended mapping of the seeded accounts
 * that still have their seeded names. Existing lines and mappings are left as they are.
 */
class AccountingReportLineSeeder extends Seeder
{
    public function run(): void
    {
        $mapping = app(ReportMappingService::class);
        $mapping->seedLines();
        $mapping->applyRecommended(onlySeeded: true);
    }
}
