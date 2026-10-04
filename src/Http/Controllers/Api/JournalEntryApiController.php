<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Actions\VoidJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\Concerns\ResolvesPerPage;
use Alimarchal\LaravelChartOfAccounts\Http\Requests\SimpleJournalEntryRequest;
use Alimarchal\LaravelChartOfAccounts\Http\Requests\StoreJournalEntryRequest;
use Alimarchal\LaravelChartOfAccounts\Http\Requests\UpdateJournalEntryRequest;
use Alimarchal\LaravelChartOfAccounts\Http\Resources\JournalEntryResource;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\JournalApprovalService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Services\SimpleJournalService;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class JournalEntryApiController extends Controller
{
    use ResolvesPerPage;

    private const RELATIONS = ['lines.account', 'lines.costCenter', 'currency', 'accountingPeriod', 'voucherType'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'filter.entry_date_from' => ['nullable', 'date'],
            'filter.entry_date_to' => ['nullable', 'date'],
            'include' => ['nullable', 'in:lines'],
        ]);

        return JournalEntryResource::collection(
            QueryBuilder::for(JournalEntry::query()->with(['currency', 'accountingPeriod', 'voucherType']), request())
                ->allowedFilters(...[
                    AllowedFilter::partial('reference'),
                    AllowedFilter::partial('description'),
                    AllowedFilter::exact('status'),
                    AllowedFilter::exact('currency_id'),
                    AllowedFilter::exact('accounting_period_id'),
                    AllowedFilter::exact('approval_status'),
                    AllowedFilter::callback('entry_date_from', fn ($query, $date) => $query->whereDate('entry_date', '>=', $date)),
                    AllowedFilter::callback('entry_date_to', fn ($query, $date) => $query->whereDate('entry_date', '<=', $date)),
                    AllowedFilter::partial('voucher_number'),
                    AllowedFilter::partial('source_document_number'),
                    AllowedFilter::exact('source_document_type'),
                    AllowedFilter::exact('voucher_type_id'),
                ])
                ->allowedSorts(['entry_date', 'id', 'reference', 'voucher_number', 'created_at'])
                ->when($request->input('include') === 'lines', fn ($query) => $query->with(['lines.account', 'lines.costCenter']))
                ->when(! $request->filled('sort'), fn ($query) => $query->latest('entry_date')->latest('id'))
                ->paginate($this->perPage())
                ->withQueryString()
        );
    }

    public function show(JournalEntry $journalEntry): JournalEntryResource
    {
        return JournalEntryResource::make($journalEntry->load(self::RELATIONS));
    }

    /**
     * Create a journal entry. Send an "Idempotency-Key" header to make retries safe.
     */
    public function store(StoreJournalEntryRequest $request, JournalEntryService $service): JsonResponse
    {
        $data = $request->journalData();

        return $this->idempotent($request, $data, fn (array $keys) => $service->create(array_merge($data, $keys)));
    }

    /**
     * Two-line entry by account codes: { debit_account_code, credit_account_code, amount, post? }.
     */
    public function simple(SimpleJournalEntryRequest $request, SimpleJournalService $service): JsonResponse
    {
        $data = $request->validated();

        return $this->idempotent($request, $data, fn (array $keys) => $service->createBalancedEntry(
            debitAccountCode: $data['debit_account_code'],
            creditAccountCode: $data['credit_account_code'],
            amount: (string) $data['amount'],
            description: $data['description'] ?? null,
            reference: $data['reference'] ?? null,
            post: $request->boolean('post', true),
            entryDate: $data['entry_date'] ?? null,
            idempotencyKey: $keys['idempotency_key'],
            idempotencyHash: $keys['idempotency_hash'],
            sourceDocumentType: $data['source_document_type'] ?? null,
            sourceDocumentNumber: $data['source_document_number'] ?? null,
            sourceDocumentDate: $data['source_document_date'] ?? null,
        ));
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry, JournalEntryService $service): JournalEntryResource
    {
        return JournalEntryResource::make(
            $service->updateDraft($journalEntry, $request->journalData())->load(self::RELATIONS)
        );
    }

    public function post(JournalEntry $journalEntry, JournalEntryService $service): JournalEntryResource
    {
        return JournalEntryResource::make($service->post($journalEntry)->load(self::RELATIONS));
    }

    public function reverse(Request $request, JournalEntry $journalEntry, JournalEntryService $service): JournalEntryResource
    {
        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'reversal_date' => ['nullable', 'date'],
        ]);

        return JournalEntryResource::make(
            $service->reverse($journalEntry, $validated['description'] ?? null, $validated['reversal_date'] ?? null)
                ->load(self::RELATIONS)
        );
    }

    /**
     * Maker: send a draft to a checker.
     */
    public function submit(JournalEntry $journalEntry, JournalApprovalService $approvals): JournalEntryResource
    {
        return JournalEntryResource::make($approvals->submit($journalEntry)->load(self::RELATIONS));
    }

    /**
     * Checker: approve a pending draft — it is posted in the same transaction.
     */
    public function approve(JournalEntry $journalEntry, JournalApprovalService $approvals): JournalEntryResource
    {
        return JournalEntryResource::make($approvals->approve($journalEntry)->load(self::RELATIONS));
    }

    /**
     * Checker: send a pending draft back to its maker with a reason.
     */
    public function reject(Request $request, JournalEntry $journalEntry, JournalApprovalService $approvals): JournalEntryResource
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];

        return JournalEntryResource::make($approvals->reject($journalEntry, $reason)->load(self::RELATIONS));
    }

    public function void(JournalEntry $journalEntry, VoidJournalEntryAction $action): JournalEntryResource
    {
        return JournalEntryResource::make($action->execute($journalEntry)->load(self::RELATIONS));
    }

    /**
     * Idempotency-Key handling (same semantics as Stripe):
     *  - first request with a key creates the entry (201);
     *  - a retry with the same key and the same payload returns the original entry (200, Idempotent-Replayed: true);
     *  - the same key with a different payload is rejected (422).
     *
     * @param  array<string, mixed>  $payload
     * @param  Closure(array{idempotency_key: string|null, idempotency_hash: string|null}): JournalEntry  $create
     */
    private function idempotent(Request $request, array $payload, Closure $create): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return $this->created($create(['idempotency_key' => null, 'idempotency_hash' => null]));
        }

        abort_if(strlen($key) > 100, 422, 'The Idempotency-Key header may not be longer than 100 characters.');

        $hash = hash('sha256', (string) json_encode($this->normalise($payload)).'|'.$request->user()?->getAuthIdentifier());

        if ($replay = $this->replay($key, $hash)) {
            return $replay;
        }

        try {
            return $this->created($create(['idempotency_key' => $key, 'idempotency_hash' => $hash]));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key won the race.
            return $this->replay($key, $hash) ?? abort(409, 'A request with this Idempotency-Key is still being processed.');
        }
    }

    private function replay(string $key, string $hash): ?JsonResponse
    {
        $existing = JournalEntry::query()->where('idempotency_key', $key)->first();

        if (! $existing) {
            return null;
        }

        abort_if($existing->idempotency_hash !== $hash, 422, 'This Idempotency-Key was already used with a different request.');

        return JournalEntryResource::make($existing->load(self::RELATIONS))
            ->response()
            ->setStatusCode(200)
            ->header('Idempotent-Replayed', 'true');
    }

    private function created(JournalEntry $entry): JsonResponse
    {
        return JournalEntryResource::make($entry->load(self::RELATIONS))->response()->setStatusCode(201);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalise(array $payload): array
    {
        ksort($payload);

        return array_map(fn ($value) => is_array($value) ? $this->normalise($value) : (string) $value, $payload);
    }
}
