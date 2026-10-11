<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\View\DynamicComponent;

beforeEach(function (): void {
    DynamicComponent::flushCache();                                          // it keeps the compiler of the first application of the test run
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->withoutVite();
    $this->actingAs($user);
    $this->host = sys_get_temp_dir().'/coa-host-'.uniqid();
    File::ensureDirectoryExists($this->host.'/layouts');
    File::ensureDirectoryExists($this->host.'/components/layouts');
    // The application's own views only: the test fixtures bring a Breeze-style app-layout component the starter kits do not have.
    app('view')->getFinder()->setPaths([$this->host]);
});

afterEach(function (): void {
    File::deleteDirectory($this->host);
    DynamicComponent::flushCache();
});

it('renders in the layout of the Livewire 4 starter kit (layouts::app) with the page title above the content', function (): void {
    file_put_contents($this->host.'/layouts/app.blade.php', '<html><body class="host-livewire-4"><main>{{ $slot }}</main></body></html>');
    View::addNamespace('layouts', $this->host.'/layouts');
    Blade::anonymousComponentPath($this->host.'/layouts', 'layouts');
    config(['livewire.component_layout' => 'layouts::app']);

    $html = $this->get('/accounting/chart-of-accounts')->assertOk()->getContent();

    expect($html)->toContain('host-livewire-4')->and($html)->toContain('Chart of Accounts')->and($html)->toContain('Cash In Hand')
        ->and($html)->toContain('select2');                                 // the styles and scripts of the page reach a layout without stacks
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');

it('renders in the layout of Livewire 3 (components.layouts.app)', function (): void {
    file_put_contents($this->host.'/components/layouts/app.blade.php', '<html><body class="host-livewire-3">{{ $slot }}</body></html>');
    config(['livewire.component_layout' => 'components.layouts.app']);

    $html = $this->get('/accounting/chart-of-accounts')->assertOk()->getContent();

    expect($html)->toContain('host-livewire-3')->and($html)->toContain('Cash In Hand');
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');

it('uses its own page layout when the application has a layouts.app view that Livewire does not render in', function (): void {
    file_put_contents($this->host.'/layouts/app.blade.php', '<html><body class="host-starter-kit">{{ $slot }}</body></html>');
    config(['livewire.component_layout' => '']);

    $html = $this->get('/accounting/chart-of-accounts')->assertOk()->getContent();

    expect($html)->toContain('<!DOCTYPE html>')->and($html)->toContain('Chart of Accounts')->and($html)->not->toContain('host-starter-kit');
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');

it('uses the layout component named in accounting.blade_layout', function (): void {
    file_put_contents($this->host.'/components/my-shell.blade.php', '<html><body class="host-shell">{{ $slot }}</body></html>');
    config(['accounting.blade_layout' => 'my-shell']);

    $html = $this->get('/accounting/chart-of-accounts')->assertOk()->getContent();

    expect($html)->toContain('host-shell')->and($html)->toContain('Cash In Hand');
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');

it('uses Breeze\'s app-layout component and its header slot when the application has it', function (): void {
    file_put_contents($this->host.'/components/app-layout.blade.php', '<html><body class="host-breeze"><h1>{{ $header ?? \'\' }}</h1>{{ $slot }}</body></html>');

    $html = $this->get('/accounting/chart-of-accounts')->assertOk()->getContent();

    expect($html)->toContain('host-breeze')->and($html)->toContain('Chart of Accounts');
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');
