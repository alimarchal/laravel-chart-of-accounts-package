<x-accounting::app-layout title="WhatsApp">
    <x-slot name="header"><x-accounting::page-header title="WhatsApp" :showSearch="false" :showRefresh="false" :showPrint="false" backRoute="accounting.dashboard"></x-accounting::page-header></x-slot>
    @php($tone = ['queued' => 'bg-gray-100 text-gray-700', 'sent' => 'bg-sky-100 text-sky-800', 'delivered' => 'bg-emerald-100 text-emerald-800', 'read' => 'bg-emerald-200 text-emerald-900', 'failed' => 'bg-red-100 text-red-700', 'received' => 'bg-violet-100 text-violet-800'])
    @php($canSend = $whatsapp['enabled'] && auth()->user()?->can('whatsapp.send'))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
        @unless($whatsapp['enabled'])<div class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">WhatsApp is switched off. Set ACCOUNTING_WHATSAPP_ENABLED=true in .env to send messages.</div>@endunless
        @if($whatsapp['enabled'] && $whatsapp['mode'] === 'fake')<div class="rounded-md bg-sky-50 border border-sky-200 p-3 text-sm text-sky-800">Sandbox (mode fake): nothing leaves this server. Messages are accepted locally and you can move them on by hand. Set ACCOUNTING_WHATSAPP_MODE=live and the token and phone number ID to send for real.</div>@endif

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="bg-white shadow rounded-lg p-4 text-sm space-y-2">
                <h3 class="font-semibold text-gray-800">Set-up · {{ $whatsapp['mode'] }} · Graph API {{ $whatsapp['api_version'] }}</h3>
                @foreach($whatsapp['checks'] as $check)
                    <div class="flex items-start justify-between gap-3 border-t pt-2"><div><p class="font-mono text-xs">{{ $check['env'] }}</p><p class="text-xs text-gray-500">{{ $check['what'] }}</p></div>
                        <x-accounting::status-pill :status="$check['set'] ? 'set' : ($check['needed'] ? 'missing' : 'optional')" /></div>
                @endforeach
                @if($whatsapp['webhook_url'])<p class="border-t pt-2 text-xs text-gray-500">Webhook URL for Meta: <span class="font-mono text-gray-800">{{ $whatsapp['webhook_url'] }}</span></p>@endif
                <p class="text-xs font-medium {{ $whatsapp['ready'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $whatsapp['ready'] ? 'Ready.' : 'Not ready: fill in the missing settings.' }}</p>
            </div>

            <div class="bg-white shadow rounded-lg p-4 text-sm" id="wa-send">
                <h3 class="font-semibold text-gray-800 mb-3">Send a message</h3>
                <form method="POST" action="{{ route('accounting.whatsapp.send') }}" class="space-y-3">@csrf
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">Phone number
                        <input name="to" value="{{ old('to') }}" placeholder="+{{ $whatsapp['default_country_code'] }} 300 1234567" required class="rounded-md border-gray-300 text-sm">
                    </label>
                    <div class="flex gap-4 text-xs">
                        <label class="flex items-center gap-1"><input type="radio" name="kind" value="template" checked> Approved template</label>
                        <label class="flex items-center gap-1"><input type="radio" name="kind" value="text"> Free text (within 24 hours of their reply)</label>
                    </div>
                    <div data-part="template" class="space-y-2">
                        <select name="template" class="rounded-md border-gray-300 text-sm">
                            @foreach($templates as $t)<option value="{{ $t['key'] }}">{{ $t['key'] }} ({{ $t['language'] }})</option>@endforeach
                        </select>
                        @foreach($templates as $t)
                            <div data-template="{{ $t['key'] }}" class="space-y-2">
                                @if($t['example'])<p class="rounded-md bg-gray-50 p-2 text-xs text-gray-600">{{ $t['example'] }}</p>@endif
                                @foreach($t['parameters'] as $name)
                                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">{{ $name }}<input name="parameters[{{ $name }}]" required class="rounded-md border-gray-300 text-sm"></label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <textarea data-part="text" name="text" class="hidden w-full rounded-md border-gray-300 text-sm min-h-20" placeholder="Your message" required></textarea>
                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="queue" value="1"> Send through the queue</label>
                    <button @disabled(! $canSend) class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest disabled:opacity-50">Send</button>
                </form>
                <script>
                    (function () {
                        var box = document.getElementById('wa-send');
                        var sync = function () {
                            var text = box.querySelector('input[name=kind]:checked').value === 'text';
                            var template = box.querySelector('select[name=template]').value;
                            box.querySelector('[data-part=template]').classList.toggle('hidden', text);
                            box.querySelectorAll('[data-part=template] select, [data-part=template] input').forEach(function (el) { el.disabled = text; });
                            var area = box.querySelector('[data-part=text]');
                            area.classList.toggle('hidden', !text);
                            area.disabled = !text;
                            box.querySelectorAll('[data-template]').forEach(function (part) {
                                var on = part.getAttribute('data-template') === template;
                                part.classList.toggle('hidden', !on);
                                part.querySelectorAll('input').forEach(function (el) { el.disabled = text || !on; });
                            });
                        };
                        box.addEventListener('change', sync);
                        sync();
                    })();
                </script>
                @if($whatsapp['mode'] === 'fake' && $canSend)
                    <form method="POST" action="{{ route('accounting.whatsapp.simulate-reply') }}" class="mt-4 flex flex-wrap items-center gap-2 border-t pt-3 text-xs">@csrf
                        <span class="text-gray-500">Sandbox: the customer writes back</span>
                        <input name="from" placeholder="Their number" required class="rounded-md border-gray-300 text-xs"><input name="text" value="Thank you" class="rounded-md border-gray-300 text-xs">
                        <button class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Customer replies</button>
                    </form>
                @endif
            </div>
        </div>

        <x-accounting::list-filters :action="route('accounting.whatsapp.index')" :values="$filters" :fields="[
            ['name' => 'search', 'label' => 'Search', 'placeholder' => 'Number, text, template or error'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['queued' => 'Queued', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed', 'received' => 'Received']],
            ['name' => 'direction', 'label' => 'Direction', 'type' => 'select', 'options' => ['out' => 'Sent', 'in' => 'Received']],
            ['name' => 'template', 'label' => 'Template'],
            ['name' => 'number', 'label' => 'Number'],
            ['name' => 'date_from', 'label' => 'From', 'type' => 'date'],
            ['name' => 'date_to', 'label' => 'To', 'type' => 'date'],
        ]" />
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">When</th><th class="py-2 px-3 text-left font-medium text-gray-600">To / from</th><th class="py-2 px-3 text-left font-medium text-gray-600">Message</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($messages as $message)
                <tr class="border-t align-top"><td class="py-2 px-3 text-xs">{{ str_replace('T', ' ', substr($message['created_at'] ?? '', 0, 16)) }}</td>
                    <td class="py-2 px-3 font-mono text-xs">{{ $message['direction'] === 'in' ? '←' : '→' }} {{ $message['to'] }}</td>
                    <td class="py-2 px-3">@if($message['template'])<span class="font-medium">{{ $message['template'] }}</span> @endif<span class="text-xs text-gray-500">{{ $message['body'] }}</span>@if($message['error'])<p class="mt-1 max-w-md text-xs text-red-700">{{ $message['error'] }}</p>@endif @if($message['wamid'])<p class="font-mono text-[10px] text-gray-400">{{ $message['wamid'] }}</p>@endif</td>
                    <td class="py-2 px-3"><x-accounting::status-pill :status="$message['status']" /></td>
                    <td class="py-2 px-3 text-right space-x-1">
                        @if($canSend && $message['status'] === 'failed')<form method="POST" action="{{ route('accounting.whatsapp.retry', $message['id']) }}" class="inline">@csrf<button class="px-3 py-1.5 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Retry</button></form>@endif
                        @if($canSend && $whatsapp['mode'] === 'fake' && $message['direction'] === 'out' && in_array($message['status'], ['sent', 'delivered'], true))<form method="POST" action="{{ route('accounting.whatsapp.simulate', $message['id']) }}" class="inline">@csrf<input type="hidden" name="status" value="{{ $message['status'] === 'sent' ? 'delivered' : 'read' }}"><button class="px-3 py-1.5 text-xs text-gray-600 hover:underline">Mark {{ $message['status'] === 'sent' ? 'delivered' : 'read' }}</button></form>@endif
                    </td></tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-gray-500">No messages yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
