<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tax Rate</h2>
            <div class="flex gap-2">
                @can('tax-rates.update')<a href="{{ route('accounting.tax-rates.edit', $taxRate) }}" class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">Edit</a>@endcan
                <a href="{{ route('accounting.tax-rates.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><x-accounting::label value="Tax Code" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($taxRate->taxCode)->name" disabled readonly /></div>
                <div><x-accounting::label value="Rate %" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="number_format($taxRate->rate, 2).'%'" disabled readonly /></div>
                <div><x-accounting::label value="Effective From" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($taxRate->effective_from)->format('Y-m-d')" disabled readonly /></div>
                <div><x-accounting::label value="Effective To" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($taxRate->effective_to)->format('Y-m-d') ?? '—'" disabled readonly /></div>
                <div><x-accounting::label value="Active" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$taxRate->is_active ? 'Yes' : 'No'" disabled readonly /></div>
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
