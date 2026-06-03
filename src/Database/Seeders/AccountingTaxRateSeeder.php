<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Illuminate\Database\Seeder;

class AccountingTaxRateSeeder extends Seeder
{
    public function run(): void
    {
        $taxCode = TaxCode::query()->where('code', 'EXEMPT')->firstOrFail();
        $effectiveFrom = now()->startOfYear()->toDateString();

        $taxRate = TaxRate::query()
            ->where('tax_code_id', $taxCode->id)
            ->whereDate('effective_from', $effectiveFrom)
            ->first();

        if ($taxRate) {
            $taxRate->update(['rate' => 0, 'effective_to' => null, 'is_active' => true]);

            return;
        }

        TaxRate::query()->create([
            'tax_code_id' => $taxCode->id,
            'effective_from' => $effectiveFrom,
            'rate' => 0,
            'effective_to' => null,
            'is_active' => true,
        ]);
    }
}
