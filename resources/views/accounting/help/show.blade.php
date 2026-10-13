<x-accounting::app-layout :title="$article['title']">
    <x-slot name="header">
        <x-accounting::page-header :title="$article['title']" :subtitle="$article['summary']" :showSearch="false" :showRefresh="false" :showPrint="true" :backRoute="route(config('accounting.route_name_prefix', 'accounting').'.help.index')" />
    </x-slot>
    <div class="acc-wrap acc-help-layout">
        <aside class="acc-help-nav acc-no-print" aria-label="{{ __('Help topics') }}">
            @foreach ($blocks as $block)
                <details @if (collect($block['topics'])->contains('slug', $article['slug'])) open @endif>
                    <summary>{{ __($block['title']) }}</summary>
                    @foreach ($block['topics'] as $topic)
                        <a href="{{ $topic['url'] }}" @if ($topic['slug'] === $article['slug']) aria-current="page" @endif>{{ $topic['title'] }}</a>
                    @endforeach
                </details>
            @endforeach
        </aside>
        <article class="acc-card acc-help-article">
            <div class="acc-card-b acc-prose">{!! $html !!}</div>
        </article>
    </div>
</x-accounting::app-layout>
