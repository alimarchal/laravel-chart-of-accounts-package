<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Console\Concerns\RunsForCompany;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingHealthCheckService;
use Illuminate\Console\Command;

class AccountingVerifyCommand extends Command
{
    use RunsForCompany;

    protected $signature = 'accounting:verify {--company= : Company code or id (default: the default company)}';

    protected $description = 'Verify the accounting module installation.';

    public function handle(AccountingHealthCheckService $healthCheck): int
    {
        $this->useCompanyOption();

        $result = $healthCheck->check();

        foreach ($result as $key => $value) {
            $this->line($key.': '.json_encode($value));
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
