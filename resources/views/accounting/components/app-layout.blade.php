@props(['title' => 'Accounting'])

@push('styles')
@if(file_exists(public_path('vendor/accounting/select2.min.css')))
    <link href="{{ asset('vendor/accounting/select2.min.css') }}" rel="stylesheet" />
@else
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endif
<style>
    .select2-container .select2-selection--single {
        height: auto; padding: 0.63rem 1rem; margin-top: 0.25rem; line-height: 1.25;
        border: 1px solid #d1d5db; border-radius: 0.375rem;
        box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
    }
    .select2-container .select2-selection--single .select2-selection__rendered { line-height: 1.25; padding-left: 0; padding-right: 0; }
    .select2-selection__arrow { top: 8px !important; right: 10px !important; }
    .select2-container .select2-selection--single .select2-selection__clear {
        color: #dc2626 !important; font-size: 1.25rem !important; font-weight: 700 !important;
        line-height: 1 !important; height: auto !important; margin-top: 0 !important; padding: 0 !important;
    }
    .select2-container .select2-selection--single .select2-selection__clear:hover { color: #991b1b !important; }
</style>
@endpush

@push('scripts')
@if(file_exists(public_path('vendor/accounting/jquery.min.js')))
    <script src="{{ asset('vendor/accounting/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/accounting/select2.min.js') }}"></script>
@else
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endif
<script>
$(document).ready(function() {
    $('.select2').select2({ placeholder: 'Select an option', allowClear: true, width: '100%' });
    $('label').on('click', function(e) {
        var forId = $(this).attr('for');
        if (forId) {
            var $el = $('#' + forId);
            if ($el.hasClass('select2') || $el.hasClass('select2-hidden-accessible')) {
                e.preventDefault(); $el.select2('open');
            }
        }
    });
});
</script>
@endpush

@if(view()->exists('layouts.app'))
    <x-app-layout>
        @if(isset($header))
            <x-slot name="header">{{ $header }}</x-slot>
        @endif
        {{ $slot }}
    </x-app-layout>
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
        <style>
            .select2-container .select2-selection--single {
                height: auto; padding: 0.63rem 1rem; margin-top: 0.25rem; line-height: 1.25;
                border: 1px solid #d1d5db; border-radius: 0.375rem;
                box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
            }
            .select2-container .select2-selection--single .select2-selection__rendered { line-height: 1.25; padding-left: 0; padding-right: 0; }
            .select2-selection__arrow { top: 8px !important; right: 10px !important; }
            .select2-container .select2-selection--single .select2-selection__clear {
                color: #dc2626 !important; font-size: 1.25rem !important; font-weight: 700 !important;
                line-height: 1 !important; height: auto !important; margin-top: 0 !important; padding: 0 !important;
            }
            .select2-container .select2-selection--single .select2-selection__clear:hover { color: #991b1b !important; }
        </style>
    </head>
    <body class="font-sans antialiased bg-gray-100">
        @if(isset($header))
        <header class="bg-white shadow mb-6">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
        </header>
        @endif
        <main>{{ $slot }}</main>
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
        <script>
        $(document).ready(function() {
            $('.select2').select2({ placeholder: 'Select an option', allowClear: true, width: '100%' });
            $('label').on('click', function(e) {
                var forId = $(this).attr('for');
                if (forId) {
                    var $el = $('#' + forId);
                    if ($el.hasClass('select2') || $el.hasClass('select2-hidden-accessible')) {
                        e.preventDefault(); $el.select2('open');
                    }
                }
            });
        });
        </script>
    </body>
    </html>
@endif
