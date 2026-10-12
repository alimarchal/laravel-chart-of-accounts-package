@props(['value'])

<label {{ $attributes->merge(['class' => 'acc-label']) }}>
    {{ $value ?? $slot }}
</label>
