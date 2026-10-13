@props(['action', 'fields', 'values' => [], 'preserve' => []])
{{-- Filter bar shared by the list screens. Fields: name, label, type (text|select|date|number), options (value => label), quiet (a setting such as "as of" that is always present and does not count as a filter). The same names work as ?name=… and ?filter[name]=… on the API.
     Up to four fields are always shown; a longer list shows the first three and keeps the rest under "More filters", which opens by itself when one of them is in use. --}}
@php($quiet = collect($fields)->where('quiet', true)->pluck('name')->all())
@php($active = collect($values)->except($quiet)->filter(fn ($value) => $value !== null && $value !== '')->count())
@php($kept = collect($preserve)->filter(fn ($value) => $value !== null && $value !== ''))
@php($shown = count($fields) > 4 ? array_slice($fields, 0, 3) : $fields)
@php($more = count($fields) > 4 ? array_slice($fields, 3) : [])
@php($moreActive = collect($more)->reject(fn ($field) => ! empty($field['quiet']))->filter(fn ($field) => ($values[$field['name']] ?? '') !== '' && ($values[$field['name']] ?? null) !== null)->count())
<form method="GET" action="{{ $action }}" class="acc-lf" role="search">
    @foreach ($kept as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    @foreach ([$shown, $more] as $set => $group)
        @if ($set === 1 && $more !== [])
            <details class="acc-lf-more" @if ($moreActive > 0) open @endif>
                <summary>{{ __('More filters') }}@if ($moreActive > 0) <span class="acc-count">{{ $moreActive }}</span>@endif</summary>
                <div class="acc-lf-moregrid">
        @endif
        @foreach ($group as $field)
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
        @if ($set === 1 && $more !== [])
                </div>
            </details>
        @endif
        @if ($set === 0)
            <div class="acc-lf-actions">
                <button class="acc-btn acc-btn--primary">{{ __('Filter') }}</button>
                @if ($active > 0)
                    <a href="{{ $action }}@if ($kept->isNotEmpty())?{{ http_build_query($kept->all()) }}@endif" class="acc-btn acc-btn--ghost">{{ __('Reset') }} ({{ $active }})</a>
                @endif
            </div>
        @endif
    @endforeach
</form>
