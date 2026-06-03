@props(['title' => 'Accounting'])
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
    </head>
    <body class="font-sans antialiased bg-gray-100">
        @if(isset($header))
        <header class="bg-white shadow mb-6">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
        </header>
        @endif
        <main>{{ $slot }}</main>
        @if(class_exists(\Livewire\Livewire::class))
            @livewireScripts
        @endif
    </body>
    </html>
@endif
