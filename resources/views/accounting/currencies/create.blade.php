<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header title="Create Currency" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.currencies.index')"></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <x-accounting::status-message class="mb-4 mt-4 shadow-md" />
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <x-accounting::validation-errors class="mb-4" />
            <form method="POST" action="{{ route('accounting.currencies.store') }}">
                @csrf
                @include('accounting::currencies.partials.form-fields')
                <div class="flex justify-end mt-6"><x-accounting::button>Create</x-accounting::button></div>
            </form>
        </div>
    </div></div>
</x-accounting::app-layout>
