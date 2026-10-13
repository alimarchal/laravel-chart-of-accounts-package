<x-accounting::app-layout title="Help centre">
    <x-slot name="header">
        <x-accounting::page-header title="Help centre" subtitle="Short guides for every screen, in plain language: what it is for, when to use it, and how." :showSearch="false" :showRefresh="false" :showPrint="false" backRoute="accounting.dashboard" />
    </x-slot>
    <div class="acc-wrap" x-data="{ q: '' }" style="margin-top:1rem">
        <div class="acc-help-search">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
            <input type="search" x-model="q" placeholder="{{ __('Search the help: journal, payroll, reconciliation …') }}" class="acc-input" aria-label="{{ __('Search the help') }}">
        </div>
        @foreach ($blocks as $block)
            <section class="acc-help-block" x-show="[...$el.querySelectorAll('[data-help]')].some(c => !q || c.dataset.help.includes(q.toLowerCase()))">
                <h3 class="acc-help-h">{{ __($block['title']) }}</h3>
                <div class="acc-help-grid">
                    @foreach ($block['topics'] as $topic)
                        <a href="{{ $topic['url'] }}" class="acc-help-card" data-help="{{ \Illuminate\Support\Str::lower($topic['title'].' '.$topic['summary']) }}" x-show="!q || $el.dataset.help.includes(q.toLowerCase())">
                            <strong>{{ $topic['title'] }}</strong>
                            <span>{{ $topic['summary'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-accounting::app-layout>
