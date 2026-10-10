<?php

/*
 * Prints the registered routes as JSON for docs/generate-route-catalog.py: boots the package in a Testbench application
 * with the UI driver given in ACCOUNTING_UI_DRIVER (inertia or blade).
 */

use Alimarchal\LaravelChartOfAccounts\LaravelChartOfAccountsServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

require dirname(__DIR__).'/vendor/autoload.php';

$providers = [PermissionServiceProvider::class, ActivitylogServiceProvider::class, SanctumServiceProvider::class, InertiaServiceProvider::class];

if (class_exists(LivewireServiceProvider::class)) {
    $providers[] = LivewireServiceProvider::class;
}

$providers[] = LaravelChartOfAccountsServiceProvider::class;

$driver = getenv('ACCOUNTING_UI_DRIVER') ?: 'inertia';
$_ENV['ACCOUNTING_UI_DRIVER'] = $_SERVER['ACCOUNTING_UI_DRIVER'] = $driver;
$_ENV['APP_KEY'] = $_SERVER['APP_KEY'] = 'base64:'.base64_encode(str_repeat('a', 32));

$app = Application::create(options: ['extra' => ['providers' => $providers, 'dont-discover' => ['*']]]);
$app->make(Kernel::class)->bootstrap();

$rows = [];

foreach (Route::getRoutes()->getRoutes() as $route) {
    $rows[] = ['method' => implode('|', $route->methods()), 'uri' => $route->uri(), 'name' => $route->getName(), 'action' => $route->getActionName(), 'middleware' => $route->gatherMiddleware()];
}

echo json_encode($rows);
