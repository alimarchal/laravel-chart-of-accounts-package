<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header :title="'Reconciliation #'.($reconciliation->id)" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.reconciliations.index')"><x-slot name="actions">@can('reconciliations.update')<a href="{{ route('accounting.reconciliations.edit', $reconciliation) }}" class="acc-btn acc-btn--dark">Edit</a>@endcan</x-slot></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><x-accounting::label value="Bank Account" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($reconciliation->bankAccount)->account_name" disabled readonly /></div>
                <div><x-accounting::label value="Statement Date" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$reconciliation->statement_date->format('Y-m-d')" disabled readonly /></div>
                <div><x-accounting::label value="Statement Balance" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="number_format($reconciliation->statement_balance, 2)" disabled readonly /></div>
                <div><x-accounting::label value="Book Balance" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="number_format($reconciliation->book_balance, 2)" disabled readonly /></div>
                <div><x-accounting::label value="Status" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="ucfirst(str_replace('_',' ',$reconciliation->status))" disabled readonly /></div>
                @if ($reconciliation->completed_at)
                <div><x-accounting::label value="Completed At" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$reconciliation->completed_at->format('Y-m-d H:i')" disabled readonly /></div>
                @endif
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
