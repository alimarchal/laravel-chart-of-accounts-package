<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header :title="'View Accounting Period: '.($accountingPeriod->name)" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.periods.index')"><x-slot name="actions">@can('periods.update')
                <a href="{{ route('accounting.periods.edit', $accountingPeriod) }}" class="acc-btn acc-btn--dark">Edit</a>
                @endcan</x-slot></x-accounting::page-header></x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-accounting::status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-accounting::label value="Name" />
                        <x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$accountingPeriod->name" disabled readonly />
                    </div>
                    <div>
                        <x-accounting::label value="Status" />
                        <x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$statusOptions[$accountingPeriod->status] ?? ucfirst($accountingPeriod->status)" disabled readonly />
                    </div>
                    <div>
                        <x-accounting::label value="Start Date" />
                        <x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$accountingPeriod->start_date?->format('d-m-Y')" disabled readonly />
                    </div>
                    <div>
                        <x-accounting::label value="End Date" />
                        <x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$accountingPeriod->end_date?->format('d-m-Y')" disabled readonly />
                    </div>
                    <div>
                        <x-accounting::label value="Created At" />
                        <x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$accountingPeriod->created_at?->format('d-m-Y H:i:s')" disabled readonly />
                    </div>
                    <div>
                        <x-accounting::label value="Updated At" />
                        <x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$accountingPeriod->updated_at?->format('d-m-Y H:i:s')" disabled readonly />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-accounting::app-layout>
