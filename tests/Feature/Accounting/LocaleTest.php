<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Support\PhraseTranslator;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

it('translates only phrases that equal a dictionary key, and never scripts or code', function (): void {
    $messages = ['Save' => 'محفوظ کریں', 'Chart of Accounts' => 'کھاتوں کا چارٹ', 'Search' => 'تلاش'];
    $html = '<h1> Chart of Accounts </h1><button>Save</button><p>Save the date</p><input placeholder="Search" value="Save"><a title="Save">x</a><script>var a = ">Save<";</script><code>Save</code><td>Journal &amp; Ledger</td>';
    $out = PhraseTranslator::html($html, $messages);

    expect($out)->toContain('<h1> کھاتوں کا چارٹ </h1>')->and($out)->toContain('<button>محفوظ کریں</button>')->and($out)->toContain('<p>Save the date</p>')
        ->and($out)->toContain('placeholder="تلاش" value="Save"')->and($out)->toContain('title="محفوظ کریں"')
        ->and($out)->toContain('<script>var a = ">Save<";</script>')->and($out)->toContain('<code>Save</code>')->and($out)->toContain('Journal &amp; Ledger');
    expect(PhraseTranslator::html($html, []))->toBe($html);
});

it('decodes entities when matching and escapes what it inserts', function (): void {
    $out = PhraseTranslator::html('<b>Invoices &amp; Bills</b><i>A</i>', ['Invoices & Bills' => 'انوائسز اور بلز', 'A' => '<b>']);

    expect($out)->toBe('<b>انوائسز اور بلز</b><i>&lt;b&gt;</i>');
});

it('ships an Urdu dictionary of valid, non-empty phrases', function (): void {
    $dictionary = json_decode((string) file_get_contents(__DIR__.'/../../../resources/lang/ur.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(count($dictionary))->toBeGreaterThan(300)->and(collect($dictionary)->every(fn ($value, $key) => is_string($value) && trim($value) !== '' && trim($key) !== ''))->toBeTrue()
        ->and($dictionary['Journal Entries'])->toBe('جرنل اندراجات');
});

it('shares the language, direction and dictionary with the React pages and remembers the choice', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);

    $this->get('/accounting')->assertInertia(fn (AssertableInertia $page) => $page->where('accountingI18n.locale', 'en')->where('accountingI18n.dir', 'ltr')->where('accountingI18n.messages', []));
    $this->get('/accounting?lang=ur')->assertInertia(fn (AssertableInertia $page) => $page->where('accountingI18n.locale', 'ur')->where('accountingI18n.dir', 'rtl')->where('accountingI18n.messages.Save', 'محفوظ کریں')->where('accountingI18n.switchUrl', '/accounting/locale'));
    // The choice stays for the session; an unknown language is ignored.
    $this->get('/accounting')->assertInertia(fn (AssertableInertia $page) => $page->where('accountingI18n.locale', 'ur'));
    $this->get('/accounting?lang=xx')->assertInertia(fn (AssertableInertia $page) => $page->where('accountingI18n.locale', 'ur'));
    $this->post('/accounting/locale', ['locale' => 'en'])->assertRedirect();
    $this->get('/accounting')->assertInertia(fn (AssertableInertia $page) => $page->where('accountingI18n.locale', 'en'));
    $this->postJson('/accounting/locale', ['locale' => 'fr'])->assertUnprocessable();
});

it('lets the application override a phrase from its own lang file', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
    $folder = sys_get_temp_dir().'/accounting-lang-'.uniqid();
    mkdir($folder);
    file_put_contents($folder.'/ur.json', json_encode(['Save' => 'درج کریں'], JSON_UNESCAPED_UNICODE));
    app('translator')->getLoader()->addJsonPath($folder);

    $this->get('/accounting?lang=ur')->assertInertia(fn (AssertableInertia $page) => $page->where('accountingI18n.messages.Save', 'درج کریں')->where('accountingI18n.messages.Cancel', 'منسوخ کریں'));
    unlink($folder.'/ur.json');
    rmdir($folder);
});

it('does not translate the API', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    Sanctum::actingAs($user);

    $this->withHeaders(['Accept-Language' => 'ur'])->getJson('/api/v1/accounting/chart-of-accounts/tree')->assertOk();
    $this->postJson('/api/v1/accounting/journal-entries', [])->assertUnprocessable()->assertJsonPath('message', fn ($message) => ! preg_match('/\p{Arabic}/u', (string) $message));
});
