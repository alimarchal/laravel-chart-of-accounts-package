<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Alimarchal\LaravelChartOfAccounts\Services\BankReconciliationMatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends SimpleAccountingResourceController
{
    protected function model(): string
    {
        return Reconciliation::class;
    }

    protected function routeName(): string
    {
        return 'reconciliations';
    }

    protected function title(): string
    {
        return 'Reconciliation';
    }

    protected function fields(): array
    {
        return [
            ['name' => 'bank_account_id', 'label' => 'Bank Account ID', 'type' => 'number', 'table' => true],
            ['name' => 'statement_date', 'label' => 'Statement Date', 'type' => 'date', 'table' => true],
            ['name' => 'statement_balance', 'label' => 'Statement Balance', 'type' => 'number', 'step' => '0.01', 'table' => true],
            ['name' => 'book_balance', 'label' => 'Book Balance', 'type' => 'number', 'step' => '0.01', 'table' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'completed' => 'Completed', 'void' => 'Void'], 'table' => true],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'bank_account_id' => ['required', 'exists:accounting_bank_accounts,id'],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
            'book_balance' => ['required', 'numeric'],
            'status' => ['required', Rule::in(['draft', 'completed', 'void'])],
        ];
    }

    public function match(Reconciliation $reconciliation, BankReconciliationMatcher $matcher): Response
    {
        return Inertia::render('accounting/reconciliations/match', [
            'reconciliation' => $reconciliation->load('bankAccount'),
            'candidates' => $matcher->candidates($reconciliation),
        ]);
    }

    public function reconcile(Request $request, Reconciliation $reconciliation, BankReconciliationMatcher $matcher): RedirectResponse
    {
        $validated = $request->validate([
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => ['integer', 'exists:accounting_journal_entry_lines,id'],
        ]);

        $matcher->reconcile($reconciliation, $validated['line_ids']);

        return to_route('accounting.reconciliations.show', $reconciliation)->with('success', 'Reconciliation lines matched.');
    }
}
