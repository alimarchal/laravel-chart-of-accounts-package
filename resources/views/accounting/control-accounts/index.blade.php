<x-accounting::app-layout title="Control Accounts">
    <x-slot name="header">
        <x-accounting::page-header title="Control Accounts" :showSearch="false" backRoute="accounting.dashboard" />
    </x-slot>

    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif

        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="max-w-3xl text-sm text-gray-600">Accounts that summarise a sub-ledger (customers, suppliers, stock …). Only that module posts to them, so their balance always agrees with the sub-ledger; manual entries need the <code>control-accounts.post-manual</code> permission.</p>
            @can('control-accounts.manage')
                <form method="POST" action="{{ route('accounting.control-accounts.recommended') }}" onsubmit="return confirm(@js("Mark these accounts as control accounts?\n\n".collect($recommended)->map(fn ($type, $code) => $code.' → '.($types[$type] ?? $type))->implode("\n")))">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">Recommended setup</button>
                </form>
            @endcan
        </div>

        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Account</th>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Controls</th>
                    <th class="py-2 px-3 text-right font-medium text-gray-600">Balance</th>
                    <th class="py-2 px-3 text-right font-medium text-gray-600">Manual postings</th>
                    <th class="py-2 px-3 text-right font-medium text-gray-600">Actions</th>
                </tr></thead>
                <tbody>
                    @forelse($controlAccounts as $account)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-2 px-3"><span class="font-mono">{{ $account['account_code'] }}</span> {{ $account['account_name'] }}</td>
                            <td class="py-2 px-3"><span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700">{{ $account['control_label'] }}</span></td>
                            <td class="py-2 px-3 text-right font-mono">{{ number_format((float) $account['balance'], 2) }}</td>
                            <td class="py-2 px-3 text-right">
                                @if($account['manual_postings'])
                                    <a href="{{ route('accounting.control-accounts.index', ['account' => $account['id']]) }}" class="font-semibold text-amber-700 hover:underline">{{ $account['manual_postings'] }}</a>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="py-2 px-3 text-right">
                                @can('control-accounts.manage')
                                    <form method="POST" action="{{ route('accounting.chart-of-accounts.control-type', $account['id']) }}" class="inline" onsubmit="return confirm(@js('Stop treating '.$account['account_code'].' '.$account['account_name'].' as a control account?'))">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="control_type" value="">
                                        <button type="submit" class="text-red-700 hover:underline">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">No control accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($selected)
            <div class="bg-white shadow rounded-lg">
                <div class="border-b p-4">
                    <h3 class="font-semibold text-gray-700">Manual postings to {{ $selected->account_code }} {{ $selected->account_name }}</h3>
                    <p class="text-sm text-gray-500">Posted entries that did not come from the account's module. Each should be a documented controller adjustment.</p>
                </div>
                <table class="min-w-full text-sm"><tbody>
                    @foreach($manualPostings as $entry)
                        <tr class="border-t">
                            <td class="py-2 px-3 font-mono"><a href="{{ route('accounting.journal-entries.show', $entry['id']) }}" class="hover:underline">{{ $entry['voucher_number'] ?? '#'.$entry['id'] }}</a></td>
                            <td class="py-2 px-3">{{ \Illuminate\Support\Carbon::parse($entry['entry_date'])->format('Y-m-d') }}</td>
                            <td class="py-2 px-3">{{ $entry['reference'] }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ $entry['description'] }}</td>
                        </tr>
                    @endforeach
                </tbody></table>
            </div>
        @endif

        @can('control-accounts.manage')
            <form method="POST" action="{{ route('accounting.control-accounts.index') }}" class="bg-white shadow rounded-lg p-4 flex flex-wrap items-end gap-3"
                  onsubmit="this.action = @js(url(trim(config('accounting.route_prefix', 'accounting'), '/').'/chart-of-accounts')) + '/' + this.account_id.value + '/control-type'; return !!this.account_id.value;">
                @csrf @method('PUT')
                <div>
                    <label for="account_id" class="block text-sm font-medium text-gray-700">Account</label>
                    <select id="account_id" name="account_id" class="select2 mt-1 rounded-md border-gray-300 text-sm w-80">
                        <option value="">Choose a posting account…</option>
                        @foreach($accounts as $option)
                            <option value="{{ $option->id }}">{{ $option->account_code }} - {{ $option->account_name }}{{ $option->control_type ? ' ('.$types[$option->control_type].')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="control_type" class="block text-sm font-medium text-gray-700">Controls</label>
                    <select id="control_type" name="control_type" required class="mt-1 rounded-md border-gray-300 text-sm">
                        <option value="">Choose…</option>
                        @foreach($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600">Mark as control account</button>
            </form>
        @endcan
    </div></div>
</x-accounting::app-layout>
