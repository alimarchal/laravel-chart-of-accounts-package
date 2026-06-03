<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">View Accounting Period: {{ $accountingPeriod->name }}</h2>
            <div class="flex items-center gap-2">
                @can('periods.update')
                <a href="{{ route('accounting.periods.edit', $accountingPeriod) }}" class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">Edit</a>
                @endcan
                <a href="{{ route('accounting.periods.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-accounting::status-message class="mb-4" />
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
