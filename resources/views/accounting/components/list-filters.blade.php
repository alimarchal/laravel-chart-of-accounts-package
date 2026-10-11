@props(['action', 'fields', 'values' => [], 'preserve' => []])
{{-- Filter bar shared by the list screens. Fields: name, label, type (text|select|date|number), options (value => label), quiet (a setting such as "as of" that is always present and does not count as a filter). The same names work as ?name=… and ?filter[name]=… on the API. --}}
@php($quiet = collect($fields)->where('quiet', true)->pluck('name')->all())
@php($active = collect($values)->except($quiet)->filter(fn ($value) => $value !== null && $value !== '')->count())
<form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-3" role="search">
    @foreach (collect($preserve)->filter(fn ($value) => $value !== null && $value !== '') as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    @foreach ($fields as $field)
        @php($type = $field['type'] ?? 'text')
        <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">
            <span>{{ __($field['label']) }}</span>
            @if ($type === 'select')
                <select name="{{ $field['name'] }}" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($field['options'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) ($values[$field['name']] ?? '') === (string) $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            @else
                <input type="{{ $type === 'text' ? 'search' : $type }}" name="{{ $field['name'] }}" value="{{ $values[$field['name']] ?? '' }}" @if ($type === 'number') step="any" @endif placeholder="{{ __($field['placeholder'] ?? '') }}" class="border-gray-300 rounded-md shadow-sm text-sm {{ $type === 'text' ? 'w-56' : 'w-36' }}">
            @endif
        </label>
    @endforeach
    <button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">{{ __('Filter') }}</button>
    @if ($active > 0)
        <a href="{{ $action }}@if (collect($preserve)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty())?{{ http_build_query(collect($preserve)->filter(fn ($value) => $value !== null && $value !== '')->all()) }}@endif" class="px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-600 hover:underline">{{ __('Reset') }} ({{ $active }})</a>
    @endif
</form>
