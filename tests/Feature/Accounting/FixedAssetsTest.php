<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\AssetDepreciation;
use Alimarchal\LaravelChartOfAccounts\Models\FixedAsset;
use Alimarchal\LaravelChartOfAccounts\Services\FixedAssetService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    $this->start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->service = fn () => app(FixedAssetService::class);
    $this->make = function (array $overrides = []): FixedAsset {
        $data = ($this->service)()->validate([
            'code' => 'EQ-1', 'name' => 'Server', 'acquisition_date' => $this->start->toDateString(), 'cost' => '12000', 'salvage_value' => '0',
            'useful_life_months' => 12, 'method' => 'straight_line',
            'asset_account_id' => account('1205')->id, 'accumulated_account_id' => account('1206')->id, 'expense_account_id' => account('5114')->id,
            ...$overrides,
        ]);

        return ($this->service)()->create($data);
    };
    $this->monthEnd = fn (int $n): string => $this->start->copy()->addMonths($n)->endOfMonth()->toDateString();
});

it('registers an asset and books its purchase when an offset account is given', function (): void {
    $asset = ($this->make)(['offset_account_id' => account('1101')->id]);

    expect($asset->acquisition_entry_id)->not->toBeNull();
    $this->assertDatabaseHas('accounting_journal_entry_lines', ['journal_entry_id' => $asset->acquisition_entry_id, 'chart_of_account_id' => account('1205')->id, 'debit' => '12000.00']);
});

it('depreciates straight line month by month and is idempotent', function (): void {
    $asset = ($this->make)(['cost' => '1000', 'useful_life_months' => 3, 'salvage_value' => '100']);

    $preview = ($this->service)()->preview(($this->monthEnd)(1));
    expect($preview['months'])->toHaveCount(2)->and($preview['total'])->toBe('600.00');

    $run = ($this->service)()->run(($this->monthEnd)(1));
    expect($run['months'])->toBe(2)->and($run['total'])->toBe('600.00');
    expect(($this->service)()->run(($this->monthEnd)(1))['months'])->toBe(0);

    ($this->service)()->run(($this->monthEnd)(5));
    $asset->refresh();
    // 900 spread over 3 months = 300 each, and never below salvage value.
    expect($asset->accumulated_depreciation)->toBe('900.00')->and(AssetDepreciation::query()->count())->toBe(3);
});

it('keeps every cent when the cost does not divide evenly', function (): void {
    ($this->make)(['cost' => '100', 'useful_life_months' => 3]);
    ($this->service)()->run(($this->monthEnd)(4));

    expect(AssetDepreciation::query()->orderBy('period_month')->pluck('amount')->all())->toBe(['33.33', '33.33', '33.34']);
});

it('depreciates on the declining balance down to the salvage value', function (): void {
    $asset = ($this->make)(['cost' => '10000', 'salvage_value' => '1000', 'useful_life_months' => 12, 'method' => 'declining_balance']);
    ($this->service)()->run(($this->monthEnd)(11));
    $rows = AssetDepreciation::query()->orderBy('period_month')->pluck('amount')->map(fn ($amount) => (float) $amount)->all();

    expect($rows[0])->toBeGreaterThan($rows[5])->and($asset->refresh()->accumulated_depreciation)->toBe('9000.00');
});

it('posts depreciation to the ledger and keeps the register in step with it', function (): void {
    ($this->make)(['offset_account_id' => account('1101')->id]);
    ($this->service)()->run(($this->monthEnd)(2));

    $register = ($this->service)()->register(($this->monthEnd)(2));
    expect($register['totals'])->toBe(['cost' => '12000.00', 'accumulated' => '3000.00', 'book_value' => '9000.00'])
        ->and(($this->service)()->reconcile(($this->monthEnd)(2))['difference'])->toBe('0.00');
});

it('disposes of an asset with a gain, bringing depreciation up first', function (): void {
    $asset = ($this->make)(['offset_account_id' => account('1101')->id]);
    $disposed = ($this->service)()->dispose($asset, ['disposal_date' => $this->start->copy()->addMonths(3)->addDays(9)->toDateString(), 'proceeds' => '10000', 'proceeds_account_id' => account('1101')->id, 'gain_loss_account_id' => account('4101')->id]);

    // Three full months depreciated (3,000): book value 9,000, sold for 10,000 → gain 1,000.
    expect($disposed->status)->toBe('disposed')->and($disposed->disposal_gain_loss)->toBe('1000.00')->and($disposed->accumulated_depreciation)->toBe('3000.00')
        ->and(($this->service)()->register($disposed->disposed_at->toDateString())['totals']['book_value'])->toBe('0.00')
        ->and(fn () => ($this->service)()->dispose($disposed, ['disposal_date' => $disposed->disposed_at->toDateString(), 'gain_loss_account_id' => account('4101')->id]))->toThrow(AccountingException::class);
});

it('books a loss when an asset is scrapped', function (): void {
    $asset = ($this->make)();
    $disposed = ($this->service)()->dispose($asset, ['disposal_date' => $this->start->copy()->addMonths(2)->toDateString(), 'gain_loss_account_id' => account('5102')->id]);

    expect($disposed->disposal_gain_loss)->toBe('-10000.00');
});

it('rejects wrong accounts and a salvage value above the cost', function (): void {
    expect(fn () => ($this->make)(['expense_account_id' => account('1101')->id]))->toThrow(ValidationException::class)
        ->and(fn () => ($this->make)(['salvage_value' => '13000']))->toThrow(ValidationException::class)
        ->and(fn () => ($this->make)(['accumulated_account_id' => account('1205')->id]))->toThrow(ValidationException::class);
});

it('locks the money fields once depreciation exists and refuses to delete a used asset', function (): void {
    $asset = ($this->make)();
    ($this->service)()->run(($this->monthEnd)(0));
    $data = ($this->service)()->validate([...$asset->only(['code', 'name', 'method', 'useful_life_months', 'asset_account_id', 'accumulated_account_id', 'expense_account_id']), 'acquisition_date' => $asset->acquisition_date->toDateString(), 'cost' => '13000', 'salvage_value' => '0'], $asset);

    expect(fn () => ($this->service)()->update($asset, $data))->toThrow(AccountingException::class)
        ->and(fn () => ($this->service)()->delete($asset))->toThrow(AccountingException::class);
    expect(($this->service)()->update($asset, [...$data, 'cost' => '12000', 'name' => 'Server rack'])->name)->toBe('Server rack');
});

it('serves the register, the asset page and the depreciation run over the web and the API', function (): void {
    $asset = ($this->make)(['offset_account_id' => account('1101')->id]);

    $this->get('/accounting/fixed-assets')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/fixed-assets/index')->where('register.totals.cost', '12000.00')->where('reconcile.difference', '0.00'));
    $this->get('/accounting/fixed-assets/create')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/fixed-assets/form')->where('asset', null));
    $this->get("/accounting/fixed-assets/{$asset->id}")->assertOk()->assertInertia(fn ($page) => $page->component('accounting/fixed-assets/show')->where('asset.code', 'EQ-1'));
    $this->get('/accounting/fixed-assets/depreciation?up_to='.($this->monthEnd)(1))->assertOk()->assertInertia(fn ($page) => $page->component('accounting/fixed-assets/depreciation')->where('preview.total', '2000.00'));
    $this->post('/accounting/fixed-assets/depreciation', ['up_to' => ($this->monthEnd)(1)])->assertRedirect()->assertSessionHas('success');

    Sanctum::actingAs($this->accountant);
    $this->getJson('/api/v1/accounting/fixed-assets')->assertOk()->assertJsonPath('data.totals.accumulated', '2000.00');
    $this->postJson('/api/v1/accounting/fixed-assets', ['code' => 'EQ-2', 'name' => 'Laptop', 'acquisition_date' => $this->start->toDateString(), 'cost' => 600, 'useful_life_months' => 6, 'method' => 'straight_line', 'asset_account_id' => account('1205')->id, 'accumulated_account_id' => account('1206')->id, 'expense_account_id' => account('5114')->id])->assertCreated()->assertJsonPath('data.code', 'EQ-2');
    $this->postJson('/api/v1/accounting/fixed-assets/depreciation', ['up_to' => ($this->monthEnd)(2)])->assertOk()->assertJsonPath('data.months', 3);
    $this->postJson("/api/v1/accounting/fixed-assets/{$asset->id}/dispose", ['disposal_date' => $this->start->copy()->addMonths(4)->toDateString(), 'gain_loss_account_id' => account('5102')->id])->assertOk()->assertJsonPath('data.status', 'disposed');
    $this->deleteJson("/api/v1/accounting/fixed-assets/{$asset->id}")->assertStatus(422);
});
