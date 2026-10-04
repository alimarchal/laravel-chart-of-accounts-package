<?php

namespace Alimarchal\LaravelChartOfAccounts\Rules;

use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * A tax code can have only one rate starting on a given date. Compared with whereDate() so it works
 * regardless of how the driver stores DATE values.
 */
class UniqueTaxRateStart implements ValidationRule
{
    public function __construct(private readonly mixed $taxCodeId, private readonly mixed $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || strtotime((string) $value) === false) {
            return;
        }

        $exists = TaxRate::query()
            ->where('tax_code_id', $this->taxCodeId)
            ->whereDate('effective_from', Carbon::parse((string) $value)->toDateString())
            ->when($this->ignoreId, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('This tax code already has a rate starting on that date.');
        }
    }
}
