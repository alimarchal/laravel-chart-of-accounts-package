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

        $period = AccountingPeriod::query()
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->first();

        if ($period) {
            $period->update(['name' => "Fiscal Year {$year}", 'status' => 'open']);

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
