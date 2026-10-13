@props([
    'title' => 'Page Title',
    'subtitle' => null,
    'createRoute' => null,
    'createLabel' => 'Add New',
    'createPermission' => null,
    'showSearch' => true,
    'showRefresh' => true,
    'showPrint' => true,
    'backRoute' => 'dashboard',
])
@php
    $backUrl = str_starts_with($backRoute, 'http') || str_starts_with($backRoute, '/') ? $backRoute : (\Illuminate\Support\Facades\Route::has($backRoute) ? route($backRoute) : null);
    $activeFilters = collect(request()->input('filter', []))->flatten()->filter(fn ($value) => $value !== null && $value !== '')->count();
    $isForm = preg_match('~/(create|edit|import)$~', request()->path()) === 1;     // a form needs no refresh or print button
    $help = app(\Alimarchal\LaravelChartOfAccounts\Support\Help::class)->forPath(request()->path());
    $canCreate = $createRoute && (! $createPermission || auth()->user()?->can($createPermission));
@endphp
<div class="acc-head">
    <div class="acc-head-l">
        @if($backUrl)
            <a href="{{ $backUrl }}" class="acc-crumb"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>{{ __('Back') }}</a>
        @endif
        <h2 class="acc-title">{{ $title }}</h2>
        @if($subtitle)<p class="acc-sub">{{ $subtitle }}</p>@endif
    </div>
    <div class="acc-head-r">
        {{ $actions ?? '' }}
        @if($showSearch)
            <button type="button" id="toggle" class="acc-btn" aria-expanded="false" aria-controls="filters">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18l-7 8.25V19l-4 1.5v-7.75L3 4.5z"/></svg>
                {{ __('Filters') }}@if($activeFilters > 0)<span class="acc-count">{{ $activeFilters }}</span>@endif
            </button>
        @endif
        @if($showRefresh && ! $isForm)
            <a href="javascript:window.location.reload();" class="acc-btn acc-btn--icon" title="{{ __('Refresh') }}" aria-label="{{ __('Refresh') }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.02 9.35h4.99M2.99 19.64v-4.99m0 0h4.99m-4.99 0 3.18 3.18a8.25 8.25 0 0 0 13.8-3.7M4.03 9.87a8.25 8.25 0 0 1 13.8-3.7l3.18 3.18m0-4.99v4.99"/></svg>
            </a>
        @endif
        @if($showPrint && ! $isForm)
        <button type="button" onclick="window.print();" class="acc-btn acc-btn--icon" title="{{ __('Print') }}" aria-label="{{ __('Print') }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.83a42.4 42.4 0 0 1 10.56 0m-10.56 0L6.34 18m.38-4.17c-.24.03-.48.06-.72.1m.72-.1H6m11.28 0c.24.03.48.06.72.1m-.72-.1.38 4.17m0 0 .23 2.52a1.13 1.13 0 0 1-1.12 1.23H7.23c-.66 0-1.18-.57-1.12-1.23L6.34 18m11.32 0h1.09A2.25 2.25 0 0 0 21 15.75V9.46c0-1.08-.77-2.02-1.84-2.18a48 48 0 0 0-1.91-.25m-10.5 0V3.38c0-.62.5-1.13 1.13-1.13h8.25c.62 0 1.13.5 1.13 1.13v3.66m-10.5 0a48.5 48.5 0 0 1 10.5 0"/></svg>
        </button>
        @endif
        @if($help)
        <a href="{{ $help['url'] }}" class="acc-btn acc-btn--icon" title="{{ __('Help') }}: {{ $help['title'] }}" aria-label="{{ __('Help') }}: {{ $help['title'] }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4.5M12 18h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        </a>
        @endif
        @if($canCreate)
            <a href="{{ $createRoute }}" class="acc-btn acc-btn--primary">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg>
                {{ $createLabel }}
            </a>
        @endif
    </div>
</div>
