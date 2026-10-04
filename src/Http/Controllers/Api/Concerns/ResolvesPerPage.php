<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\Concerns;

trait ResolvesPerPage
{
    /**
     * ?per_page=N, clamped to 1…config('accounting.api_max_per_page') so a client cannot request huge pages.
     */
    protected function perPage(int $default = 15): int
    {
        $max = max(1, (int) config('accounting.api_max_per_page', 100));

        return max(1, min($max, (int) request()->integer('per_page', $default)));
    }
}
