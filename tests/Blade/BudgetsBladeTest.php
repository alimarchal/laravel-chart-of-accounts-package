<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Budget;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('plans, approves and reviews a budget in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $approver = User::factory()->create();
    $approver->assignRole('approver');
    $this->actingAs($accountant);
    $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    journal(['5102' => 400, '1101' => -400], $start->copy()->addDays(5)->toDateString());

    $this->get('/accounting')->assertSee('Budgets');
    $this->get('/accounting/budgets')->assertOk()->assertSee('No budgets yet.');
    $this->get('/accounting/budgets/create')->assertOk()->assertSee('5102 - Rent Expense')->assertSee('By month');

    $this->post('/accounting/budgets', ['name' => 'Annual', 'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addMonths(11)->toDateString(),
        'lines' => [['chart_of_account_id' => account('5102')->id, 'cost_center_id' => '', 'annual' => '12000']]])->assertRedirect();
    $budget = Budget::query()->sole();

    $this->get("/accounting/budgets/{$budget->id}")->assertOk()->assertSee('5102 Rent Expense')->assertSee('12,000.00')->assertSee('400.00')->assertSee('Edit')->assertDontSee('>Approve<', false);
    $this->get("/accounting/budgets/{$budget->id}/edit")->assertOk()->assertSee('Save changes');
    $this->put("/accounting/budgets/{$budget->id}", ['name' => 'Annual 2', 'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addMonths(11)->toDateString(),
        'lines' => [['chart_of_account_id' => account('5102')->id, 'cost_center_id' => '', 'amounts' => [$start->format('Y-m') => '1000']]]])->assertRedirect();
    $this->post("/accounting/budgets/{$budget->id}/approve")->assertForbidden();

    $this->actingAs($approver);
    $this->post("/accounting/budgets/{$budget->id}/approve")->assertSessionHas('success', 'Budget approved.');
    $this->get("/accounting/budgets/{$budget->id}")->assertSee('approved')->assertSee('Close');
    $this->get("/accounting/budgets/{$budget->id}/edit")->assertForbidden();

    $this->actingAs($accountant);
    $this->post("/accounting/budgets/{$budget->id}/copy", ['name' => 'Next', 'uplift_percent' => 10])->assertRedirect();
    $this->get('/accounting/budgets')->assertSee('Annual 2')->assertSee('Next');
});
