@props(['title' => 'Accounting'])

@push('styles')
@if(file_exists(public_path('vendor/accounting/select2.min.css')))
    <link href="{{ asset('vendor/accounting/select2.min.css') }}" rel="stylesheet" />
@else
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endif
@endpush

@push('scripts')
@if(file_exists(public_path('vendor/accounting/jquery.min.js')))
    <script src="{{ asset('vendor/accounting/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/accounting/select2.min.js') }}"></script>
@else
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endif
<x-accounting::select2-init />
@endpush

@php
    // The layout of the host application, in this order: accounting.blade_layout (ACCOUNTING_BLADE_LAYOUT, a component name such as
    // "app-layout" or "layouts::app"); <x-app-layout> (Breeze, Jetstream) when the application really has that component; the layout
    // Livewire renders its pages in (livewire.component_layout: "layouts::app" in the Livewire 4 starter kit, "components.layouts.app" in
    // Livewire 3) when that view exists. Anything else gets this package's own page layout. See Support\BladeKit.
    $hostLayout = \Alimarchal\LaravelChartOfAccounts\Support\BladeKit::layout();
    $kit = \Alimarchal\LaravelChartOfAccounts\Support\BladeKit::kit();
@endphp
@if($hostLayout)
    <x-dynamic-component :component="$hostLayout" :title="$title">
        @if($hostLayout === 'app-layout')
            @if(isset($header))
                <x-slot name="header"><div class="acc-kit acc-kit-{{ $kit }}">{{ $header }}</div></x-slot>
            @endif
        @endif
        <x-accounting::kit-theme :kit="$kit" />
        <x-accounting::ui-styles />
        <x-accounting::feedback />
        <div class="acc-kit acc-kit-{{ $kit }}">
            @if($hostLayout !== 'app-layout' && isset($header))
                {{-- The layout of a starter kit has no header slot: the page title and its buttons go above the content. --}}
                <div class="acc-wrap" style="padding-top:1rem">{{ $header }}</div>
            @endif
            <x-accounting::company-bar />
            {{ $slot }}
        </div>
        {{-- Jetstream, Breeze and the Livewire kit print none (or only some) of these stacks: print the ones their layout leaves out. --}}
        @foreach(['styles', 'scripts', 'modals'] as $acStack)
            @unless(\Alimarchal\LaravelChartOfAccounts\Support\BladeKit::hostRenders($acStack))
                @stack($acStack)
            @endunless
        @endforeach
    </x-dynamic-component>
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }} – {{ $title }}</title>
        @vite(['resources/css/app.css'])
        @if(class_exists(\Livewire\Livewire::class))
            @livewireStyles
        @endif
        @stack('header')
        @if(file_exists(public_path('vendor/accounting/select2.min.css')))
            <link href="{{ asset('vendor/accounting/select2.min.css') }}" rel="stylesheet" />
        @else
            <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        @endif
        <x-accounting::ui-styles />
    </head>
    <body class="font-sans antialiased acc-kit acc-kit-plain" style="background:#f1f5f9;margin:0">
        @if(isset($header))
        <header class="acc-wrap" style="padding-top:1.25rem;padding-bottom:.25rem">{{ $header }}</header>
        @endif
        <x-accounting::feedback />
        <main><x-accounting::company-bar />{{ $slot }}</main>
        @stack('modals')
        @if(class_exists(\Livewire\Livewire::class))
            @livewireScripts
        @endif
        @if(file_exists(public_path('vendor/accounting/jquery.min.js')))
            <script src="{{ asset('vendor/accounting/jquery.min.js') }}"></script>
            <script src="{{ asset('vendor/accounting/select2.min.js') }}"></script>
        @else
            <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        @endif
        <x-accounting::select2-init />
    </body>
    </html>
@endif
