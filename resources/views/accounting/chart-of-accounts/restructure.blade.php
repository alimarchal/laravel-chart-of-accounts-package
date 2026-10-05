<x-accounting::app-layout title="Renumber or merge {{ $account['account_code'] }}">
    <x-slot name="header">
        <x-accounting::page-header :title="$account['account_code'].' '.$account['account_name'].' — renumber or merge'" :showSearch="false" backRoute="accounting.chart-of-accounts.index" />
    </x-slot>

    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        @if ($account['merged_into'])<div class="rounded-md bg-gray-50 border border-gray-200 p-3 text-sm text-gray-700">This account was merged into {{ $account['merged_into'] }}.</div>@endif

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="bg-white shadow rounded-lg p-5 space-y-4">
                <h3 class="font-semibold text-gray-800">Renumber</h3>
                <form method="GET" action="{{ route('accounting.chart-of-accounts.restructure', $account['id']) }}" class="space-y-3">
                    <div>
                        <x-accounting::label for="renumber_code" value="New code" />
                        <x-accounting::input id="renumber_code" name="renumber_code" type="text" maxlength="30" class="mt-1 block w-full" :value="$renumber['code'] ?? ''" required />
                    </div>
                    @if ($account['is_group'])
                        <input type="hidden" name="with_children" value="0">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="with_children" value="1" @checked($renumber['with_children'] ?? true)> Renumber the sub-accounts sharing its prefix too</label>
                    @endif
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">Preview</button>
                </form>

                @if ($renumber)
                    @if ($renumber['error'])
                        <p class="text-sm text-red-700">{{ $renumber['error'] }}</p>
                    @else
                        <table class="min-w-full text-sm border rounded-md">
                            @foreach ($renumber['plan'] as $from => $to)
                                <tr class="border-t"><td class="py-1 px-3 font-mono">{{ $from }}</td><td class="text-gray-400">→</td><td class="py-1 px-3 font-mono font-semibold">{{ $to }}</td></tr>
                            @endforeach
                        </table>
                        <form method="POST" action="{{ route('accounting.chart-of-accounts.renumber', $account['id']) }}">
                            @csrf
                            <input type="hidden" name="account_code" value="{{ $renumber['code'] }}">
                            <input type="hidden" name="with_children" value="{{ $renumber['with_children'] ? 1 : 0 }}">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600">Renumber {{ count($renumber['plan']) }} {{ \Illuminate\Support\Str::plural('account', count($renumber['plan'])) }}</button>
                        </form>
                    @endif
                @endif
            </div>

            <div class="bg-white shadow rounded-lg p-5 space-y-4">
                <h3 class="font-semibold text-gray-800">Merge into another account</h3>
                <p class="text-sm text-gray-600">For duplicates. The balance moves to the other account with a posted transfer entry (per cost center), drafts, sub-accounts and bank accounts follow, and {{ $account['account_code'] }} is deactivated. Accounts of the same type only.</p>
                <form method="GET" action="{{ route('accounting.chart-of-accounts.restructure', $account['id']) }}" class="flex gap-2 items-end">
                    <div class="flex-1">
                        <x-accounting::label for="merge_target" value="Merge into" />
                        <select id="merge_target" name="merge_target" class="select2 mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                            <option value="">Choose an account</option>
                            @foreach ($targets as $option)<option value="{{ $option['id'] }}" @selected(request('merge_target') == $option['id'])>{{ $option['label'] }}</option>@endforeach
                        </select>
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">Preview</button>
                </form>

                @if ($merge)
                    @if ($merge['problems'])
                        <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800"><ul class="list-disc pl-4">@foreach ($merge['problems'] as $problem)<li>{{ $problem }}</li>@endforeach</ul></div>
                    @endif
                    <ul class="text-sm space-y-1">
                        <li>Balance to move: <span class="font-semibold">{{ $merge['balance'] }}</span>
                            @if ($merge['transfers']) ({{ collect($merge['transfers'])->map(fn ($t) => ($t['cost_center'] ?? 'no cost center').': '.$t['amount'].' '.$t['side'])->implode(', ') }}) @else — nothing to transfer @endif</li>
                        <li>Draft lines moved: {{ $merge['draft_lines'] }}</li>
                        <li>Sub-accounts moved: {{ $merge['children'] ? implode(', ', $merge['children']) : 'none' }}</li>
                        <li>Bank accounts moved: {{ $merge['bank_accounts'] ? implode(', ', $merge['bank_accounts']) : 'none' }}</li>
                    </ul>
                    @unless ($merge['problems'])
                        <form method="POST" action="{{ route('accounting.chart-of-accounts.merge', $account['id']) }}" class="grid gap-3 md:grid-cols-2" onsubmit="return confirm('Merge {{ $account['account_code'] }} into {{ $merge['target']['account_code'] }}? This posts a transfer entry and deactivates {{ $account['account_code'] }}.')">
                            @csrf
                            <input type="hidden" name="target_account_id" value="{{ $merge['target']['id'] }}">
                            <div><x-accounting::label for="date" value="Transfer date" /><x-accounting::input id="date" name="date" type="date" class="mt-1 block w-full" :value="$today" /></div>
                            <div><x-accounting::label for="description" value="Narration (optional)" /><x-accounting::input id="description" name="description" type="text" class="mt-1 block w-full" /></div>
                            <div class="md:col-span-2"><button type="submit" class="inline-flex items-center px-4 py-2 bg-red-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-600">Merge into {{ $merge['target']['account_code'] }}</button></div>
                        </form>
                    @endunless
                @endif
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
