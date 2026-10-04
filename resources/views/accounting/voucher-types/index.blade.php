@php
    $resetLabels = ['yearly' => 'Every fiscal year', 'monthly' => 'Every month', 'never' => 'Never (one running series)'];
    $form = $editing;
    $locked = $form && (collect($voucherTypes)->firstWhere('id', $form->id)['entries_count'] ?? 0) > 0;
    $field = fn (string $name, $default = '') => old($name, $form?->{$name} ?? $default);
    $canWrite = $form ? auth()->user()?->can('voucher-types.update') : auth()->user()?->can('voucher-types.create');
@endphp
<x-accounting::app-layout title="Voucher Types">
    <x-slot name="header">
        <x-accounting::page-header title="Voucher Types" :showSearch="false" backRoute="accounting.dashboard" />
    </x-slot>

    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif

        <p class="text-sm text-gray-600">Each voucher type has its own gapless number series. A number is issued when an entry is posted, so voided drafts never leave gaps.</p>

        @if($canWrite)
        <form method="POST" action="{{ $form ? route('accounting.voucher-types.update', $form->id) : route('accounting.voucher-types.store') }}" class="bg-white shadow rounded-lg p-4 space-y-3">
            @csrf
            @if($form) @method('PUT') @endif
            <h3 class="font-semibold text-gray-700">{{ $form ? 'Edit '.$form->code : 'New voucher type' }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">Code</label>
                    <input id="code" name="code" value="{{ $field('code') }}" @readonly($locked) placeholder="SV" class="mt-1 w-full rounded-md border-gray-300 text-sm uppercase {{ $locked ? 'bg-gray-100' : '' }}">
                    @error('code')<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input id="name" name="name" value="{{ $field('name') }}" placeholder="Sales Voucher" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    @error('name')<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="prefix" class="block text-sm font-medium text-gray-700">Prefix</label>
                    <input id="prefix" name="prefix" value="{{ $field('prefix') }}" placeholder="SV" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    @error('prefix')<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="format" class="block text-sm font-medium text-gray-700">Number format</label>
                    <input id="format" name="format" value="{{ $field('format', $defaultFormat) }}" class="mt-1 w-full rounded-md border-gray-300 text-sm font-mono">
                    @error('format')<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="reset" class="block text-sm font-medium text-gray-700">Numbering restarts</label>
                    @if($locked)<input type="hidden" name="reset" value="{{ $form->reset }}">@endif
                    <select id="reset" name="reset" @disabled($locked) class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        @foreach($resets as $reset)
                            <option value="{{ $reset }}" @selected($field('reset', 'yearly') === $reset)>{{ $resetLabels[$reset] ?? $reset }}</option>
                        @endforeach
                    </select>
                    @error('reset')<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-6">
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <input id="description" name="description" value="{{ $field('description') }}" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
            </div>
            <p class="text-xs text-gray-500">Tokens: <code>{PREFIX}</code> prefix · <code>{FY}</code> fiscal year (2026, or 2025-26) · <code>{YYYY}</code> / <code>{YY}</code> year · <code>{MM}</code> month · <code>{SEQ:5}</code> number padded to 5 digits.
                @if($locked) Entries carry numbers of this series, so its code and restart rule are fixed.@endif</p>
            <div class="flex flex-wrap items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $form?->is_active ?? true)) @disabled($form?->is_system)> Active</label>
                @if($form?->is_system)<input type="hidden" name="is_active" value="1">@endif
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600">{{ $form ? 'Save changes' : 'Create voucher type' }}</button>
                @if($form)<a href="{{ route('accounting.voucher-types.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>@endif
            </div>
        </form>
        @endif

        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Type</th>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Format</th>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Restarts</th>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Next number</th>
                    <th class="py-2 px-3 text-right font-medium text-gray-600">Numbered entries</th>
                    <th class="py-2 px-3 text-right font-medium text-gray-600">Actions</th>
                </tr></thead>
                <tbody>
                    @foreach($voucherTypes as $type)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-2 px-3">
                                <span class="font-semibold">{{ $type['code'] }}</span> {{ $type['name'] }}
                                @if($type['is_system'])<span class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700">default</span>@endif
                                @unless($type['is_active'])<span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">inactive</span>@endunless
                                @if($type['description'])<div class="text-xs text-gray-500">{{ $type['description'] }}</div>@endif
                            </td>
                            <td class="py-2 px-3 font-mono text-xs">{{ $type['format'] }}</td>
                            <td class="py-2 px-3">{{ $resetLabels[$type['reset']] ?? $type['reset'] }}</td>
                            <td class="py-2 px-3 font-mono">{{ $type['next_number'] }}</td>
                            <td class="py-2 px-3 text-right">{{ $type['entries_count'] }}</td>
                            <td class="py-2 px-3 text-right whitespace-nowrap">
                                @can('voucher-types.update')
                                    <a href="{{ route('accounting.voucher-types.index', ['edit' => $type['id']]) }}" class="text-indigo-700 hover:underline">Edit</a>
                                @endcan
                                @can('voucher-types.delete')
                                    @if(! $type['is_system'] && $type['entries_count'] === 0)
                                        <form method="POST" action="{{ route('accounting.voucher-types.destroy', $type['id']) }}" class="inline" onsubmit="return confirm(@js('Delete voucher type '.$type['code'].'?'))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="ml-2 text-red-700 hover:underline">Delete</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div></div>
</x-accounting::app-layout>
