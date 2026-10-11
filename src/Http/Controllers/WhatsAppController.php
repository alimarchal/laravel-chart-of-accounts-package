<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\WhatsAppMessage;
use Alimarchal\LaravelChartOfAccounts\Services\WhatsAppService;
use Alimarchal\LaravelChartOfAccounts\Support\ListFilters;
use Alimarchal\LaravelChartOfAccounts\Support\WhatsApp\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WhatsApp: what is set up, the messages that were sent and how far each got, and a form to send one (text, or an approved
 * template with its parameters). One controller for the React screen, the Blade screen and the API.
 */
class WhatsAppController extends Controller
{
    public function __construct(private readonly WhatsAppService $whatsapp) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date']]);
        $messages = ListFilters::for(WhatsAppMessage::query(), $request, [
            ListFilters::search('search', ['to_number', 'template', 'body', 'error', 'wamid']),
            ListFilters::exact('status'),
            ListFilters::exact('direction'),
            ListFilters::exact('template'),
            ListFilters::exact('type'),
            ListFilters::search('number', ['to_number']),
            ListFilters::from('date_from', 'created_at'),
            ListFilters::to('date_to', 'created_at'),
        ], sorts: ['created_at', 'status', 'id'], defaultSort: '-created_at')->orderByDesc('id')->limit(200)->get()->map(fn (WhatsAppMessage $message): array => $this->present($message))->values();
        $props = [
            'messages' => $messages,
            'whatsapp' => $this->status(),
            'templates' => $this->whatsapp->templates(),
            'filters' => ListFilters::current($request, ['search', 'status', 'direction', 'template', 'number', 'date_from', 'date_to']),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $messages, 'whatsapp' => $props['whatsapp'], 'templates' => $props['templates']]);
        }

        return config('accounting.ui_driver') === 'blade' ? view('accounting::whatsapp.index', $props) : Inertia::render('accounting/whatsapp/index', $props);
    }

    /** Send a message: {"to": "+923001234567", "template": "fee_received", "parameters": {...}} or {"to": ..., "text": "..."}. */
    public function send(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:40'], 'text' => ['nullable', 'string', 'max:4096'], 'template' => ['nullable', 'string', 'max:120'],
            'parameters' => ['nullable', 'array'], 'language' => ['nullable', 'string', 'max:12'], 'queue' => ['nullable', 'boolean'],
        ]);

        if (blank($data['text'] ?? null) === blank($data['template'] ?? null)) {
            return $this->fail($request, 'Send either a text or a template.');
        }

        return $this->guard($request, function () use ($data, $request) {
            $queue = (bool) ($data['queue'] ?? false);
            $message = filled($data['template'] ?? null)
                ? ($queue ? $this->whatsapp->queueTemplate($data['to'], $data['template'], (array) ($data['parameters'] ?? []), $data['language'] ?? null) : $this->whatsapp->template($data['to'], $data['template'], (array) ($data['parameters'] ?? []), $data['language'] ?? null))
                : ($queue ? $this->whatsapp->queueText($data['to'], $data['text']) : $this->whatsapp->text($data['to'], $data['text']));

            return $request->expectsJson() ? response()->json(['data' => $this->present($message)], 201) : back()->with('success', $queue ? 'The message is queued.' : 'The message was handed to WhatsApp.');
        });
    }

    /** Try a failed message again. */
    public function retry(Request $request, WhatsAppMessage $message): RedirectResponse|JsonResponse
    {
        abort_unless($message->direction === 'out' && $message->status === 'failed', 422, 'Only a failed message can be sent again.');

        return $this->guard($request, function () use ($request, $message) {
            $message = $this->whatsapp->deliver($message, throw: true);

            return $request->expectsJson() ? response()->json(['data' => $this->present($message)]) : back()->with('success', 'The message was handed to WhatsApp.');
        });
    }

    /** Sandbox only (mode fake): move a message on as WhatsApp would — delivered, read or failed. */
    public function simulate(Request $request, WhatsAppMessage $message): RedirectResponse|JsonResponse
    {
        abort_unless($this->whatsapp->mode() === 'fake', 404);
        $data = $request->validate(['status' => ['required', 'in:delivered,read,failed']]);
        $status = ['id' => $message->wamid, 'status' => $data['status'], 'timestamp' => now()->timestamp];

        if ($data['status'] === 'failed') {
            $status['errors'] = [['code' => 131026, 'title' => 'Message undeliverable', 'message' => 'Message undeliverable']];
        }

        $this->whatsapp->applyStatus($status);

        return $request->expectsJson() ? response()->json(['data' => $this->present($message->refresh())]) : back()->with('success', "The message is now {$data['status']}.");
    }

    /** Sandbox only (mode fake): the customer writes back, which opens the 24-hour window for free text. */
    public function simulateReply(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->whatsapp->mode() === 'fake', 404);
        $data = $request->validate(['from' => ['required', 'string', 'max:40'], 'text' => ['nullable', 'string', 'max:1000']]);

        return $this->guard($request, function () use ($data, $request) {
            $number = PhoneNumber::normalise($data['from']);
            $message = $this->whatsapp->receive(['from' => $number, 'id' => 'wamid.FAKEIN'.strtoupper(Str::random(20)), 'type' => 'text', 'text' => ['body' => $data['text'] ?? 'Hello'], 'timestamp' => (string) now()->timestamp]);

            return $request->expectsJson() ? response()->json(['data' => $message === null ? null : $this->present($message)], 201) : back()->with('success', 'A reply from the customer was recorded: free text can be sent for 24 hours.');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function status(): array
    {
        return [
            'enabled' => $this->whatsapp->enabled(), 'mode' => $this->whatsapp->mode(), 'api_version' => (string) config('accounting.whatsapp.api_version', 'v25.0'),
            'checks' => $this->whatsapp->checks(), 'ready' => collect($this->whatsapp->checks())->every(fn (array $check): bool => ! $check['needed'] || $check['set']),
            'webhook_url' => Route::has('api.accounting.whatsapp.webhook.receive') ? route('api.accounting.whatsapp.webhook.receive') : null,
            'default_country_code' => (string) config('accounting.whatsapp.default_country_code', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WhatsAppMessage $message): array
    {
        return [
            'id' => $message->id, 'direction' => $message->direction, 'to' => $message->to_number, 'type' => $message->type, 'template' => $message->template, 'language' => $message->language,
            'body' => $message->body, 'status' => $message->status, 'wamid' => $message->wamid, 'error_code' => $message->error_code, 'error' => $message->error, 'attempts' => $message->attempts,
            'mode' => $message->mode, 'related_type' => $message->related_type, 'related_id' => $message->related_id,
            'sent_at' => $message->sent_at?->toISOString(), 'delivered_at' => $message->delivered_at?->toISOString(), 'read_at' => $message->read_at?->toISOString(), 'failed_at' => $message->failed_at?->toISOString(),
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    /**
     * @param  \Closure(): (RedirectResponse|JsonResponse)  $action
     */
    private function guard(Request $request, \Closure $action): RedirectResponse|JsonResponse
    {
        try {
            return $action();
        } catch (AccountingException $exception) {
            return $this->fail($request, $exception->getMessage());
        }
    }

    private function fail(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message], 422) : back()->with('error', $message);
    }
}
