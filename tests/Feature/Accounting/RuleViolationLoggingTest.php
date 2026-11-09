<?php

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Illuminate\Contracts\Debug\ExceptionHandler;

it('does not report a refused accounting rule', function (): void {
    expect(app(ExceptionHandler::class)->shouldReport(new AccountingException('Only draft journal entries can be posted.')))->toBeFalse();
});

it('still reports a real exception', function (): void {
    expect(app(ExceptionHandler::class)->shouldReport(new RuntimeException('Something broke.')))->toBeTrue();
});
