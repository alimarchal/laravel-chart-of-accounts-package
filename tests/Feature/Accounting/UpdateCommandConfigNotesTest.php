<?php

use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingUpdateCommand;

it('tells an upgraded install to fix the api_middleware line of its published config', function (): void {
    $old = "'api_middleware' => array_values(array_filter(explode(',', env('ACCOUNTING_API_MIDDLEWARE', 'api,auth:sanctum')))),";

    expect(AccountingUpdateCommand::outdatedConfigNotes($old))->toHaveCount(1)->and(AccountingUpdateCommand::outdatedConfigNotes($old)[0])->toContain('(string) env(');
});

it('has nothing to say about the current package config or a missing file', function (): void {
    expect(AccountingUpdateCommand::outdatedConfigNotes((string) file_get_contents(__DIR__.'/../../../config/accounting.php')))->toBe([])
        ->and(AccountingUpdateCommand::outdatedConfigNotes(''))->toBe([]);
});
