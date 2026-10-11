<x-accounting::app-layout title="Account roles">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Account roles</h2><a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a></div></x-slot>
    <div class="py-6"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Which account the modules use for cash, bank, salaries, receivables and so on. Chart: {{ $presets[$chart] ?? $chart }}.</p>
        <form method="POST" action="{{ route('settings.account-roles.update') }}" class="space-y-4">
            @csrf @method('PUT')
            <div class="bg-white shadow rounded-lg divide-y">
                @foreach ($roles as $row)
                    <label class="grid gap-2 px-5 py-3 text-sm md:grid-cols-[18rem_1fr] md:items-start">
                        <span><span class="font-medium text-gray-900">{{ $row['label'] }}</span>
                            @if($row['overridden'])<span class="block text-xs text-amber-700">Fixed in the configuration ({{ $row['config'] }})</span>@endif</span>
                        @php($chosen = collect($row['accounts'])->pluck('id')->all())
                        @if ($row['overridden'])
                            <span class="text-gray-700">{{ collect($row['accounts'])->map(fn ($account) => $account['account_code'].' '.$account['account_name'])->implode(', ') ?: '—' }}</span>
                        @elseif ($row['multiple'])
                            <select name="roles[{{ $row['role'] }}][]" multiple size="6" class="border-gray-300 rounded-md shadow-sm w-full text-sm">
                                @foreach ($accounts as $account)@if ($row['groups'] || ! $account->is_group)<option value="{{ $account->id }}" @selected(in_array($account->id, $chosen))>{{ $account->account_code }} {{ $account->account_name }}{{ $account->is_group ? ' (group)' : '' }}</option>@endif @endforeach
                            </select>
                        @else
                            <select name="roles[{{ $row['role'] }}][]" class="border-gray-300 rounded-md shadow-sm w-full text-sm">
                                <option value="">— the chart's default —</option>
                                @foreach ($accounts as $account)@if ($row['groups'] || ! $account->is_group)<option value="{{ $account->id }}" @selected(in_array($account->id, $chosen))>{{ $account->account_code }} {{ $account->account_name }}{{ $account->is_group ? ' (group)' : '' }}</option>@endif @endforeach
                            </select>
                        @endif
                    </label>
                @endforeach
            </div>
            <button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Save</button>
        </form>
    </div></div>
</x-accounting::app-layout>
