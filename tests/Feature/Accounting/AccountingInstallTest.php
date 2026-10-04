<?php

use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingInstallCommand;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingHealthCheckService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('seeds accounting data idempotently', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->seed(AccountingDatabaseSeeder::class);

    expect(AccountType::query()->count())->toBe(5)
        ->and(Currency::query()->where('is_base', true)->count())->toBe(1)
        ->and(ChartOfAccount::query()->where('account_code', '1101')->count())->toBe(1)
        ->and(ChartOfAccount::query()->where('account_code', '4101')->count())->toBe(1);
});

it('verifies a healthy accounting installation', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $result = app(AccountingHealthCheckService::class)->check();

    expect($result['ok'])->toBeTrue();
});

it('registers accounting artisan commands', function (): void {
    expect(Artisan::all())->toHaveKeys([
        'accounting:install',
        'accounting:seed',
        'accounting:sync-db-objects',
        'accounting:verify',
        'accounting:health-check',
        'accounting:rebuild-snapshots',
        'accounting:close-period',
        'accounting:open-period',
    ]);
});

it('tells the developer which traits the user model is missing', function (?string $model, array $expected, array $unexpected): void {
    config(['auth.providers.users.model' => $model ?? PlainInstallTestUser::class]);

    $command = app(AccountingInstallCommand::class);
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer = new BufferedOutput));
    (new ReflectionMethod($command, 'checkUserModel'))->invoke($command);

    expect($buffer->fetch())->toContain(...$expected)->not->toContain(...$unexpected);
})->with([
    'plain model' => [null, ['Spatie\\Permission\\Traits\\HasRoles', 'Laravel\\Sanctum\\HasApiTokens'], ['User model OK']],
    'complete model' => [User::class, ['User model OK'], ['Add these traits']],
]);

class PlainInstallTestUser extends Illuminate\Foundation\Auth\User {}
