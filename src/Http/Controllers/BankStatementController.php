<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatement;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatementLine;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Alimarchal\LaravelChartOfAccounts\Services\BankStatementService;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bank statement import, matching and reconciliation for the React and Blade screens and the API.
 *
 * Screens: upload → preview (nothing saved; the parsed file is kept for 30 minutes) → confirm. API: one request,
 * dry_run=1 for the preview.
 */
class BankStatementController extends Controller
{
    public function __construct(private readonly BankStatementService $statements) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $statements = BankStatement::query()->with('bankAccount:id,account_name,bank_name')->withCount([
            'lines as unmatched_count' => fn ($query) => $query->where('status', 'unmatched'),
        ])->orderByDesc('to_date')->orderByDesc('id')->limit(200)->get()->map(fn (BankStatement $statement): array => $this->presentStatement($statement))->values();

        return $request->expectsJson() ? response()->json(['data' => $statements]) : $this->render('index', ['statements' => $statements]);
    }

    public function create(Request $request): Response|View
    {
        $token = (string) $request->query('preview', '');
        $stash = $token !== '' ? $this->statements->stashed($token) : null;

        return $this->render('import', [
            'banks' => $this->banks(),
            'preview' => $stash === null ? null : [
                ...$stash['parsed'],
                'token' => $stash['parsed']['summary']['error'] === 0 ? $token : null,
                'filename' => $stash['filename'],
                'bank_account_id' => $stash['bank_account_id'],
            ],
            'matchDays' => (int) config('accounting.bank_import.match_days', 5),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $data = $this->validateUpload($request);

        try {
            $bank = $this->bank($data['bank_account_id']);
            $parsed = $this->statements->parse($bank, $this->statements->readUpload($data['file']));
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        $token = $this->statements->stash($bank, $parsed, $data['file']->getClientOriginalName());

        return to_route($this->routeName('bank-statements.import'), ['preview' => $token]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'closing_balance' => ['nullable', 'numeric']]);
        $stash = $this->statements->stashed($data['token']);

        if ($stash === null || $stash['parsed']['summary']['error'] > 0) {
            return to_route($this->routeName('bank-statements.import'))->with('error', 'The preview has expired. Upload the file again.');
        }

        try {
            /** @var array{lines: list<array<string, mixed>>, summary: array{new: int, duplicate: int, error: int}, from_date: string|null, to_date: string|null, closing_balance: string|null} $parsed */
            $parsed = $stash['parsed'];
            $statement = $this->statements->import($this->bank($stash['bank_account_id']), $parsed, $stash['filename'], isset($data['closing_balance']) ? (string) $data['closing_balance'] : null);
        } catch (AccountingException $exception) {
            return to_route($this->routeName('bank-statements.import'))->with('error', $exception->getMessage());
        }

        $this->statements->forget($data['token']);

        return to_route($this->routeName('bank-statements.show'), $statement)->with('success', "Statement imported: {$statement->lines_count} transactions.");
    }

    /**
     * API: POST /bank-statements/import (multipart: file, bank_account_id, dry_run, closing_balance, auto_match).
     */
    public function api(Request $request): JsonResponse
    {
        $data = $this->validateUpload($request, true);
        $bank = $this->bank($data['bank_account_id']);
        $parsed = $this->statements->parse($bank, $this->statements->readUpload($data['file']));

        if ($data['dry_run'] || $parsed['summary']['error'] > 0) {
            return response()->json([
                'message' => $parsed['summary']['error'] > 0 ? 'Some rows have errors: nothing was imported.' : 'Preview only: nothing was saved.',
                'data' => $parsed,
            ], $parsed['summary']['error'] > 0 ? 422 : 200);
        }

        $statement = $this->statements->import($bank, $parsed, $data['file']->getClientOriginalName(), $data['closing_balance']);
        $matched = $request->boolean('auto_match') ? $this->statements->autoMatch($statement) : 0;

        return response()->json(['message' => 'Statement imported.', 'data' => [...$this->presentStatement($statement->refresh()), 'skipped_duplicates' => $parsed['summary']['duplicate'], 'auto_matched' => $matched]], 201);
    }

    public function show(Request $request, BankStatement $bankStatement): Response|View|JsonResponse
    {
        $bankStatement->load('bankAccount:id,account_name,bank_name,chart_of_account_id');
        $lines = $bankStatement->lines()->with(['journalEntry:id,voucher_number,status'])->get()->map(fn (BankStatementLine $line): array => $this->presentLine($line))->values();
        $data = [
            'statement' => $this->presentStatement($bankStatement),
            'lines' => $lines,
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->whereKeyNot((int) $bankStatement->bankAccount->chart_of_account_id)->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'matchDays' => (int) config('accounting.bank_import.match_days', 5),
        ];

        return $request->expectsJson() ? response()->json(['data' => ['statement' => $data['statement'], 'lines' => $lines]]) : $this->render('show', $data);
    }

    public function update(Request $request, BankStatement $bankStatement): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['closing_balance' => ['required', 'numeric']]);

        return $this->guard($request, function () use ($request, $bankStatement, $data) {
            if ($bankStatement->reconciliation_id !== null) {
                throw new AccountingException('A reconciled statement cannot be changed.');
            }

            $bankStatement->forceFill(['closing_balance' => $data['closing_balance']])->save();

            return $this->answer($request, $bankStatement->refresh(), 'Closing balance saved.');
        });
    }

    public function destroy(Request $request, BankStatement $bankStatement): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $bankStatement) {
            $this->statements->delete($bankStatement);

            return $request->expectsJson() ? response()->json(null, 204) : to_route($this->routeName('bank-statements.index'))->with('success', 'Statement deleted.');
        });
    }

    public function autoMatch(Request $request, BankStatement $bankStatement): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $bankStatement) {
            $matched = $this->statements->autoMatch($bankStatement);
            $message = $matched === 0 ? 'No transaction has exactly one matching ledger line.' : "{$matched} transactions matched.";

            return $request->expectsJson() ? response()->json(['message' => $message, 'data' => ['matched' => $matched]]) : back()->with('success', $message);
        });
    }

    public function reconcile(Request $request, BankStatement $bankStatement): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $bankStatement) {
            $reconciliation = $this->statements->reconcile($bankStatement);

            return $request->expectsJson()
                ? response()->json(['message' => 'Statement reconciled.', 'data' => ['reconciliation_id' => $reconciliation->id, 'status' => $reconciliation->status]])
                : back()->with('success', 'Statement reconciled.');
        });
    }

    public function candidates(BankStatementLine $bankStatementLine): JsonResponse
    {
        $candidates = $this->statements->candidates($bankStatementLine, (int) config('accounting.bank_import.match_days', 5))->map(fn (JournalEntryLine $line): array => [
            'id' => $line->id,
            'date' => $line->journalEntry->entry_date->toDateString(),
            'voucher_number' => $line->journalEntry->getAttribute('voucher_number'),
            'reference' => $line->journalEntry->reference,
            'description' => $line->description ?? $line->journalEntry->description,
            'debit' => $line->debit,
            'credit' => $line->credit,
        ])->values();

        return response()->json(['data' => $candidates]);
    }

    public function match(Request $request, BankStatementLine $bankStatementLine): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['journal_entry_line_id' => ['required', 'integer', CompanyRule::existsLine()]]);

        return $this->guard($request, fn () => $this->answerLine($request, $this->statements->match($bankStatementLine, (int) $data['journal_entry_line_id']), 'Matched.'));
    }

    public function unmatch(Request $request, BankStatementLine $bankStatementLine): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answerLine($request, $this->statements->unmatch($bankStatementLine), 'Unmatched.'));
    }

    public function ignore(Request $request, BankStatementLine $bankStatementLine): RedirectResponse|JsonResponse
    {
        $ignored = $request->has('ignored') ? $request->boolean('ignored') : true;

        return $this->guard($request, fn () => $this->answerLine($request, $this->statements->ignore($bankStatementLine, $ignored), $ignored ? 'Ignored.' : 'Restored.'));
    }

    public function createEntry(Request $request, BankStatementLine $bankStatementLine): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'chart_of_account_id' => ['required', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->guard($request, function () use ($request, $bankStatementLine, $data) {
            $entry = $this->statements->createEntry($bankStatementLine, (int) $data['chart_of_account_id'], $data['description'] ?? null);
            $message = $entry->status === 'posted' ? 'Entry posted and matched.' : 'Entry created as a draft and matched: post it to complete.';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'data' => ['journal_entry_id' => $entry->id, 'status' => $entry->status, 'line' => $this->presentLine($bankStatementLine->refresh()->load('journalEntry:id,voucher_number,status'))]], 201)
                : back()->with('success', $message);
        });
    }

    /**
     * @return array{file: UploadedFile, bank_account_id: int, dry_run: bool, closing_balance: string|null}
     */
    private function validateUpload(Request $request, bool $api = false): array
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:'.(int) config('accounting.bank_import.max_size_kb', 5120)],
            'bank_account_id' => ['required', 'integer', CompanyRule::exists('accounting_bank_accounts', 'id')],
            'dry_run' => [$api ? 'nullable' : 'prohibited', 'boolean'],
            'closing_balance' => [$api ? 'nullable' : 'prohibited', 'numeric'],
        ]);

        return ['file' => $data['file'], 'bank_account_id' => (int) $data['bank_account_id'], 'dry_run' => $request->boolean('dry_run'), 'closing_balance' => isset($data['closing_balance']) ? (string) $data['closing_balance'] : null];
    }

    private function bank(int $id): BankAccount
    {
        $bank = BankAccount::query()->findOrFail($id);

        if ($bank->chart_of_account_id === null) {
            throw new AccountingException('Link the bank account to a chart of accounts account first: its transactions are matched to that account.');
        }

        return $bank;
    }

    /**
     * @return Collection<int, array{id: int, name: non-falsy-string}>
     */
    private function banks(): Collection
    {
        return BankAccount::query()->where('is_active', true)->whereNotNull('chart_of_account_id')->orderBy('account_name')->get(['id', 'account_name', 'bank_name', 'account_number'])
            ->map(fn (BankAccount $bank): array => ['id' => $bank->id, 'name' => $bank->account_name.($bank->bank_name ? ' — '.$bank->bank_name : '').' ('.$bank->account_number.')']);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentStatement(BankStatement $statement): array
    {
        return [
            'id' => $statement->id,
            'bank_account_id' => $statement->bank_account_id,
            'bank' => $statement->relationLoaded('bankAccount') ? $statement->bankAccount->account_name : null,
            'file_name' => $statement->file_name,
            'from_date' => $statement->from_date?->toDateString(),
            'to_date' => $statement->to_date?->toDateString(),
            'closing_balance' => $statement->closing_balance,
            'lines_count' => $statement->lines_count,
            'unmatched_count' => isset($statement->getAttributes()['unmatched_count']) ? (int) $statement->getAttributes()['unmatched_count'] : $statement->lines()->where('status', 'unmatched')->count(),
            'reconciliation_id' => $statement->reconciliation_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLine(BankStatementLine $line): array
    {
        return [
            'id' => $line->id,
            'line_no' => $line->line_no,
            'txn_date' => $line->txn_date->toDateString(),
            'description' => $line->description,
            'reference' => $line->reference,
            'deposit' => $line->deposit,
            'withdrawal' => $line->withdrawal,
            'balance' => $line->balance,
            'status' => $line->status,
            'journal_entry_id' => $line->journal_entry_id,
            'voucher_number' => $line->relationLoaded('journalEntry') ? $line->journalEntry?->getAttribute('voucher_number') : null,
            'journal_status' => $line->relationLoaded('journalEntry') ? $line->journalEntry?->getAttribute('status') : null,
        ];
    }

    private function answer(Request $request, BankStatement $statement, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $this->presentStatement($statement)]) : back()->with('success', $message);
    }

    private function answerLine(Request $request, BankStatementLine $line, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $this->presentLine($line->load('journalEntry:id,voucher_number,status'))]) : back()->with('success', $message);
    }

    /**
     * @param  \Closure(): (RedirectResponse|JsonResponse)  $action
     */
    private function guard(Request $request, \Closure $action): RedirectResponse|JsonResponse
    {
        try {
            return $action();
        } catch (AccountingException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $page, array $props): Response|View
    {
        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::bank-statements.'.$page, $props)
            : Inertia::render('accounting/bank-statements/'.$page, $props);
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
