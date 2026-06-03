<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Illuminate\Database\Seeder;

class AccountingCostCenterSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'GENERAL', 'name' => 'General Operations', 'type' => 'cost_center', 'is_active' => true],
            ['code' => 'ACADEMIC', 'name' => 'Academic Department', 'type' => 'cost_center', 'is_active' => true],
            ['code' => 'ADMIN', 'name' => 'Administration', 'type' => 'cost_center', 'is_active' => true],
        ] as $costCenter) {
            CostCenter::query()->updateOrCreate(['code' => $costCenter['code']], $costCenter);
        }
    }
}
