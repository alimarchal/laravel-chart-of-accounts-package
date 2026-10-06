<x-accounting::app-layout title="Asset">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $asset['code'] }} · {{ $asset['name'] }} <span class="ml-2 rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-700">{{ $asset['status'] }}</span></h2>
            <div class="flex gap-2">
                @if($asset['status'] === 'active')@can('fixed-assets.update')<a href="{{ route('accounting.fixed-assets.edit', $asset['id']) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Edit</a>@endcan @endif
                @if($asset['status'] === 'active' && ! $asset['locked'])@can('fixed-assets.delete')<form method="POST" action="{{ route('accounting.fixed-assets.destroy', $asset['id']) }}" onsubmit="return confirm('Delete this asset?')">@csrf @method('DELETE')<button class="px-3 py-2 text-red-700 text-xs font-semibold uppercase tracking-widest hover:underline">Delete</button></form>@endcan @endif
                <a href="{{ route('accounting.fixed-assets.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Register</a>
            </div>
        </div>
    </x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <div class="bg-white shadow rounded-lg p-5 grid gap-4 sm:grid-cols-4 text-sm">
            @foreach (['Acquired' => $asset['acquisition_date'], 'In service' => $asset['in_service_date'], 'Cost' => $fmt($asset['cost']), 'Salvage value' => $fmt($asset['salvage_value']), 'Life' => $asset['useful_life_months'].' months', 'Method' => str_replace('_', ' ', $asset['method']), 'Accumulated depreciation' => $fmt($asset['accumulated_depreciation']), 'Book value' => $fmt($asset['book_value'])] as $label => $value)
                <div><p class="text-gray-500">{{ $label }}</p><p class="font-medium tabular-nums">{{ $value }}</p></div>
            @endforeach
        </div>
        @if($asset['status'] === 'disposed')<div class="bg-white shadow rounded-lg p-5 text-sm">Disposed on {{ $asset['disposed_at'] }} for {{ $fmt($asset['disposal_proceeds'] ?? 0) }}; {{ (float) $asset['disposal_gain_loss'] >= 0 ? 'gain' : 'loss' }} of {{ $fmt(abs((float) $asset['disposal_gain_loss'])) }}.</div>@endif
        <div class="bg-white shadow rounded-lg p-5"><h3 class="font-semibold text-gray-800 mb-2">Depreciation booked</h3>
            <table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Month</th><th class="text-right">Amount</th><th class="text-right">Entry</th></tr></thead><tbody>
                @forelse ($history as $row)<tr class="border-t"><td class="py-1">{{ $row['month'] }}</td><td class="text-right tabular-nums">{{ $fmt($row['amount']) }}</td><td class="text-right"><a class="hover:underline" href="{{ route('accounting.journal-entries.show', $row['journal_entry_id']) }}">#{{ $row['journal_entry_id'] }}</a></td></tr>@empty<tr><td colspan="3" class="py-4 text-center text-gray-500">Nothing booked yet.</td></tr>@endforelse
            </tbody></table>
        </div>
        @if($asset['status'] === 'active')@can('fixed-assets.dispose')
            <form method="POST" action="{{ route('accounting.fixed-assets.dispose', $asset['id']) }}" onsubmit="return confirm('Dispose of this asset? Cost and accumulated depreciation will be removed.')" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-3">
                @csrf
                <h3 class="font-semibold text-gray-800 md:col-span-3">Sell or scrap</h3>
                <label class="text-sm"><span class="text-gray-700">Date</span><input type="date" name="disposal_date" value="{{ old('disposal_date', $today) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Proceeds (blank when scrapped)</span><input type="number" step="any" name="proceeds" value="{{ old('proceeds') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Proceeds received in</span><select name="proceeds_account_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">—</option>@foreach ($accounts as $account)@if($account['type'] === 'ASSET')<option value="{{ $account['id'] }}" @selected((string) old('proceeds_account_id') === (string) $account['id'])>{{ $account['account_code'] }} {{ $account['account_name'] }}</option>@endif @endforeach</select></label>
                <label class="text-sm"><span class="text-gray-700">Gain / loss account</span><select name="gain_loss_account_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($accounts as $account)@if(in_array($account['type'], ['INCOME', 'EXPENSE'], true))<option value="{{ $account['id'] }}" @selected((string) old('gain_loss_account_id') === (string) $account['id'])>{{ $account['account_code'] }} {{ $account['account_name'] }}</option>@endif @endforeach</select></label>
                <label class="text-sm md:col-span-2"><span class="text-gray-700">Notes</span><input name="notes" value="{{ old('notes') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <div><button class="px-4 py-2 bg-red-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest">Dispose</button></div>
            </form>
        @endcan @endif
    </div></div>
</x-accounting::app-layout>
