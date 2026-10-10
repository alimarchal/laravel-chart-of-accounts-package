<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Services\PartyDocumentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyPaymentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('accountant');
    Sanctum::actingAs($this->user);
    account('1103')->forceFill(['control_type' => 'receivables'])->save();
    account('2101')->forceFill(['control_type' => 'payables'])->save();
    $this->start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->on = fn (int $day): string => $this->start->copy()->addDays($day - 1)->toDateString();

    $parties = app(PartyService::class);
    $this->makeParty = fn (string $type, string $code, string $name, array $extra = []) => $parties->create($parties->validate(['type' => $type, 'code' => $code, 'name' => $name, ...$extra]));
    $this->acme = ($this->makeParty)('customer', 'C001', 'Acme Traders', ['email' => 'buy@acme.test']);
    $this->paper = ($this->makeParty)('supplier', 'S001', 'Paper Mart', ['tax_number' => 'NTN-777']);
    $this->both = ($this->makeParty)('both', 'B001', 'Both Ways Ltd');
    $this->percent = ($this->makeParty)('customer', 'C002', '100% Organic');
    $this->idle = ($this->makeParty)('customer', 'C003', 'Dormant Co');
    $this->idle->forceFill(['is_active' => false])->save();

    $docs = app(PartyDocumentService::class);
    $this->makeDocument = function (string $kind, Party $party, int $net, int $day, array $extra = []) use ($docs) {
        $data = $docs->validate(['party_id' => $party->id, 'kind' => $kind, 'issue_date' => ($this->on)($day), 'lines' => [['chart_of_account_id' => account($kind === 'bill' ? '5102' : '4101')->id, 'quantity' => 1, 'unit_price' => $net]], ...$extra]);

        return $docs->post($docs->create($data));
    };
});

/** @return list<string> */
function codes(TestResponse $response, string $key = 'code'): array
{
    return collect($response->assertOk()->json('data'))->pluck($key)->all();
}

it('filters parties by type, treating "both" as customer and supplier', function (): void {
    expect(codes($this->getJson('/api/v1/accounting/parties?filter[type]=customer')))->toEqualCanonicalizing(['C001', 'B001', 'C002', 'C003'])
        ->and(codes($this->getJson('/api/v1/accounting/parties?type=customer')))->toEqualCanonicalizing(['C001', 'B001', 'C002', 'C003'])
        ->and(codes($this->getJson('/api/v1/accounting/parties?type=supplier')))->toEqualCanonicalizing(['S001', 'B001'])
        ->and(codes($this->getJson('/api/v1/accounting/parties?type=both')))->toBe(['B001']);
});

it('accepts the standard filter[...] form and the short form alike, the standard one winning', function (): void {
    $standard = codes($this->getJson('/api/v1/accounting/parties?filter[search]=paper'));
    $short = codes($this->getJson('/api/v1/accounting/parties?search=paper'));
    $both = codes($this->getJson('/api/v1/accounting/parties?filter[search]=paper&search=acme'));

    expect($standard)->toBe(['S001'])->and($short)->toBe($standard)->and($both)->toBe(['S001']);
});

it('searches name, code, email and tax number, and matches % and _ literally', function (): void {
    expect(codes($this->getJson('/api/v1/accounting/parties?search=acme')))->toBe(['C001'])
        ->and(codes($this->getJson('/api/v1/accounting/parties?search=S001')))->toBe(['S001'])
        ->and(codes($this->getJson('/api/v1/accounting/parties?search=buy@acme')))->toBe(['C001'])
        ->and(codes($this->getJson('/api/v1/accounting/parties?search=ntn-777')))->toBe(['S001'])
        ->and(codes($this->getJson('/api/v1/accounting/parties?search=100%25')))->toBe(['C002'])    // "100%": not "100 followed by anything"
        ->and(codes($this->getJson('/api/v1/accounting/parties?search=%25')))->toBe(['C002'])        // a lone % is only the % in "100% Organic"
        ->and(codes($this->getJson('/api/v1/accounting/parties?search=C_01')))->toBe([])             // _ is not a wildcard
        ->and(codes($this->getJson('/api/v1/accounting/parties?search=nobody')))->toBe([]);
});

it('filters active and inactive parties and sorts by the requested column', function (): void {
    expect(codes($this->getJson('/api/v1/accounting/parties?active=0')))->toBe(['C003'])
        ->and(codes($this->getJson('/api/v1/accounting/parties?filter[active]=false')))->toBe(['C003'])
        ->and(codes($this->getJson('/api/v1/accounting/parties?active=1')))->toEqualCanonicalizing(['C001', 'S001', 'B001', 'C002'])
        ->and(codes($this->getJson('/api/v1/accounting/parties?sort=-code')))->toBe(['S001', 'C003', 'C002', 'C001', 'B001'])
        ->and(codes($this->getJson('/api/v1/accounting/parties')))->toBe(['C002', 'C001', 'B001', 'C003', 'S001']);          // by name
});

it('refuses filters a list does not offer and sorts that are not allowed', function (): void {
    $this->getJson('/api/v1/accounting/parties?filter[password]=x')->assertStatus(400);
    $this->getJson('/api/v1/accounting/parties?sort=credit_limit')->assertStatus(400);
});

it('filters documents by kind, status, party, number, dates and amount', function (): void {
    $one = ($this->makeDocument)('invoice', $this->acme, 100, 1);
    $two = ($this->makeDocument)('invoice', $this->percent, 500, 10, ['reference' => 'PO-RENT-9']);
    $bill = ($this->makeDocument)('bill', $this->paper, 300, 20);
    $draft = app(PartyDocumentService::class);
    $draftDoc = $draft->create($draft->validate(['party_id' => $this->acme->id, 'kind' => 'invoice', 'issue_date' => ($this->on)(25), 'lines' => [['chart_of_account_id' => account('4101')->id, 'quantity' => 1, 'unit_price' => 50]]]));
    $url = fn (string $query) => $this->getJson("/api/v1/accounting/party-documents?{$query}");
    $ids = fn (string $query) => collect($url($query)->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('kind=bill'))->toBe([$bill->id])
        ->and($ids('filter[kind]=invoice&status=posted'))->toEqualCanonicalizing([$two->id, $one->id])
        ->and($ids('status=draft'))->toBe([$draftDoc->id])
        ->and($ids('party_id='.$this->percent->id))->toBe([$two->id])
        ->and($ids('search=RENT'))->toBe([$two->id])                                              // the reference
        ->and($ids('search='.$one->number))->toBe([$one->id])                                      // the number
        ->and($ids('date_from='.($this->on)(10).'&date_to='.($this->on)(20)))->toEqualCanonicalizing([$bill->id, $two->id])
        ->and($ids('min_total=400'))->toBe([$two->id])
        ->and($ids('max_total=100'))->toEqualCanonicalizing([$draftDoc->id, $one->id])
        ->and($ids('kind=invoice&sort=total'))->toBe([$draftDoc->id, $one->id, $two->id])
        ->and($ids('kind=invoice'))->toBe([$draftDoc->id, $two->id, $one->id]);                   // newest first by default

    $this->getJson('/api/v1/accounting/party-documents?date_from=not-a-date')->assertStatus(422);
    $this->getJson('/api/v1/accounting/party-documents?min_total=lots')->assertStatus(422);
});

it('filters payments by kind, party, method, dates, amount and reference', function (): void {
    $pay = app(PartyPaymentService::class);
    $make = fn (Party $party, string $kind, int $amount, int $day, array $extra = []) => $pay->create($pay->validate(['party_id' => $party->id, 'kind' => $kind, 'payment_date' => ($this->on)($day), 'amount' => $amount, 'account_id' => account('1101')->id, ...$extra]));
    $a = $make($this->acme, 'receipt', 100, 2, ['method' => 'cash', 'reference' => 'CHQ-1001']);
    $b = $make($this->acme, 'receipt', 900, 12, ['method' => 'bank']);
    $c = $make($this->paper, 'payment', 250, 22, ['method' => 'bank']);
    $ids = fn (string $query) => collect($this->getJson("/api/v1/accounting/party-payments?{$query}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('kind=payment'))->toBe([$c->id])
        ->and($ids('party_id='.$this->acme->id))->toBe([$b->id, $a->id])
        ->and($ids('method=bank'))->toBe([$c->id, $b->id])
        ->and($ids('search=chq'))->toBe([$a->id])
        ->and($ids('date_from='.($this->on)(10).'&date_to='.($this->on)(30)))->toBe([$c->id, $b->id])
        ->and($ids('min_amount=200&max_amount=500'))->toBe([$c->id])
        ->and($ids('sort=amount'))->toBe([$a->id, $c->id, $b->id]);
});

it('shows the active filters on the React screens', function (): void {
    $this->withoutVite();
    $this->actingAs($this->user);
    ($this->makeDocument)('invoice', $this->acme, 100, 1);

    $this->get('/accounting/parties?filter[search]=acme&type=customer')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/parties/index')->has('parties', 1)->where('filters.search', 'acme')->where('filters.type', 'customer'));
    $this->get('/accounting/party-documents?status=posted&search=zzz')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('documents', 0)->where('filters.status', 'posted')->where('filters.search', 'zzz'));
});
