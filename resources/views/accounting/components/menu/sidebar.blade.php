@props(['variant' => 'navlist'])
{{-- The accounting menu for the Flux sidebar of the Livewire starter kit: one "Accounting" block with sections that open and close, then
     Settings and Help. variant="navlist" for kits built on <flux:navlist> (Flux 1), variant="sidebar" for <flux:sidebar.nav> (Flux 2). --}}
@php($menu = \Alimarchal\LaravelChartOfAccounts\Support\AccountingMenu::for(auth()->user()))
@if ($menu !== [])
    @php($group = 'flux::'.($variant === 'sidebar' ? 'sidebar' : 'navlist').'.group')
    @php($entry = 'flux::'.($variant === 'sidebar' ? 'sidebar' : 'navlist').'.item')
    @php($first = collect($menu)->firstWhere('area', 'accounting'))
    @php($settingsShown = false)
    <x-dynamic-component :component="$group" :heading="__('Accounting')" class="grid">
        @foreach (($first['key'] ?? null) === 'overview' ? $first['items'] : [] as $item)
            <x-dynamic-component :component="$entry" :icon="$item['key'] === 'overview' ? 'chart-bar-square' : 'squares-2x2'" :href="$item['url']" :current="$item['active']">{{ __($item['title']) }}</x-dynamic-component>
        @endforeach
    </x-dynamic-component>
    @foreach ($menu as $section)
        @continue($section['key'] === 'overview')
        @if ($section['area'] === 'settings' && ! $settingsShown)
            @php($settingsShown = true)
            <x-dynamic-component :component="$group" :heading="__('Accounting settings')" class="grid mt-2" />
        @endif
        @if ($section['area'] === 'help')
            <x-dynamic-component :component="$group" class="grid mt-2">
                @foreach ($section['items'] as $item)
                    <x-dynamic-component :component="$entry" icon="question-mark-circle" :href="$item['url']" :current="$item['active']">{{ __($item['title']) }}</x-dynamic-component>
                @endforeach
            </x-dynamic-component>
        @else
            <x-dynamic-component :component="$group" :expandable="true" :expanded="$section['open']" :heading="__($section['title'])" class="grid">
                @foreach ($section['items'] as $item)
                    <x-dynamic-component :component="$entry" :href="$item['url']" :current="$item['active']">{{ __($item['title']) }}</x-dynamic-component>
                @endforeach
            </x-dynamic-component>
        @endif
    @endforeach
@endif
