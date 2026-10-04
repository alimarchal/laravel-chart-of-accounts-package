<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Database\Seeder;

class AccountingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        // Never touch existing periods (re-seeding must not reopen a closed year), and never
        // create a period that overlaps one the user already defined.
        $overlaps = AccountingPeriod::query()
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($overlaps) {
            return;
        }

        AccountingPeriod::query()->create([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'name' => "Fiscal Year {$year}",
            'status' => 'open',
        ]);
    }
}
