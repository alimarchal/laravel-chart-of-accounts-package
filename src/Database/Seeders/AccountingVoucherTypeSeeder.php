<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Illuminate\Database\Seeder;

/**
 * The standard voucher types of the current company. Existing types are left as they are.
 */
class AccountingVoucherTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (VoucherType::defaults() as $default) {
            VoucherType::query()->firstOrCreate(['code' => $default['code']], [
                ...$default,
                'format' => VoucherType::DEFAULT_FORMAT,
                'reset' => VoucherType::RESET_YEARLY,
                'is_active' => true,
                'is_system' => $default['code'] === VoucherType::DEFAULT_CODE,
            ]);
        }
    }
}
