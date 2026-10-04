<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

class AccountingHealthCheckCommand extends AccountingVerifyCommand
{
    protected $signature = 'accounting:health-check {--company= : Company code or id (default: the default company)}';

    protected $description = 'Run accounting module health checks.';
}
