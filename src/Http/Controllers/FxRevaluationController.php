<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\ExchangeRate;
use Alimarchal\LaravelChartOfAccounts\Models\FxRevaluation;
use Alimarchal\LaravelChartOfAccounts\Services\FxRevaluationService;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Foreign-currency revaluation and the exchange-rate history, for the React and Blade screens and the API.
 */
class FxRevaluationController extends Controller
{
    public function __construct(private readonly FxRevaluationService $fx) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $base = Currency::query()->where('is_base', true)->first();
        $currencies = Currency::query()->where('is_base', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'exchange_rate_to_base']);
        $revaluations = FxRevaluation::query()->with(['journalEntry:id,voucher_number', 'reversalEntry:id,voucher_number'])->orderByDesc('as_of_date')->orderByDesc('id')->limit(100)->get()
            ->map(fn (FxRevaluation $revaluation): array => $this->present($revaluation))->values();
        $rates = ExchangeRate::query()->with('currency:id,code')->orderByDesc('rate_date')->orderBy('currency_id')->limit(200)->get()
            ->map(fn (ExchangeRate $rate): array => ['id' => $rate->id, 'currency_id' => $rate->currency_id, 'currency' => $rate->currency->code ?? '', 'rate_date' => $rate->rate_date->toDateString(), 'rate' => $rate->rate, 'source' => $rate->source])->values();

        $asOf = $request->string('as_of_date')->toString();
        $override = array_filter((array) $request->input('rates', []), fn ($rate) => is_numeric($rate) && (float) $rate > 0);
        $preview = null;
        $previewError = null;

        if ($asOf !== '' && ! $request->expectsJson()) {
            try {
                $preview = $this->fx->preview($asOf, $override);
            } catch (AccountingException|InvalidFormatException $exception) {
                $previewError = $exception->getMessage();
            }
        }

        $data = [
            'base' => $base?->code,
            'asOf' => $asOf !== '' ? $asOf : now()->toDateString(),
            'preview' => $preview,
            'previewError' => $previewError,
            'currencies' => $currencies->map(fn (Currency $currency): array => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'rate' => (string) ($override[$currency->id] ?? $this->fx->rateOn($currency, $asOf !== '' ? $asOf : now()->toDateString())),
            ])->values(),
            'accounts' => ChartOfAccount::query()->with('accountType:id,code')->where('is_group', false)->where('is_active', true)
                ->whereHas('accountType', fn ($query) => $query->whereIn('code', ['INCOME', 'EXPENSE']))->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_type_id'])
                ->map(fn (ChartOfAccount $account): array => ['id' => $account->id, 'account_code' => $account->account_code, 'account_name' => $account->account_name, 'type' => $account->accountType->code])->values(),
            'revaluations' => $revaluations,
            'rates' => $rates,
            'today' => now()->toDateString(),
            'defaultAccountCode' => config('accounting.fx.gain_loss_account'),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => ['revaluations' => $revaluations, 'rates' => $rates]]);
        }

        return $this->render('index', $data);
    }

    /**
     * What a revaluation would adjust (nothing is posted).
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'as_of_date' => ['required', 'date'],
            'rates' => ['nullable', 'array'],
            'rates.*' => ['nullable', 'numeric', 'gt:0'],
        ]);

        return response()->json(['data' => $this->fx->preview($data['as_of_date'], array_filter((array) ($data['rates'] ?? []), fn ($rate) => $rate !== null && $rate !== ''))]);
    }

    public function show(Request $request, FxRevaluation $fxRevaluation): Response|View|JsonResponse
    {
        $fxRevaluation->load(['lines.account:id,account_code,account_name', 'lines.currency:id,code', 'journalEntry:id,voucher_number', 'reversalEntry:id,voucher_number', 'gainLossAccount:id,account_code,account_name']);
        $data = ['revaluation' => $this->present($fxRevaluation, true)];

        return $request->expectsJson() ? response()->json(['data' => $data['revaluation']]) : $this->render('show', $data);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'as_of_date' => ['required', 'date'],
            'gain_loss_account_id' => ['required', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')],
            'rates' => ['nullable', 'array'],
            'rates.*' => ['nullable', 'numeric', 'gt:0'],
            'auto_reverse' => ['nullable', 'boolean'],
            'reversal_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['rates'] = array_filter((array) ($data['rates'] ?? []), fn ($rate) => $rate !== null && $rate !== '');

        return $this->guard($request, function () use ($request, $data) {
            $revaluation = $this->fx->run($data);

            return $request->expectsJson()
                ? response()->json(['data' => $this->present($revaluation, true)], 201)
                : to_route($this->routeName('fx-revaluation.show'), $revaluation)->with('success', 'Revaluation posted.');
        });
    }

    public function reverse(Request $request, FxRevaluation $fxRevaluation): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['reversal_date' => ['nullable', 'date']]);

        return $this->guard($request, function () use ($request, $fxRevaluation, $data) {
            $revaluation = $this->fx->reverse($fxRevaluation, $data['reversal_date'] ?? null);

            return $request->expectsJson()
                ? response()->json(['data' => $this->present($revaluation->load(['journalEntry:id,voucher_number', 'reversalEntry:id,voucher_number']))])
                : back()->with('success', 'Revaluation reversed.');
        });
    }

    public function storeRate(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'currency_id' => ['required', 'integer', Rule::exists('accounting_currencies', 'id')->where(fn ($query) => $query->whereRaw('is_base = ?', [0]))],
            'rate_date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'source' => ['nullable', 'string', 'max:60'],
        ]);
        // whereDate: SQLite keeps a time part on date columns.
        $rate = ExchangeRate::query()->where('currency_id', $data['currency_id'])->whereDate('rate_date', $data['rate_date'])->first() ?? new ExchangeRate(['currency_id' => $data['currency_id'], 'rate_date' => $data['rate_date']]);
        $rate->fill(['rate' => $data['rate'], 'source' => $data['source'] ?? null])->save();

        return $request->expectsJson()
            ? response()->json(['data' => ['id' => $rate->id, 'currency_id' => $rate->currency_id, 'rate_date' => $rate->rate_date->toDateString(), 'rate' => $rate->rate, 'source' => $rate->source]], 201)
            : back()->with('success', 'Rate saved.');
    }

    public function destroyRate(Request $request, ExchangeRate $exchangeRate): RedirectResponse|JsonResponse
    {
        $exchangeRate->delete();

        return $request->expectsJson() ? response()->json(null, 204) : back()->with('success', 'Rate removed.');
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

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $page, array $props): Response|View
    {
        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::fx-revaluation.'.$page, $props)
            : Inertia::render('accounting/fx-revaluation/'.$page, $props);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FxRevaluation $revaluation, bool $detailed = false): array
    {
        $data = [
            'id' => $revaluation->id,
            'as_of_date' => $revaluation->as_of_date->toDateString(),
            'total_gain' => $revaluation->total_gain,
            'total_loss' => $revaluation->total_loss,
            'journal_entry_id' => $revaluation->journal_entry_id,
            'voucher_number' => $revaluation->relationLoaded('journalEntry') ? $revaluation->journalEntry?->getAttribute('voucher_number') : null,
            'reversal_entry_id' => $revaluation->reversal_entry_id,
            'reversal_voucher_number' => $revaluation->relationLoaded('reversalEntry') ? $revaluation->reversalEntry?->getAttribute('voucher_number') : null,
            'reversal_date' => $revaluation->reversal_date?->toDateString(),
            'notes' => $revaluation->notes,
        ];

        if ($detailed) {
            $data['gain_loss_account'] = $revaluation->relationLoaded('gainLossAccount') && $revaluation->gainLossAccount ? $revaluation->gainLossAccount->account_code.' '.$revaluation->gainLossAccount->account_name : null;
            $data['lines'] = $revaluation->lines->map(fn ($line): array => [
                'account' => $line->relationLoaded('account') && $line->account ? $line->account->account_code.' '.$line->account->account_name : (string) $line->chart_of_account_id,
                'currency' => $line->relationLoaded('currency') ? $line->currency?->code : null,
                'foreign_balance' => $line->foreign_balance,
                'rate' => $line->rate,
                'carrying_base' => $line->carrying_base,
                'revalued_base' => $line->revalued_base,
                'adjustment' => $line->adjustment,
            ])->values()->all();
        }

        return $data;
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
