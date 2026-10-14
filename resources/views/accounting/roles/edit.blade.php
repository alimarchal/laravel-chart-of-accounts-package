<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header :title="'Edit Role: '.($role->name)" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('settings.roles.index')"></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <x-accounting::status-message class="mb-4 mt-4 shadow-md" />
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <x-accounting::validation-errors class="mb-4" />
            <form method="POST" action="{{ route('settings.roles.update', $role) }}">
                @csrf @method('PUT')
                @include('accounting::roles.partials.form-fields', ['role' => $role])
                <div class="flex justify-end mt-6"><x-accounting::button>Update Role</x-accounting::button></div>
            </form>
        </div>
    </div></div>
</x-accounting::app-layout>
