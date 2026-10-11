<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Support\Install\EnvFile;
use Alimarchal\LaravelChartOfAccounts\Support\Install\EnvQuestions;
use Alimarchal\LaravelChartOfAccounts\Support\Install\StarterKitMenu;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;

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

/**
 * The yes/no question of every optional topic, in the order they are asked: "no" unless the topic has a closure in $accept, which says "yes"
 * and adds the expectations of the questions that follow (they are asked right after the yes).
 *
 * @param  array<string, Closure(PendingCommand): mixed>  $accept
 */
function declineGroups(PendingCommand $command, array $accept = []): PendingCommand
{
    $gates = [
        'api' => 'Do you want to use the REST API? (needs Laravel Sanctum: "php artisan install:api")',
        'whatsapp' => 'Do you want to send WhatsApp messages (receipts, reminders)? You can start in the sandbox without any Meta account.',
        'fbr' => 'Do you want to send sales invoices to FBR?',
        'webhooks' => 'Do you want to send events (entry posted, period closed …) to another system as signed webhooks?',
        'payroll' => 'Do you want to set the payroll options (working days, overtime, gratuity, approval)?',
        'budgets_bank' => 'Do you want to set budget control and bank statement import options?',
        'files' => 'Do you want to set the PDF, export and attachment options?',
        'language' => 'Do you want to change the language of the screens?',
        'modules' => 'Are there modules you will not use and want to switch off?',
    ];

    foreach ($gates as $key => $question) {
        $command->expectsConfirmation($question, isset($accept[$key]) ? 'yes' : 'no');

        if (isset($accept[$key])) {
            $accept[$key]($command);
        }
    }

    return $command;
}

it('asks the setup questions and applies the answers', function (): void {
    setupProject($this);

    $command = $this->artisan('accounting:install', ['--setup-only' => true, '--no-menu' => true])
        ->expectsChoice('Which screens do you want?', 'React (Inertia)', ['Livewire starter kit (Flux)', 'Blade (Jetstream / Breeze)', 'React (Inertia)', 'REST API only'])
        ->expectsQuestion('Base currency code (ISO 4217, for example PKR, USD, EUR)', 'pkr')
        ->expectsChoice('Which chart of accounts should the company start with?', 'School chart', ['ERPNext standard chart (recommended)', 'General business chart', 'School chart'])
        ->expectsConfirmation('Will you keep the books of more than one company (multi-company)?', 'yes')
        ->expectsQuestion('Short code of your first company (letters and digits)', 'school')
        ->expectsConfirmation('Does the company have several branches (head office, campuses, shops) to book entries to?', 'yes')
        ->expectsConfirmation('Should entries be approved by a second person (maker-checker)?', 'yes')
        ->expectsQuestion('Entries from which amount (in the base currency) need approval? (0 = every entry)', '100000')
        ->expectsConfirmation('May a user approve an entry that he or she made themselves? (not recommended)', 'no')
        ->expectsQuestion('URL of the accounting screens (https://your-app/<this>)', 'books');
    $exit = declineGroups($command, ['api' => fn ($c) => $c
        ->expectsQuestion('URL of the API', 'api/v1/accounting')
        ->expectsQuestion('API calls per minute and user (0 = no limit)', '60')
        ->expectsQuestion('Most rows an API list returns in one page', '100')])
        ->expectsQuestion('Email of the administrator (super-admin)', 'head@school.test')
        ->expectsQuestion('Name of the administrator', 'Head Teacher')
        ->expectsQuestion('Password for head@school.test (at least 8 characters)', 'a-good-password')
        ->expectsQuestion('Repeat the password', 'a-good-password')
        ->run();

    expect($exit)->toBe(0);

    $env = new EnvFile($this->project.'/.env');

    expect($env->get('ACCOUNTING_UI_DRIVER'))->toBe('inertia')->and($env->get('ACCOUNTING_BASE_CURRENCY'))->toBe('PKR')
        ->and($env->get('ACCOUNTING_CHART_PRESET'))->toBe('school')->and($env->get('ACCOUNTING_MULTI_COMPANY'))->toBe('true')->and($env->get('ACCOUNTING_BRANCHES'))->toBe('true')
        ->and($env->get('ACCOUNTING_DEFAULT_COMPANY'))->toBe('SCHOOL')
        ->and($env->get('ACCOUNTING_APPROVALS_ENABLED'))->toBe('true')->and($env->get('ACCOUNTING_APPROVAL_THRESHOLD'))->toBe('100000')
        ->and($env->get('ACCOUNTING_ALLOW_SELF_APPROVAL'))->toBe('false')
        ->and($env->get('ACCOUNTING_API_ENABLED'))->toBe('true')->and($env->get('ACCOUNTING_API_RATE_LIMIT'))->toBe('60')
        ->and($env->get('ACCOUNTING_ROUTE_PREFIX'))->toBe('books')
        ->and($env->get('ACCOUNTING_WHATSAPP_ENABLED'))->toBeNull()->and($env->get('ACCOUNTING_FBR_ENABLED'))->toBeNull()        // declined: the package defaults stay
        ->and(User::query()->where('email', 'head@school.test')->firstOrFail()->hasRole('super-admin'))->toBeTrue();
});

it('asks for the WhatsApp credentials only in live mode, and the FBR and webhook settings on request', function (): void {
    setupProject($this);

    $command = $this->artisan('accounting:install', ['--setup-only' => true, '--no-menu' => true, '--ui' => 'blade', '--currency' => 'PKR', '--chart' => 'general', '--prefix' => 'accounting', '--admin-email' => 'nobody'])
        ->expectsConfirmation('Will you keep the books of more than one company (multi-company)?', 'no')
        ->expectsConfirmation('Does the company have several branches (head office, campuses, shops) to book entries to?', 'no')
        ->expectsConfirmation('Should entries be approved by a second person (maker-checker)?', 'no');
    $exit = declineGroups($command, [
        'whatsapp' => fn ($c) => $c
            ->expectsChoice('WhatsApp mode: fake (sandbox, nothing is sent) or live (Meta)', 'live', ['fake', 'live'])
            ->expectsQuestion('WhatsApp access token (system user token) (empty = leave unset)', 'EAAG-token')
            ->expectsQuestion('WhatsApp phone number ID', '1055550000')
            ->expectsQuestion('WhatsApp Business Account ID (to list templates)', '2066660000')
            ->expectsQuestion('Meta app secret (signs the webhook calls) (empty = leave unset)', 'app-secret')
            ->expectsQuestion('Verify token for Meta\'s webhook (empty = generate a random one)', 'my-verify-word')
            ->expectsQuestion('Country code for numbers written with a leading 0 (92 for Pakistan)', '92')
            ->expectsQuestion('Language code of your WhatsApp templates (en, en_US, ur …)', 'en')
            ->expectsQuestion('Template to send the customer when a receipt is recorded (empty = none)', 'fee_received')
            ->expectsConfirmation('Send the automatic messages through the queue?', 'yes'),
        'fbr' => fn ($c) => $c
            ->expectsChoice('FBR mode: fake (accepted locally) or live', 'fake', ['fake', 'live'])
            ->expectsQuestion('Seller name', 'Green School')
            ->expectsQuestion('Seller NTN', '1234567-8')
            ->expectsQuestion('Seller province', 'Punjab')
            ->expectsQuestion('Seller address', 'Lahore')
            ->expectsQuestion('Province to use when a customer has none', 'Punjab')
            ->expectsQuestion('Default HS code of your items', '9999.0000')
            ->expectsConfirmation('Send an invoice to FBR as soon as it is posted?', 'no'),
        'webhooks' => fn ($c) => $c
            ->expectsQuestion('Webhook URL(s), comma separated', 'https://school.test/hooks/accounting')
            ->expectsQuestion('Secret that signs the webhooks (empty = generate a random one)', '')
            ->expectsQuestion('Delivery attempts', '5')
            ->expectsQuestion('Seconds to wait for the receiver', '10'),
    ])
        ->run();

    expect($exit)->toBe(0);

    $env = new EnvFile($this->project.'/.env');

    expect($env->get('ACCOUNTING_WHATSAPP_ENABLED'))->toBe('true')->and($env->get('ACCOUNTING_WHATSAPP_MODE'))->toBe('live')
        ->and($env->get('WHATSAPP_TOKEN'))->toBe('EAAG-token')->and($env->get('WHATSAPP_PHONE_NUMBER_ID'))->toBe('1055550000')
        ->and($env->get('WHATSAPP_APP_SECRET'))->toBe('app-secret')->and($env->get('WHATSAPP_VERIFY_TOKEN'))->toBe('my-verify-word')
        ->and($env->get('WHATSAPP_NOTIFY_RECEIPT'))->toBe('fee_received')->and($env->get('WHATSAPP_USE_QUEUE'))->toBe('true')
        ->and($env->get('ACCOUNTING_FBR_ENABLED'))->toBe('true')->and($env->get('ACCOUNTING_FBR_MODE'))->toBe('fake')->and($env->get('ACCOUNTING_FBR_URL'))->toBeNull()
        ->and($env->get('ACCOUNTING_FBR_SELLER_NAME'))->toBe('Green School')
        ->and($env->get('ACCOUNTING_WEBHOOK_URLS'))->toBe('https://school.test/hooks/accounting')
        ->and(strlen((string) $env->get('ACCOUNTING_WEBHOOK_SECRET')))->toBe(40);                                  // generated
});

it('lists the modules that can be switched off and every optional setting once', function (): void {
    $keys = collect(EnvQuestions::groups())->flatMap(fn (array $group) => collect($group['questions'])->pluck('env'));
    $configured = collect(explode("\n", (string) file_get_contents(__DIR__.'/../../../config/accounting.php')))
        ->map(fn (string $line) => preg_match("/env\('([A-Z_]+)'/", $line, $match) === 1 ? $match[1] : null)->filter();

    expect($keys->duplicates()->all())->toBe([])
        ->and($keys->diff($configured)->all())->toBe([]);                                                         // every question sets a key the config reads
});

it('rejects an administrator password that was typed differently the second time', function (): void {
    setupProject($this);

    $command = $this->artisan('accounting:install', ['--setup-only' => true, '--no-menu' => true, '--no-env' => true, '--ui' => 'blade', '--currency' => 'PKR', '--chart' => 'general', '--prefix' => 'accounting'])
        ->expectsConfirmation('Will you keep the books of more than one company (multi-company)?', 'no')
        ->expectsConfirmation('Does the company have several branches (head office, campuses, shops) to book entries to?', 'no')
        ->expectsConfirmation('Should entries be approved by a second person (maker-checker)?', 'no');
    declineGroups($command)
        ->expectsQuestion('Email of the administrator (super-admin)', 'head@school.test')
        ->expectsQuestion('Name of the administrator', 'Head')
        ->expectsQuestion('Password for head@school.test (at least 8 characters)', 'password-one')
        ->expectsQuestion('Repeat the password', 'password-two')
        ->run();

    expect(User::query()->count())->toBe(0);
});

it('patches the real files of the official Laravel starter kits (Livewire sidebar and header, React sidebar)', function (): void {
    $kits = __DIR__.'/../../Fixtures/starter-kits';
    writeFile($this->project.'/resources/views/layouts/app/sidebar.blade.php', (string) file_get_contents($kits.'/livewire-sidebar.blade.php'));
    writeFile($this->project.'/resources/views/layouts/app/header.blade.php', (string) file_get_contents($kits.'/livewire-header.blade.php'));
    writeFile($this->project.'/resources/js/components/app-sidebar.tsx', (string) file_get_contents($kits.'/react-app-sidebar.tsx'));
    $menu = new StarterKitMenu;

    $blade = $menu->apply('blade', $this->project, '/accounting', 'accounting.dashboard');
    $react = $menu->apply('inertia', $this->project, '/accounting', 'accounting.dashboard');

    $sidebar = (string) file_get_contents($this->project.'/resources/views/layouts/app/sidebar.blade.php');
    $header = (string) file_get_contents($this->project.'/resources/views/layouts/app/header.blade.php');

    expect(array_column($blade, 'status'))->toBe(['added', 'added'])->and(array_column($react, 'status'))->toBe(['added'])
        ->and($sidebar)->toContain('<flux:sidebar.item icon="calculator" :href="route(\'accounting.dashboard\')"')
        ->and($header)->toContain('<flux:navbar.item icon="calculator" :href="route(\'accounting.dashboard\')"')
        ->and(substr_count($header, "route('accounting.dashboard')"))->toBe(2)                    // the top bar and the mobile menu each have a Dashboard item
        ->and(substr_count($sidebar, "route('accounting.dashboard')"))->toBe(1)
        ->and(file_get_contents($this->project.'/resources/js/components/app-sidebar.tsx'))->toContain('icon: Calculator }, // accounting:menu');
});

it('patches the real navigation of Jetstream and Breeze: the top bar and the mobile menu, once', function (string $fixture, string $file): void {
    $original = (string) file_get_contents(__DIR__.'/../../Fixtures/starter-kits/'.$fixture);
    writeFile($this->project.'/'.$file, $original);
    $menu = new StarterKitMenu;

    $first = $menu->apply('blade', $this->project, '/accounting', 'accounting.dashboard');
    $second = $menu->apply('blade', $this->project, '/accounting', 'accounting.dashboard');
    $result = (string) file_get_contents($this->project.'/'.$file);

    expect($first[0]['status'])->toBe('added')->and($second[0]['status'])->toBe('present')
        ->and(substr_count($result, "route('accounting.dashboard')"))->toBe(2)                       // x-nav-link and x-responsive-nav-link
        ->and($result)->toContain("routeIs('accounting.*')")->toContain("__('Accounting')")->toContain('{{-- accounting:menu --}}')
        ->and(substr_count($result, "route('dashboard')"))->toBe(substr_count($original, "route('dashboard')"));   // the Dashboard items are untouched
})->with([
    'Jetstream (Livewire)' => ['jetstream-navigation-menu.blade.php', 'resources/views/navigation-menu.blade.php'],
    'Breeze' => ['breeze-navigation.blade.php', 'resources/views/layouts/navigation.blade.php'],
]);

it('lets Tailwind 3 (Jetstream, Breeze) scan the screens of the package through tailwind.config.js', function (): void {
    writeFile($this->project.'/resources/css/app.css', "@tailwind base;\n@tailwind components;\n@tailwind utilities;\n");
    writeFile($this->project.'/tailwind.config.js', "export default {\n    content: [\n        './resources/views/**/*.blade.php',\n    ],\n};\n");
    $menu = new StarterKitMenu;

    expect($menu->tailwindSource($this->project))->toBe('added')->and($menu->tailwindSource($this->project))->toBe('present')
        ->and(file_get_contents($this->project.'/tailwind.config.js'))->toContain("'./vendor/alimarchal/laravel-chart-of-accounts/resources/views/**/*.blade.php',")
        ->toContain("'./resources/views/**/*.blade.php'");
});

it('has three kinds of screens: --ui=livewire dresses the Blade screens for Flux, --ui=blade lets them follow Jetstream / Breeze', function (string $ui, string $driver, string $kit): void {
    setupProject($this, "APP_NAME=Laravel\n");

    $this->artisan('accounting:install', ['--setup-only' => true, '--no-interaction' => true, '--no-menu' => true, '--ui' => $ui, '--currency' => 'PKR', '--chart' => 'general', '--prefix' => 'accounting'])->assertSuccessful();
    $env = new EnvFile($this->project.'/.env');

    expect($env->get('ACCOUNTING_UI_DRIVER'))->toBe($driver)->and($env->get('ACCOUNTING_BLADE_KIT'))->toBe($kit);
})->with([
    'livewire' => ['livewire', 'blade', 'flux'],
    'blade (Jetstream / Breeze)' => ['blade', 'blade', 'auto'],
]);

it('writes no kit for React', function (): void {
    setupProject($this, "APP_NAME=Laravel\n");

    $this->artisan('accounting:install', ['--setup-only' => true, '--no-interaction' => true, '--no-menu' => true, '--ui' => 'react', '--currency' => 'PKR', '--chart' => 'general', '--prefix' => 'accounting'])->assertSuccessful();

    expect((new EnvFile($this->project.'/.env'))->get('ACCOUNTING_UI_DRIVER'))->toBe('inertia')->and((new EnvFile($this->project.'/.env'))->get('ACCOUNTING_BLADE_KIT'))->toBeNull();
});
