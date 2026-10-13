<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Support\AccountingMenu;
use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Alimarchal\LaravelChartOfAccounts\Support\Help;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->makeUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };
});

$keys = fn (array $menu): array => collect($menu)->pluck('items')->flatten(1)->pluck('key')->all();

it('offers the whole menu to the owner, in sections, with the settings after the accounting sections and the help last', function () use ($keys): void {
    $menu = AccountingMenu::for(($this->makeUser)('super-admin'), 'accounting/journal-entries');

    expect(collect($menu)->pluck('key')->all())->toBe(['overview', 'journal', 'sales', 'banking', 'payroll', 'assets', 'reports', 'access', 'setup', 'help'])
        ->and(collect($menu)->pluck('area')->unique()->values()->all())->toBe(['accounting', 'settings', 'help'])
        ->and($keys($menu))->toContain('journal-entries', 'payroll', 'trial-balance', 'users', 'periods', 'features', 'help');
});

it('shows each person only what their role may open', function () use ($keys): void {
    $viewer = $keys(AccountingMenu::for(($this->makeUser)('viewer')));
    $admin = $keys(AccountingMenu::for(($this->makeUser)('admin')));

    expect($viewer)->toContain('trial-balance', 'journal-entries', 'help')->not->toContain('users', 'roles', 'features', 'periods')
        ->and($admin)->toContain('users', 'roles', 'features', 'help');
    expect(AccountingMenu::for(null))->toBe([]);
});

it('marks the screen the person is on, and opens its section', function (): void {
    $menu = AccountingMenu::for(($this->makeUser)('super-admin'), 'accounting/payroll/loans');
    $payroll = collect($menu)->firstWhere('key', 'payroll');
    $journal = collect($menu)->firstWhere('key', 'journal');

    expect($payroll['open'])->toBeTrue()->and(collect($payroll['items'])->firstWhere('active', true)['key'])->toBe('payroll-loans')
        ->and($journal['open'])->toBeFalse();

    $settings = AccountingMenu::for(($this->makeUser)('super-admin'), config('accounting.ui_driver') === 'blade' ? 'settings/users' : 'accounting/users');
    expect(collect($settings)->firstWhere('key', 'access')['open'])->toBeTrue();
});

it('leaves out a module that is switched off', function () use ($keys): void {
    $owner = ($this->makeUser)('super-admin');
    expect($keys(AccountingMenu::for($owner)))->toContain('inventory', 'payroll-loans');

    app(FeatureManager::class)->set('inventory', false);
    app(FeatureManager::class)->set('payroll_loans', false);

    expect($keys(AccountingMenu::for($owner)))->not->toContain('inventory')->not->toContain('payroll-loans')->toContain('payroll');
});

it('gives every item of the menu a help article, and every article only working links', function (): void {
    $help = app(Help::class);
    $missing = collect(AccountingMenu::definition())->pluck('items')->flatten(1)->pluck('key')->reject(fn (string $key) => $key === 'help' || $help->topic($key) !== null)->all();
    $dead = [];

    foreach (glob(__DIR__.'/../../../resources/help/en/*.md') as $file) {
        preg_match_all('/\[\[([a-z0-9-]+)\]\]/', (string) file_get_contents($file), $links);

        foreach ($links[1] as $link) {
            if ($help->topic($link) === null) {
                $dead[] = basename($file).' -> '.$link;
            }
        }
    }

    expect($missing)->toBe([])->and($dead)->toBe([])
        ->and($help->topic('journal-entries')['title'])->toBe('Journal entries')->and($help->topic('journal-entries')['summary'])->not->toBe('')
        ->and($help->topic('../.env'))->toBeNull()->and($help->topic('nope'))->toBeNull();
});

it('finds the article of the screen a person is on', function (): void {
    $help = app(Help::class);

    expect(AccountingMenu::topicFor('accounting/payroll/loans'))->toBe('payroll-loans')
        ->and(AccountingMenu::topicFor('accounting/reports/trial-balance'))->toBe('trial-balance')
        ->and(AccountingMenu::topicFor('accounting/journal-entries/12/edit'))->toBe('journal-entries')
        ->and(AccountingMenu::topicFor('settings/roles'))->toBe('roles')->and(AccountingMenu::topicFor('accounting/roles/3/edit'))->toBe('roles')
        ->and(AccountingMenu::topicFor('dashboard'))->toBeNull()
        ->and($help->forPath('accounting/chart-of-accounts/5')['slug'])->toBe('chart-of-accounts')
        ->and($help->forPath('accounting/help/payroll'))->toBeNull();
});

it('turns [[topic]] into a link and strips raw HTML from an article', function (): void {
    $html = app(Help::class)->html('getting-started');

    expect($html)->toContain('<h2>')->toContain('href="')->toContain('Journal entries</a>')->not->toContain('[[');
});

it('serves the help centre: the index, an article, and not found for anything else', function (): void {
    if (config('accounting.ui_driver') !== 'inertia') {
        $this->markTestSkipped('Inertia only (tests/Blade has the Blade version)');
    }

    $this->actingAs(($this->makeUser)('viewer'));

    $this->get('/accounting/help')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/help/index')->has('blocks'));
    $this->get('/accounting/help/journal-entries')->assertOk()
        ->assertInertia(fn ($page) => $page->component('accounting/help/show')->where('article.slug', 'journal-entries')->has('html'));
    $this->get('/accounting/help/no-such-topic')->assertNotFound();
});

it('keeps the help centre behind the sign-in', function (): void {
    foreach (['accounting.help.index', 'accounting.help.show'] as $name) {
        expect(app('router')->getRoutes()->getByName($name)->gatherMiddleware())->toContain('auth');
    }
});

it('shares the menu and the help link of the screen with every Inertia page', function (): void {
    if (config('accounting.ui_driver') !== 'inertia') {
        $this->markTestSkipped('Inertia only');
    }

    $this->actingAs(($this->makeUser)('super-admin'));

    $this->get('/accounting/payroll')->assertInertia(fn ($page) => $page
        ->has('accountingMenu', 10)
        ->where('accountingHelp.slug', 'payroll')
        ->where('accountingMenu.4.items.0.active', true));
});
