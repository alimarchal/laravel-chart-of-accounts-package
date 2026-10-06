<x-accounting::app-layout title="Asset">
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $asset ? 'Edit '.$asset['code'] : 'New asset' }}</h2></x-slot>
    @php($locked = $asset['locked'] ?? false)
    @php($v = fn ($name, $default = '') => old($name, $asset[$name] ?? $default))
    <div class="py-6"><div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        @if($locked)<p class="text-sm text-amber-700">Cost, life, method, dates and accounts are locked: the asset already has entries.</p>@endif
        <form method="POST" action="{{ $asset ? route('accounting.fixed-assets.update', $asset['id']) : route('accounting.fixed-assets.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-3">
            @csrf @if($asset) @method('PUT') @endif
            @foreach ([['code', 'Code', 'text', false], ['name', 'Name', 'text', false], ['category', 'Category', 'text', false], ['acquisition_date', 'Acquisition date', 'date', true], ['in_service_date', 'In service from (default: acquisition date)', 'date', true], ['cost', 'Cost', 'number', true], ['salvage_value', 'Salvage value', 'number', true], ['useful_life_months', 'Useful life (months)', 'number', true], ['declining_rate', 'Annual rate % for declining balance (blank = double the straight-line rate)', 'number', true], ['description', 'Description', 'text', false]] as [$name, $label, $type, $lock])
                <label class="text-sm"><span class="text-gray-700">{{ $label }}</span><input type="{{ $type }}" step="any" name="{{ $name }}" value="{{ $v($name, $name === 'acquisition_date' ? $today : ($name === 'salvage_value' ? '0' : ($name === 'useful_life_months' ? '60' : ''))) }}" @disabled($lock && $locked) class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
            @endforeach
            <label class="text-sm"><span class="text-gray-700">Method</span><select name="method" @disabled($locked) class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">@foreach ($methods as $key => $label)<option value="{{ $key }}" @selected($v('method', 'straight_line') === $key)>{{ $label }}</option>@endforeach</select></label>
            @foreach ([['asset_account_id', 'Asset account (cost)', ['ASSET']], ['accumulated_account_id', 'Accumulated depreciation account', ['ASSET']], ['expense_account_id', 'Depreciation expense account', ['EXPENSE']], ['offset_account_id', 'Paid from / owed to (books the purchase)', null]] as [$name, $label, $types])
                @continue($name === 'offset_account_id' && $asset)
                <label class="text-sm"><span class="text-gray-700">{{ $label }}</span><select name="{{ $name }}" @disabled($locked && $name !== 'offset_account_id') class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">{{ $name === 'offset_account_id' ? 'Do not book the purchase' : 'Choose…' }}</option>@foreach ($accounts as $account)@if($types === null || in_array($account['type'], $types, true))<option value="{{ $account['id'] }}" @selected((string) $v($name) === (string) $account['id'])>{{ $account['account_code'] }} {{ $account['account_name'] }}</option>@endif @endforeach</select></label>
            @endforeach
            <div class="md:col-span-3"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Save</button> <a href="{{ route('accounting.fixed-assets.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Cancel</a></div>
        </form>
    </div></div>
</x-accounting::app-layout>
