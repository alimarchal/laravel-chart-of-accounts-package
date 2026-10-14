<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header title="Edit Accounting Period" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.periods.index')"></x-accounting::page-header></x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-accounting::status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <x-accounting::validation-errors class="mb-4" />
                <form method="POST" action="{{ route('accounting.periods.update', $accountingPeriod) }}">
                    @csrf @method('PUT')
                    @include('accounting::accounting-periods.partials.form-fields', ['accountingPeriod' => $accountingPeriod])
                    <div class="flex items-center justify-end mt-6">
                        <x-accounting::button>Update Period</x-accounting::button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-accounting::app-layout>
