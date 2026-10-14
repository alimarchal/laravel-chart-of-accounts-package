@props(['action', 'fields', 'values' => [], 'preserve' => []])
{{-- Filter bar shared by the list screens. Fields: name, label, type (text|select|date|number), options (value => label), quiet (a setting such as "as of" that is always present and does not count as a filter). The same names work as ?name=… and ?filter[name]=… on the API. --}}
@php($quiet = collect($fields)->where('quiet', true)->pluck('name')->all())
@php($active = collect($values)->except($quiet)->filter(fn ($value) => $value !== null && $value !== '')->count())
@php($kept = collect($preserve)->filter(fn ($value) => $value !== null && $value !== ''))
<form method="GET" action="{{ $action }}" class="acc-lf" role="search">
    @foreach ($kept as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    @foreach ($fields as $field)
        @php($type = $field['type'] ?? 'text')
        <div class="acc-field {{ $type === 'text' ? 'acc-field--wide' : '' }}">
            <label for="lf_{{ $field['name'] }}">{{ __($field['label']) }}</label>
            @if ($type === 'select')
                <select id="lf_{{ $field['name'] }}" name="{{ $field['name'] }}" class="acc-select">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($field['options'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) ($values[$field['name']] ?? '') === (string) $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            @else
                <input id="lf_{{ $field['name'] }}" type="{{ $type === 'text' ? 'search' : $type }}" name="{{ $field['name'] }}" value="{{ $values[$field['name']] ?? '' }}" @if ($type === 'number') step="any" @endif placeholder="{{ __($field['placeholder'] ?? '') }}" class="acc-input">
            @endif
        </div>
    @endforeach
    <div class="acc-lf-actions">
        <button class="acc-btn acc-btn--primary">{{ __('Filter') }}</button>
        @if ($active > 0)
            <a href="{{ $action }}@if ($kept->isNotEmpty())?{{ http_build_query($kept->all()) }}@endif" class="acc-btn acc-btn--ghost">{{ __('Reset') }} ({{ $active }})</a>
        @endif
    </div>
</form>
