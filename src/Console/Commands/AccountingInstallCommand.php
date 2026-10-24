<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Support\AccountRoles;
use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Alimarchal\LaravelChartOfAccounts\Support\Install\EnvFile;
use Alimarchal\LaravelChartOfAccounts\Support\Install\EnvQuestions;
use Alimarchal\LaravelChartOfAccounts\Support\Install\StarterKitMenu;
use Flux\Flux;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AccountingInstallCommand extends Command
{
    protected $signature = 'accounting:install
        {--admin-email= : Email of the user to receive the super-admin role (the user is created when it does not exist; defaults to the first user)}
        {--admin-name= : Name of the administrator, used when the user has to be created}
        {--admin-password= : Password of the administrator, used when the user has to be created (asked for when interactive)}
        {--views : Also publish the Blade views for customisation (not needed otherwise)}
        {--chart= : Chart of accounts to start from: erpnext (default), general or school}
        {--ui= : Screens to install: livewire (Flux starter kit), blade (Jetstream / Breeze), inertia (React) or api}
        {--currency= : Base currency code, for example PKR}
        {--prefix= : URL prefix of the accounting screens (default accounting)}
        {--no-menu : Do not add the Accounting link to the starter kit navigation}
        {--no-env : Do not write the answers to .env}
        {--advanced : Ask every optional setting (WhatsApp, webhooks, payroll, budgets, PDF, language, modules) instead of one yes/no per topic}
        {--setup-only : Only ask the questions, write .env, create the administrator and add the menu (no publishing, migrating or seeding)}';

    protected $description = 'Full setup: publish assets, run migrations (including Spatie), seed master data, and verify.';

    /** @var array<string, string|int|bool|null> what goes into .env */
    private array $env = [];

    public function handle(): int
    {
        if (! $this->askSetupQuestions()) {
            return self::INVALID;
        }

        $driver = (string) config('accounting.ui_driver', 'inertia');

        if (! $this->option('setup-only')) {
            $this->installPackage($driver);
        }

        $this->checkUserModel();
        $this->assignSuperAdmin();
        $this->warnIfApiGuardMissing();
        $this->addNavigation($driver);

        if ($this->option('setup-only')) {
            $this->info('Setup done.');

            return self::SUCCESS;
        }

        $this->info('Verifying installation...');
        $verifyExitCode = Artisan::call('accounting:verify', [], $this->output);

        if ($verifyExitCode !== self::SUCCESS) {
            $this->error('Verification failed. Please check the output above.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Accounting module installed successfully!');
        if ($driver !== 'api') {
            $this->info('   Log in and open '.url('/'.trim((string) config('accounting.route_prefix', 'accounting'), '/')).($driver === 'inertia' ? '  (run "npm run build" once for the React pages)' : '  (run "npm run build" once so the styles of the screens are compiled)'));
        }
        $this->newLine();
        $this->line('   UI driver: '.$driver.' (ACCOUNTING_UI_DRIVER = inertia | blade | api; for blade, ACCOUNTING_BLADE_KIT = auto | flux | jetstream | breeze | plain).');
        $this->line('   REST API: /'.trim((string) config('accounting.api_prefix'), '/').' — see docs/openapi.yaml.');
        $this->newLine();
        $this->line('   Run "php artisan accounting:update" after future package upgrades.');

        return self::SUCCESS;
    }

    /**
     * The chart the company starts from: --chart, else (asked when there is a person to ask) a choice that defaults to the ERPNext standard
     * chart, else accounting.chart_preset. A company that already has its chart keeps it (the seeder never replaces accounts).
     */
    private function chosenChart(): ?string
    {
        $presets = AccountRoles::presets();
        $chart = $this->option('chart');

        if ($chart !== null && $chart !== '') {
            if (! array_key_exists((string) $chart, $presets)) {
                $this->error("Unknown chart \"{$chart}\": use ".implode(', ', array_keys($presets)).'.');

                return null;
            }

            return (string) $chart;
        }

        $configured = (string) config('accounting.chart_preset', 'erpnext');
        $default = array_key_exists($configured, $presets) ? $configured : 'erpnext';

        if (! $this->input->isInteractive()) {
            return $default;
        }

        $label = $this->choice('Which chart of accounts should the company start with?', array_values($presets), $presets[$default]);

        return (string) array_search($label, $presets, true);
    }

    /**
     * The API defaults to auth:sanctum. Laravel 11+ apps only have Sanctum after "php artisan install:api".
     */
    private function warnIfApiGuardMissing(): void
    {
        if (! config('accounting.api_enabled', true)) {
            return;
        }

        $usesSanctum = in_array('auth:sanctum', (array) config('accounting.api_middleware', []), true);

        if ($usesSanctum && ! array_key_exists('sanctum', (array) config('auth.guards', [])) && ! class_exists(Sanctum::class)) {
            $this->warn('The REST API uses auth:sanctum, but Laravel Sanctum is not installed.');
            $this->line('   Run "php artisan install:api" (and add HasApiTokens to your User model),');
            $this->line('   or set ACCOUNTING_API_MIDDLEWARE to your own guard, or ACCOUNTING_API_ENABLED=false.');
        }
    }

    /**
     * Roles need Spatie's HasRoles on the user model; API tokens need Sanctum's HasApiTokens.
     * Print the exact lines to add instead of failing later with an obscure error.
     */
    private function checkUserModel(): void
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! class_exists($model)) {
            return;
        }

        $traits = class_uses_recursive($model);
        $missing = array_filter([
            'Spatie\\Permission\\Traits\\HasRoles' => ! in_array('Spatie\\Permission\\Traits\\HasRoles', $traits, true),
            'Laravel\\Sanctum\\HasApiTokens' => config('accounting.api_enabled', true)
                && trait_exists('Laravel\\Sanctum\\HasApiTokens')
                && ! in_array('Laravel\\Sanctum\\HasApiTokens', $traits, true),
        ]);

        if ($missing === []) {
            $this->info('User model OK ('.$model.').');

            return;
        }

        $this->warn('Add these traits to '.$model.':');

        foreach (array_keys($missing) as $trait) {
            $this->line('   use '.$trait.';');
        }

        $this->line('   …and list them in the class body, e.g. "use HasApiTokens, HasFactory, HasRoles, Notifiable;"');
    }

    private function installPackage(string $driver): void
    {
        $this->info('Publishing accounting migrations...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-migrations', '--no-interaction' => true], $this->output);

        $this->info('Publishing accounting config...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-config', '--no-interaction' => true], $this->output);

        // Views are served from the package; publishing them freezes them and hides future fixes.
        if ($this->option('views')) {
            $this->info('Publishing accounting views...');
            Artisan::call('vendor:publish', ['--tag' => 'accounting-views', '--no-interaction' => true], $this->output);
        }

        if ($driver === 'blade') {
            $this->info('Publishing accounting public assets (select2, jQuery)...');
            Artisan::call('vendor:publish', ['--tag' => 'accounting-assets', '--no-interaction' => true], $this->output);
        }

        if ($driver === 'inertia') {
            $this->info('Publishing Inertia/React pages to resources/js/pages/accounting...');
            Artisan::call('vendor:publish', ['--tag' => 'accounting-js', '--no-interaction' => true], $this->output);
        }

        if (! $this->spatiePermissionMigrationExists()) {
            $this->info('Publishing spatie/laravel-permission migrations...');
            Artisan::call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--no-interaction' => true,
            ], $this->output);
        }

        if (! $this->spatieActivitylogMigrationExists()) {
            $this->info('Publishing spatie/laravel-activitylog migrations...');
            Artisan::call('vendor:publish', [
                '--provider' => 'Spatie\Activitylog\ActivitylogServiceProvider',
                '--tag' => 'activitylog-migrations',
                '--no-interaction' => true,
            ], $this->output);
        }

        $this->info('Running all migrations...');
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true], $this->output);

        $chart = (string) config('accounting.chart_preset', 'erpnext');

        $this->info('Seeding accounting master data ('.(AccountRoles::presets()[$chart] ?? $chart).')...');
        Artisan::call('accounting:seed', ['--chart' => $chart], $this->output);

        $this->info('Syncing database objects...');
        Artisan::call('accounting:sync-db-objects', [], $this->output);
    }

    /**
     * The questions of a first installation (only asked when there is a person to ask and the answer was not given as an option): screens,
     * currency, chart, companies, approvals, WhatsApp, REST API, URL prefix. The answers take effect for this run and are written to .env.
     */
    private function askSetupQuestions(): bool
    {
        $interactive = $this->input->isInteractive();
        $envFile = new EnvFile($this->laravel->environmentFilePath());
        $current = fn (string $key, string $fallback): string => $envFile->get($key) ?? $fallback;

        // 1. The screens
        $uis = ['livewire' => 'Livewire starter kit (Flux)', 'blade' => 'Blade (Jetstream / Breeze)', 'inertia' => 'React (Inertia)', 'api' => 'REST API only'];
        $ui = strtolower((string) $this->option('ui'));
        $ui = $ui === 'react' ? 'inertia' : $ui;

        if ($ui !== '' && ! array_key_exists($ui, $uis)) {
            $this->error("Unknown --ui \"{$ui}\": use livewire, blade, inertia or api.");

            return false;
        }

        if ($ui === '' && $interactive) {
            $saved = $envFile->get('ACCOUNTING_UI_DRIVER');
            $default = $saved === 'blade' ? ($envFile->get('ACCOUNTING_BLADE_KIT') === 'flux' ? 'livewire' : 'blade') : ($saved ?: $this->detectUi());
            $ui = (string) array_search($this->choice('Which screens do you want?', array_values($uis), $uis[$default] ?? $uis['livewire']), $uis, true);
        }

        // Livewire and Blade are the same screens (the Blade driver); the kit they are dressed for is told to the layout.
        $this->remember('ACCOUNTING_UI_DRIVER', match ($ui) {
            '' => null, 'livewire' => 'blade', default => $ui
        }, 'accounting.ui_driver');
        $this->remember('ACCOUNTING_BLADE_KIT', match ($ui) {
            'livewire' => 'flux', 'blade' => 'auto', default => null
        }, 'accounting.blade_kit');

        // 2. Currency
        $currency = strtoupper(trim((string) $this->option('currency')));

        if ($currency === '' && $interactive) {
            $currency = strtoupper(trim((string) $this->ask('Base currency code (ISO 4217, for example PKR, USD, EUR)', $current('ACCOUNTING_BASE_CURRENCY', (string) config('accounting.defaults.currency_code', 'PKR')))));
        }

        if ($currency !== '' && preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $this->error("\"{$currency}\" is not a three-letter currency code.");

            return false;
        }

        $this->remember('ACCOUNTING_BASE_CURRENCY', $currency !== '' ? $currency : null, 'accounting.defaults.currency_code');

        // 3. The chart of accounts
        $chart = $this->chosenChart();

        if ($chart === null) {
            return false;
        }

        $this->remember('ACCOUNTING_CHART_PRESET', ($this->option('chart') || $interactive) ? $chart : null, 'accounting.chart_preset');
        config(['accounting.chart_preset' => $chart]);

        // 4. Companies and maker-checker
        if ($interactive) {
            $multi = $this->confirm('Will you keep the books of more than one company (multi-company)?', (bool) config('accounting.multi_company.enabled', false));
            $this->remember('ACCOUNTING_MULTI_COMPANY', $multi, 'accounting.multi_company.enabled');

            if ($multi) {
                $this->remember('ACCOUNTING_DEFAULT_COMPANY', strtoupper(trim((string) $this->ask('Short code of your first company (letters and digits)', $current('ACCOUNTING_DEFAULT_COMPANY', (string) config('accounting.multi_company.default_company_code', 'MAIN'))))), 'accounting.multi_company.default_company_code');
            }

            $this->remember('ACCOUNTING_BRANCHES', $this->confirm('Does the company have several branches (head office, campuses, shops) to book entries to?', (bool) config('accounting.branches.enabled', false)), 'accounting.branches.enabled');

            $approvals = $this->confirm('Should entries be approved by a second person (maker-checker)?', (bool) config('accounting.approvals.enabled', false));
            $this->remember('ACCOUNTING_APPROVALS_ENABLED', $approvals, 'accounting.approvals.enabled');

            if ($approvals) {
                $this->remember('ACCOUNTING_APPROVAL_THRESHOLD', (string) $this->ask('Entries from which amount (in the base currency) need approval? (0 = every entry)', (string) config('accounting.approvals.threshold', '0')), 'accounting.approvals.threshold');
                $this->remember('ACCOUNTING_ALLOW_SELF_APPROVAL', $this->confirm('May a user approve an entry that he or she made themselves? (not recommended)', false), 'accounting.approvals.allow_self_approval');
            }
        } elseif (! class_exists(Sanctum::class) && $envFile->get('ACCOUNTING_API_ENABLED') === null && ! array_key_exists('sanctum', (array) config('auth.guards', []))) {
            $this->remember('ACCOUNTING_API_ENABLED', false, 'accounting.api_enabled');
        }

        // 5. The URL of the screens
        $prefix = trim((string) $this->option('prefix'), '/ ');

        if ($prefix === '' && $interactive) {
            $prefix = trim((string) $this->ask('URL of the accounting screens (https://your-app/<this>)', $current('ACCOUNTING_ROUTE_PREFIX', (string) config('accounting.route_prefix', 'accounting'))), '/ ');
        }

        if ($prefix !== '' && in_array(strtolower(explode('/', $prefix)[0]), self::RESERVED_PREFIXES, true)) {
            $this->error("\"{$prefix}\" is already used by your application (the starter kit's own screens): choose another URL, for example accounting.");

            return false;
        }

        $this->remember('ACCOUNTING_ROUTE_PREFIX', $prefix !== '' ? $prefix : null, 'accounting.route_prefix');

        if ($interactive) {
            $this->askOptionalGroups($envFile);
        }

        if (! $this->option('no-env')) {
            $written = $envFile->set(array_filter($this->env, fn ($value) => $value !== null));

            if ($written !== []) {
                $this->info('Saved to .env: '.implode(', ', $written));
            } elseif ($this->env !== [] && ! $envFile->exists()) {
                $this->warn('No .env file found: set '.implode(', ', array_keys($this->env)).' yourself.');
            }
        }

        return true;
    }

    /**
     * The optional settings, topic by topic (EnvQuestions): one yes/no per topic, or every question with --advanced. What is answered goes
     * to .env; a question left empty is not written, so the package default stays.
     */
    private function askOptionalGroups(EnvFile $envFile): void
    {
        $advanced = (bool) $this->option('advanced');

        foreach (EnvQuestions::groups() as $group) {
            $default = $group['key'] === 'api' ? class_exists(Sanctum::class) && (bool) config('accounting.api_enabled', true) : false;

            if (! $advanced && ! $this->confirm($group['gate'], $default)) {
                foreach ($group['off'] ?? [] as $key => $value) {
                    $this->env[$key] = $value;
                }

                continue;
            }

            if ($advanced) {
                $this->newLine();
                $this->line("<fg=cyan>{$group['title']}</>");
            }

            foreach ($group['questions'] as $question) {
                if (isset($question['when']) && ! $this->answerIs((array) $question['when'], $envFile)) {
                    continue;
                }

                if (! empty($question['forced'])) {
                    $this->env[(string) $question['env']] = true;

                    continue;
                }

                $value = $this->askValue($question, $envFile->get((string) $question['env']));

                if ($value !== null) {
                    $this->env[(string) $question['env']] = $value;
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $when  env key => the value an earlier answer (or .env) must have
     */
    private function answerIs(array $when, EnvFile $envFile): bool
    {
        foreach ($when as $key => $expected) {
            $actual = $this->env[$key] ?? $envFile->get($key);

            if ((is_bool($actual) ? ($actual ? 'true' : 'false') : (string) $actual) !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function askValue(array $question, ?string $current): string|int|bool|null
    {
        $type = (string) ($question['type'] ?? 'text');
        $label = (string) $question['label'];
        $default = $current ?? (isset($question['default']) ? (string) (is_bool($question['default']) ? ($question['default'] ? 'true' : 'false') : $question['default']) : null);

        if ($type === 'bool') {
            return $this->confirm($label, filter_var($default, FILTER_VALIDATE_BOOLEAN));
        }

        if ($type === 'choice') {
            $choices = array_values((array) $question['choices']);

            return (string) $this->choice($label, $choices, in_array($default, $choices, true) ? $default : $choices[0]);
        }

        if ($type === 'secret') {
            $value = trim((string) $this->secret($label.' (empty = leave unset)'));

            return $value === '' ? null : $value;
        }

        if ($type === 'secret-or-random') {
            $value = trim((string) $this->ask($label.' (empty = '.($current ? 'keep the current one' : 'generate a random one').')'));

            if ($value === '' && $current) {
                return null;
            }

            if ($value === '') {
                $value = Str::random(40);
                $this->line('   '.$question['env'].' = '.$value);
            }

            return $value;
        }

        if ($type === 'features') {
            $options = ['none' => 'none: keep every module'];

            foreach (FeatureManager::catalog() as $key => $feature) {
                if ($feature['parent'] === null && $feature['group'] === 'Modules') {
                    $options[$key] = $feature['label'];
                }
            }

            $picked = array_diff((array) $this->choice($label.' (several: 2,4)', $options, 'none', null, true), ['none', $options['none']]);
            $keys = array_values(array_filter(array_map(fn ($pick) => array_key_exists((string) $pick, $options) ? (string) $pick : (string) array_search($pick, $options, true), $picked)));

            return $keys === [] ? null : implode(',', $keys);
        }

        for ($try = 0; $try < 3; $try++) {
            $value = trim((string) $this->ask($label, $default !== null && $default !== '' ? $default : null));

            if ($value === '') {
                return null;
            }

            if ($type === 'int' && ! ctype_digit($value)) {
                $this->error('Please write a whole number.');
            } elseif ($type === 'url' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                $this->error('Please write a full URL, for example https://example.com/path.');
            } elseif ($type === 'time' && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
                $this->error('Please write the time as HH:MM, for example 03:00.');
            } else {
                return $type === 'int' ? (int) $value : $value;
            }
        }

        return null;
    }

    /** Keep an answer: for this run (config) and, when given, for .env. */
    private function remember(string $envKey, string|int|bool|null $value, string $configKey): void
    {
        if ($value === null) {
            return;
        }

        $this->env[$envKey] = $value;
        config([$configKey => $value]);
    }

    private function detectUi(): string
    {
        if (is_file(resource_path('js/components/app-sidebar.tsx')) || is_file(resource_path('js/components/app-header.tsx'))) {
            return 'inertia';
        }

        if (class_exists(Jetstream::class) || is_file(resource_path('views/navigation-menu.blade.php')) || is_file(resource_path('views/layouts/navigation.blade.php'))) {
            return 'blade';                                         // Jetstream (Livewire) and Breeze (Blade)
        }

        if (class_exists(Flux::class) || is_file(resource_path('views/components/layouts/app/sidebar.blade.php')) || is_file(resource_path('views/layouts/app/sidebar.blade.php'))) {
            return 'livewire';
        }

        return $this->vueOrSvelteKit() || is_dir(resource_path('views/layouts')) || class_exists(Livewire::class) ? 'blade' : (string) config('accounting.ui_driver', 'inertia');
    }

    /** The Vue and Svelte starter kits: the accounting screens are React (Inertia) or Blade, so these apps use the Blade screens. */
    private function vueOrSvelteKit(): bool
    {
        foreach (['components/AppSidebar.vue', 'components/app-sidebar.vue', 'components/AppSidebar.svelte', 'components/app-sidebar.svelte', 'components/AppHeader.vue', 'components/app-header.svelte', 'Layouts/AppLayout.vue'] as $file) {
            if (is_file(resource_path('js/'.$file))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The administrator: --admin-email (asked for when interactive). A user that does not exist yet is created (name and password asked for,
     * or --admin-name / --admin-password), then it gets the super-admin role.
     */
    private function assignSuperAdmin(): void
    {
        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            return;
        }

        $email = trim((string) $this->option('admin-email'));
        $interactive = $this->input->isInteractive();

        if ($email === '' && $interactive) {
            $first = $userModel::query()->orderBy((new $userModel)->getKeyName())->first();
            $email = trim((string) $this->ask('Email of the administrator (super-admin)', $first instanceof Model ? (string) $first->getAttribute('email') : null));
        }

        $user = $email !== ''
            ? $userModel::query()->where('email', $email)->first()
            : $userModel::query()->orderBy((new $userModel)->getKeyName())->first();

        if (! $user && $email !== '') {
            $user = $this->createAdministrator($userModel, $email, $interactive);
        }

        if (! $user) {
            $this->warn($email !== ''
                ? "No user with email {$email} was created. Create it, then run \"php artisan accounting:install --admin-email={$email}\" again."
                : 'No users found. Please create a user and assign the "super-admin" role manually.');

            return;
        }

        if (! $user instanceof Model || ! method_exists($user, 'assignRole')) {
            $this->warn('The super-admin role was not assigned: add HasRoles to your user model (see above), then run "php artisan accounting:install" again.');

            return;
        }

        if ($email === '') {
            $this->warn('No --admin-email given: the super-admin role goes to the first user. Verify this is intended.');
        }

        $role = Role::findByName('super-admin', 'web');
        $user->assignRole($role);

        $this->info('Assigned "super-admin" role to user: '.$user->getAttribute('email'));
    }

    private function createAdministrator(string $userModel, string $email, bool $interactive): ?Model
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("\"{$email}\" is not an email address.");

            return null;
        }

        $name = trim((string) $this->option('admin-name'));
        $password = (string) $this->option('admin-password');

        if ($name === '' && $interactive) {
            $name = trim((string) $this->ask('Name of the administrator', 'Administrator'));
        }

        if ($password === '' && $interactive) {
            $password = (string) $this->secret('Password for '.$email.' (at least 8 characters)');
            $confirm = (string) $this->secret('Repeat the password');

            if ($password !== $confirm) {
                $this->error('The passwords do not match.');

                return null;
            }
        }

        if (strlen($password) < 8) {
            $this->error('The administrator needs a password of at least 8 characters (--admin-password).');

            return null;
        }

        try {
            /** @var Model $user */
            $user = new $userModel;
            $user->forceFill([
                'name' => $name !== '' ? $name : 'Administrator', 'email' => $email, 'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $user->save();
        } catch (\Throwable $exception) {
            $this->error('The administrator could not be created: '.$exception->getMessage());

            return null;
        }

        $this->info("Created the user {$email}.");

        return $user;
    }

    /**
     * The Accounting menu in the navigation of the starter kit (Livewire: Blade sidebar, Jetstream/Breeze: top navigation, React: app-sidebar.tsx) and the Tailwind source that
     * compiles the classes of the package's Blade screens.
     */
    private function addNavigation(string $driver): void
    {
        if ($this->option('no-menu') || $driver === 'api') {
            return;
        }

        $menu = new StarterKitMenu;
        $url = '/'.trim((string) config('accounting.route_prefix', 'accounting'), '/');
        $routeName = trim((string) config('accounting.route_name_prefix', 'accounting'), '.').'.dashboard';
        $results = $menu->apply($driver, base_path(), $url, $routeName);

        foreach ($results as $result) {
            $line = "   {$result['file']}: {$result['message']}";
            $result['status'] === 'manual' ? $this->warn($line) : $this->line($line);
        }

        if ($results === [] && $this->vueOrSvelteKit()) {
            $this->line('   Vue/Svelte starter kit: the accounting screens are separate pages, not Inertia pages. Add a plain link, not an Inertia Link:  <a href="'.$url.'">Accounting</a>');
        } elseif ($results === []) {
            $this->line($driver === 'blade'
                ? '   No starter kit menu found (Flux sidebar, Jetstream or Breeze navigation): link to route(\''.$routeName.'\') from your own menu.'
                : '   No React starter kit sidebar found: add <AccountingNav /> (from @/components/accounting/accounting-nav) below <NavMain /> in your sidebar.');
        } elseif (in_array('manual', array_column($results, 'status'), true)) {
            $this->line($driver === 'blade'
                ? '   Add to your menu:  Flux sidebar  <x-accounting::menu.sidebar />   |   Flux header, Jetstream, Breeze  <x-accounting::menu.dropdown />   |   their mobile menu  <x-accounting::menu.responsive />'
                : '   Add <AccountingNav /> below <NavMain /> in your sidebar (header layout: <AccountingHeaderMenu />), from @/components/accounting/accounting-nav.');
        }

        if ($driver === 'blade' && $menu->tailwindSource(base_path()) === 'added') {
            $this->line('   Tailwind (resources/css/app.css or tailwind.config.js) now also scans the package\'s screens (run "npm run build" or keep "npm run dev" running).');
        }
    }

    /** URL segments the starter kits use for their own screens: the accounting screens must not take them over. */
    private const RESERVED_PREFIXES = ['settings', 'dashboard', 'login', 'logout', 'register', 'password', 'user', 'email', 'two-factor', 'livewire', 'api', 'up', 'storage', 'sanctum', 'forgot-password', 'reset-password', 'verify-email', 'confirm-password', 'teams', 'profile', 'terms-of-service', 'privacy-policy', 'two-factor-challenge'];

    private function spatiePermissionMigrationExists(): bool
    {
        return collect(glob(database_path('migrations/*.php')))
            ->contains(fn ($path) => str_contains($path, 'create_permission_tables'));
    }

    private function spatieActivitylogMigrationExists(): bool
    {
        return collect(glob(database_path('migrations/*.php')))
            ->contains(fn ($path) => str_contains($path, 'create_activity_log_table'));
    }
}
