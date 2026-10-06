<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\PartyPayment;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('runs the receivables cycle in Blade: customer, invoice, receipt, statement and ageing', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
    $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $d = fn (int $day) => $start->copy()->addDays($day - 1)->toDateString();
    account('1103')->forceFill(['control_type' => 'receivables'])->save();
    $tax = TaxCode::query()->create(['code' => 'GST17', 'name' => 'GST', 'kind' => 'output', 'tax_account_id' => account('2104')->id]);
    TaxRate::query()->create(['tax_code_id' => $tax->id, 'rate' => '17', 'effective_from' => $start->copy()->subYear()->toDateString()]);

    $this->get('/accounting')->assertSee('Customers &amp; Suppliers', false)->assertSee('Invoices &amp; Bills', false);
    $this->get('/accounting/parties')->assertOk()->assertSee('No customers or suppliers yet.');
    $this->get('/accounting/parties/create')->assertOk()->assertSee('Payment terms');
    $this->post('/accounting/parties', ['type' => 'customer', 'code' => 'C001', 'name' => 'Acme Traders', 'payment_terms_days' => 30, 'credit_limit' => '500', 'is_active' => 1])->assertRedirect();
    $party = Party::query()->sole();

    $this->get('/accounting/party-documents/create?kind=invoice')->assertOk()->assertSee('Acme Traders')->assertSee('GST17')->assertSee('Add line');
    $this->post('/accounting/party-documents', ['kind' => 'invoice', 'party_id' => $party->id, 'issue_date' => $d(2), 'reference' => 'PO-1', 'lines' => [
        ['chart_of_account_id' => account('4101')->id, 'description' => 'Goods', 'quantity' => 2, 'unit_price' => 500, 'tax_code_id' => $tax->id, 'cost_center_id' => ''],
    ]])->assertRedirect();
    $invoice = PartyDocument::query()->sole();
    $this->get("/accounting/party-documents/{$invoice->id}")->assertOk()->assertSee('(draft #'.$invoice->id.')')->assertSee('1,170.00')->assertSee('Post');
    $this->get("/accounting/party-documents/{$invoice->id}/edit")->assertOk()->assertSee('Save draft');
    $this->post("/accounting/party-documents/{$invoice->id}/post")->assertSessionHas('success', 'Posted.');
    $invoice->refresh();
    $this->get("/accounting/party-documents/{$invoice->id}")->assertSee($invoice->number)->assertSee('open 1,170.00')->assertSee('posted');
    $this->get("/accounting/party-documents/{$invoice->id}/edit")->assertForbidden();
    $this->get('/accounting/party-documents?kind=invoice')->assertSee($invoice->number);

    $this->get('/accounting/party-payments/create?kind=receipt&party_id='.$party->id)->assertOk()->assertSee('Deposited to')->assertSee($invoice->number);
    $this->post('/accounting/party-payments', ['kind' => 'receipt', 'party_id' => $party->id, 'payment_date' => $d(10), 'amount' => 500, 'account_id' => account('1101')->id, 'method' => 'cheque',
        'auto_allocate' => 1, 'allocations' => [['document_id' => $invoice->id, 'amount' => '']]])->assertRedirect();
    $payment = PartyPayment::query()->sole();
    $this->get("/accounting/party-payments/{$payment->id}")->assertOk()->assertSee($payment->number)->assertSee($invoice->number)->assertSee('not applied 0.00');
    $this->get('/accounting/party-payments')->assertSee($payment->number)->assertSee('500.00');

    $this->get('/accounting/parties/'.$party->id)->assertOk()->assertSee('They owe you')->assertSee('670.00')->assertSee('Opening balance')->assertSee('Over the credit limit');
    $this->get('/accounting/receivables/aging?side=receivable&as_of='.$d(40))->assertOk()->assertSee('Acme Traders')->assertSee('670.00')->assertSee('agrees with the control account');
    $this->get('/accounting/receivables/aging?side=payable')->assertOk()->assertSee('Nothing outstanding.');

    $this->post("/accounting/party-payments/{$payment->id}/void")->assertSessionHas('success');
    $this->post("/accounting/party-documents/{$invoice->id}/void")->assertSessionHas('success');
    $this->put('/accounting/parties/'.$party->id, ['type' => 'both', 'code' => 'C001', 'name' => 'Acme', 'is_active' => 1])->assertRedirect();
    $this->get('/accounting/parties')->assertSee('Acme');
});
