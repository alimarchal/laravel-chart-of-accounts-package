<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Aged Receivables" :showSearch="false" :showRefresh="true" backRoute="accounting.dashboard" />
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            @livewire('accounting::reports.aged-receivables')
        </div>
    </div></div>
</x-accounting::app-layout>
