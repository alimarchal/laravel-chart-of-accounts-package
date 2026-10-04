<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Database\Seeder;

class AccountingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        // The fiscal year containing today, starting in the company's fiscal_year_start_month
        // (January by default; e.g. July for a July–June year).
        $startMonth = max(1, min(12, (int) app(CurrentCompany::class)->get()->fiscal_year_start_month));
        $start = now()->startOfMonth()->month($startMonth)->startOfMonth();

        if ($start->isAfter(now())) {
            $start->subYear();
        }

        $end = $start->copy()->addYear()->subDay();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $name = $startMonth === 1 ? "Fiscal Year {$start->year}" : "Fiscal Year {$start->year}-{$end->format('y')}";

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
            'name' => $name,
            'status' => 'open',
        ]);
    }
}
