<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Support\Install\EnvFile;
use Alimarchal\LaravelChartOfAccounts\Support\Install\StarterKitMenu;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->project = sys_get_temp_dir().'/coa-project-'.uniqid();
    File::ensureDirectoryExists($this->project);
});

afterEach(fn () => File::deleteDirectory($this->project));

function writeFile(string $path, string $content): void
{
    File::ensureDirectoryExists(dirname($path));
    file_put_contents($path, $content);
}

const FLUX2_SIDEBAR = <<<'BLADE'
<flux:sidebar sticky collapsible="mobile">
    <flux:sidebar.nav>
        <flux:sidebar.group :heading="__('Platform')" class="grid">
            <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </flux:sidebar.item>
        </flux:sidebar.group>
    </flux:sidebar.nav>
    <flux:spacer />
    <flux:sidebar.nav>
        <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">{{ __('Repository') }}</flux:sidebar.item>
    </flux:sidebar.nav>
</flux:sidebar>
BLADE;

const FLUX1_SIDEBAR = <<<'BLADE'
<flux:navlist variant="outline">
    <flux:navlist.group :heading="__('Platform')" class="grid">
        <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
    </flux:navlist.group>
</flux:navlist>
BLADE;

const REACT_SIDEBAR = <<<'TSX'
import { BookOpen, Folder, LayoutGrid } from 'lucide-react';
import { dashboard } from '@/routes';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    { title: 'Repository', href: 'https://github.com/laravel/react-starter-kit', icon: Folder },
];
TSX;

it('adds the Accounting link next to Dashboard in the Livewire starter kit sidebar, once', function (string $source): void {
    writeFile($this->project.'/resources/views/layouts/app/sidebar.blade.php', $source);
    $menu = new StarterKitMenu;

    $first = $menu->apply('blade', $this->project, '/accounting', 'accounting.dashboard');
    $second = $menu->apply('blade', $this->project, '/accounting', 'accounting.dashboard');
    $result = file_get_contents($this->project.'/resources/views/layouts/app/sidebar.blade.php');

    expect($first[0]['status'])->toBe('added')->and($second[0]['status'])->toBe('present')
        ->and(substr_count($result, "route('accounting.dashboard')"))->toBe(1)
        ->and($result)->toContain('{{-- accounting:menu --}}')->toContain('icon="calculator"')->toContain("__('Accounting')")
        ->toContain("routeIs('accounting.*')")->toContain("route('dashboard')")                   // the Dashboard item itself is untouched
        ->and(substr_count($result, 'wire:navigate'))->toBe(1)                                    // the new item is an ordinary link
        ->and(strpos($result, "route('dashboard')"))->toBeLessThan(strpos($result, "route('accounting.dashboard')"));
})->with([
    'Flux 2 (sidebar.item)' => [FLUX2_SIDEBAR],
    'Flux 1 (navlist.item)' => [FLUX1_SIDEBAR],
]);

it('says so when the sidebar has no Dashboard item to copy', function (): void {
    writeFile($this->project.'/resources/views/layouts/app/sidebar.blade.php', '<div>my own menu</div>');

    $result = (new StarterKitMenu)->apply('blade', $this->project, '/accounting', 'accounting.dashboard');

    expect($result[0]['status'])->toBe('manual')
        ->and(file_get_contents($this->project.'/resources/views/layouts/app/sidebar.blade.php'))->toBe('<div>my own menu</div>');
});

it('adds the Accounting link to mainNavItems of the React starter kit, with its icon import', function (): void {
    writeFile($this->project.'/resources/js/components/app-sidebar.tsx', REACT_SIDEBAR);
    $menu = new StarterKitMenu;

    $first = $menu->apply('inertia', $this->project, '/settings-config', 'accounting.dashboard');
    $second = $menu->apply('inertia', $this->project, '/settings-config', 'accounting.dashboard');
    $result = file_get_contents($this->project.'/resources/js/components/app-sidebar.tsx');

    expect($first[0]['status'])->toBe('added')->and($second[0]['status'])->toBe('present')
        ->and($result)->toContain("{ title: 'Accounting', href: '/settings-config', icon: Calculator }")
        ->toContain("import { BookOpen, Calculator, Folder, LayoutGrid } from 'lucide-react';")
        ->and(substr_count($result, 'Calculator'))->toBe(2)
        ->and(strpos($result, 'icon: LayoutGrid'))->toBeLessThan(strpos($result, "title: 'Accounting'"))
        ->and(strpos($result, "title: 'Accounting'"))->toBeLessThan(strpos($result, 'const footerNavItems'));
});

it('leaves the other driver\'s files alone', function (): void {
    writeFile($this->project.'/resources/js/components/app-sidebar.tsx', REACT_SIDEBAR);
    writeFile($this->project.'/resources/views/layouts/app/sidebar.blade.php', FLUX2_SIDEBAR);

    expect((new StarterKitMenu)->apply('api', $this->project, '/accounting', 'accounting.dashboard'))->toBe([])
        ->and((new StarterKitMenu)->apply('blade', $this->project, '/accounting', 'accounting.dashboard'))->toHaveCount(1)
        ->and(file_get_contents($this->project.'/resources/js/components/app-sidebar.tsx'))->toBe(REACT_SIDEBAR);
});

it('adds the package screens to the Tailwind 4 sources, once', function (): void {
    writeFile($this->project.'/resources/css/app.css', "@import 'tailwindcss';\n\n@source '../views';\n@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';\n\n@theme {}\n");
    $menu = new StarterKitMenu;

    $first = $menu->tailwindSource($this->project);
    $second = $menu->tailwindSource($this->project);
    $css = file_get_contents($this->project.'/resources/css/app.css');

    expect($first)->toBe('added')->and($second)->toBe('present')
        ->and(substr_count($css, 'alimarchal/laravel-chart-of-accounts'))->toBe(1)
        ->and(strpos($css, 'Pagination'))->toBeLessThan(strpos($css, 'alimarchal'))
        ->and(strpos($css, 'alimarchal'))->toBeLessThan(strpos($css, '@theme'));

    writeFile($this->project.'/resources/css/app.css', "@tailwind base;\n");
    expect($menu->tailwindSource($this->project))->toBe('none');
});

it('changes a .env key in place, appends a missing one and keeps one line for a key written twice', function (): void {
    writeFile($this->project.'/.env', "APP_NAME=Laravel\nACCOUNTING_ROUTE_PREFIX=accounting      # comment\nACCOUNTING_BASE_CURRENCY=PKR\n# ACCOUNTING_UI_DRIVER=inertia\nACCOUNTING_ROUTE_PREFIX=settings\n");
    $env = new EnvFile($this->project.'/.env');

    $env->set(['ACCOUNTING_ROUTE_PREFIX' => 'settings-config', 'ACCOUNTING_UI_DRIVER' => 'blade', 'ACCOUNTING_MULTI_COMPANY' => true, 'ACCOUNTING_WEBHOOK_SECRET' => 'a b"c']);
    $content = file_get_contents($this->project.'/.env');

    expect(substr_count($content, 'ACCOUNTING_ROUTE_PREFIX='))->toBe(1)
        ->and($env->get('ACCOUNTING_ROUTE_PREFIX'))->toBe('settings-config')->and($env->get('ACCOUNTING_UI_DRIVER'))->toBe('blade')
        ->and($env->get('ACCOUNTING_MULTI_COMPANY'))->toBe('true')->and($env->get('ACCOUNTING_BASE_CURRENCY'))->toBe('PKR')
        ->and($env->get('ACCOUNTING_WEBHOOK_SECRET'))->toBe('a b"c')
        ->and($content)->toContain("APP_NAME=Laravel\n")->toContain('# ACCOUNTING_UI_DRIVER=inertia')                  // other lines untouched
        ->and((new EnvFile($this->project.'/missing'))->set(['A' => 'b']))->toBe([]);
});

// -- the installer's questions ------------------------------------------------------------------------------------------------

function setupProject(object $test, string $env = "APP_NAME=Laravel\n"): void
{
    writeFile($test->project.'/.env', $env);
    app()->useEnvironmentPath($test->project);
    app()->loadEnvironmentFrom('.env');
    $test->seed(AccountingDatabaseSeeder::class);
}

it('creates the administrator, gives it super-admin and writes the answers to .env', function (): void {
    setupProject($this, "APP_NAME=Laravel\nACCOUNTING_ROUTE_PREFIX=settings\n");

    $this->artisan('accounting:install', [
        '--setup-only' => true, '--no-interaction' => true, '--no-menu' => true, '--ui' => 'blade', '--currency' => 'usd', '--chart' => 'school', '--prefix' => 'settings-config',
        '--admin-email' => 'owner@school.test', '--admin-name' => 'Ali', '--admin-password' => 'secret-pass-1',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'owner@school.test')->firstOrFail();
    $env = new EnvFile($this->project.'/.env');

    expect($user->name)->toBe('Ali')->and(Hash::check('secret-pass-1', $user->password))->toBeTrue()->and($user->hasRole('super-admin'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($env->get('ACCOUNTING_UI_DRIVER'))->toBe('blade')->and($env->get('ACCOUNTING_BASE_CURRENCY'))->toBe('USD')
        ->and($env->get('ACCOUNTING_CHART_PRESET'))->toBe('school')->and($env->get('ACCOUNTING_ROUTE_PREFIX'))->toBe('settings-config')
        ->and(substr_count((string) file_get_contents($this->project.'/.env'), 'ACCOUNTING_ROUTE_PREFIX='))->toBe(1)
        ->and(config('accounting.route_prefix'))->toBe('settings-config');
});

it('gives super-admin to a user that already exists without touching the password', function (): void {
    setupProject($this);
    $existing = User::factory()->create(['email' => 'boss@school.test', 'password' => Hash::make('old-password')]);

    $this->artisan('accounting:install', ['--setup-only' => true, '--no-interaction' => true, '--no-menu' => true, '--no-env' => true, '--admin-email' => 'boss@school.test'])->assertSuccessful();

    expect($existing->refresh()->hasRole('super-admin'))->toBeTrue()->and(Hash::check('old-password', $existing->password))->toBeTrue()
        ->and(file_get_contents($this->project.'/.env'))->toBe("APP_NAME=Laravel\n");                    // --no-env: nothing written
});

it('does not create an administrator without a usable password or email', function (): void {
    setupProject($this);

    $this->artisan('accounting:install', ['--setup-only' => true, '--no-interaction' => true, '--no-menu' => true, '--admin-email' => 'new@school.test', '--admin-password' => 'short'])->assertSuccessful();
    $this->artisan('accounting:install', ['--setup-only' => true, '--no-interaction' => true, '--no-menu' => true, '--admin-email' => 'not-an-email', '--admin-password' => 'long-enough-1'])->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});

it('refuses a URL prefix that the starter kit uses and an unknown screen type or currency', function (): void {
    setupProject($this);

    $this->artisan('accounting:install', ['--setup-only' => true, '--no-interaction' => true, '--prefix' => 'settings'])->assertExitCode(2);
    $this->artisan('accounting:install', ['--setup-only' => true, '--no-interaction' => true, '--ui' => 'vue'])->assertExitCode(2);
    $this->artisan('accounting:install', ['--setup-only' => true, '--no-interaction' => true, '--currency' => 'RUPEES'])->assertExitCode(2);
    $this->artisan('accounting:install', ['--setup-only' => true, '--no-interaction' => true, '--chart' => 'nope'])->assertExitCode(2);
});

it('asks the setup questions and applies the answers', function (): void {
    setupProject($this);

    $this->artisan('accounting:install', ['--setup-only' => true, '--no-menu' => true])
        ->expectsChoice('Which screens do you want?', 'React / Inertia', ['Livewire / Blade', 'React / Inertia', 'REST API only'])
        ->expectsQuestion('Base currency code (ISO 4217, for example PKR, USD, EUR)', 'pkr')
        ->expectsChoice('Which chart of accounts should the company start with?', 'School chart', ['ERPNext standard chart (recommended)', 'General business chart', 'School chart'])
        ->expectsConfirmation('Will you keep the books of more than one company (multi-company)?', 'no')
        ->expectsConfirmation('Should entries be approved by a second person (maker-checker)?', 'yes')
        ->expectsQuestion('Entries from which amount (in the base currency) need approval? (0 = every entry)', '100000')
        ->expectsConfirmation('Do you want to send WhatsApp messages (receipts, reminders)? You can start in the sandbox without any Meta account.', 'yes')
        ->expectsConfirmation('Enable the REST API?', 'yes')
        ->expectsQuestion('URL of the accounting screens (https://your-app/<this>)', 'books')
        ->expectsQuestion('Email of the administrator (super-admin)', 'head@school.test')
        ->expectsQuestion('Name of the administrator', 'Head Teacher')
        ->expectsQuestion('Password for head@school.test (at least 8 characters)', 'a-good-password')
        ->expectsQuestion('Repeat the password', 'a-good-password')
        ->assertSuccessful();

    $env = new EnvFile($this->project.'/.env');

    expect($env->get('ACCOUNTING_UI_DRIVER'))->toBe('inertia')->and($env->get('ACCOUNTING_BASE_CURRENCY'))->toBe('PKR')
        ->and($env->get('ACCOUNTING_CHART_PRESET'))->toBe('school')->and($env->get('ACCOUNTING_MULTI_COMPANY'))->toBe('false')
        ->and($env->get('ACCOUNTING_APPROVALS_ENABLED'))->toBe('true')->and($env->get('ACCOUNTING_APPROVAL_THRESHOLD'))->toBe('100000')
        ->and($env->get('ACCOUNTING_WHATSAPP_ENABLED'))->toBe('true')->and($env->get('ACCOUNTING_WHATSAPP_MODE'))->toBe('fake')
        ->and($env->get('ACCOUNTING_API_ENABLED'))->toBe('true')->and($env->get('ACCOUNTING_ROUTE_PREFIX'))->toBe('books')
        ->and(User::query()->where('email', 'head@school.test')->firstOrFail()->hasRole('super-admin'))->toBeTrue();
});

it('rejects an administrator password that was typed differently the second time', function (): void {
    setupProject($this);

    $this->artisan('accounting:install', ['--setup-only' => true, '--no-menu' => true, '--no-env' => true, '--ui' => 'blade', '--currency' => 'PKR', '--chart' => 'general', '--prefix' => 'accounting'])
        ->expectsConfirmation('Will you keep the books of more than one company (multi-company)?', 'no')
        ->expectsConfirmation('Should entries be approved by a second person (maker-checker)?', 'no')
        ->expectsConfirmation('Do you want to send WhatsApp messages (receipts, reminders)? You can start in the sandbox without any Meta account.', 'no')
        ->expectsConfirmation('Enable the REST API?', 'no')
        ->expectsQuestion('Email of the administrator (super-admin)', 'head@school.test')
        ->expectsQuestion('Name of the administrator', 'Head')
        ->expectsQuestion('Password for head@school.test (at least 8 characters)', 'password-one')
        ->expectsQuestion('Repeat the password', 'password-two')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});
