<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="New journal entry" subtitle="Record a posting. It is saved as a draft; you review and post it afterwards." :showSearch="false" :showRefresh="false" :showPrint="false" backRoute="accounting.journal-entries.index" />
    </x-slot>
    <div class="acc-wrap" style="margin-top:1rem;padding-bottom:4rem">
        <x-accounting::status-message />
        @livewire('accounting.journal-entry-form')
    </div>
</x-accounting::app-layout>
