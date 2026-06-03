<x-accounting::app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Accounting Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-accounting::status-message class="mb-4" />
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Account Types</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $summary['accountTypes'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Currencies</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $summary['currencies'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Chart of Accounts</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $summary['accounts'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Posted Journal Entries</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $summary['postedJournalEntries'] }}</p>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @can('account-types.view')
                <a href="{{ route('accounting.account-types.index') }}" class="bg-white p-4 rounded-lg shadow hover:shadow-md transition flex items-center gap-3">
                    <div class="bg-indigo-100 p-2 rounded-md">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    </div>
                    <span class="font-semibold text-gray-700">Account Types</span>
                </a>
                @endcan
                @can('currencies.view')
                <a href="{{ route('accounting.currencies.index') }}" class="bg-white p-4 rounded-lg shadow hover:shadow-md transition flex items-center gap-3">
                    <div class="bg-green-100 p-2 rounded-md">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span class="font-semibold text-gray-700">Currencies</span>
                </a>
                @endcan
                @can('chart-of-accounts.view')
                <a href="{{ route('accounting.chart-of-accounts.index') }}" class="bg-white p-4 rounded-lg shadow hover:shadow-md transition flex items-center gap-3">
                    <div class="bg-blue-100 p-2 rounded-md">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <span class="font-semibold text-gray-700">Chart of Accounts</span>
                </a>
                @endcan
                @can('journal-entries.view')
                <a href="{{ route('accounting.journal-entries.index') }}" class="bg-white p-4 rounded-lg shadow hover:shadow-md transition flex items-center gap-3">
                    <div class="bg-yellow-100 p-2 rounded-md">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <span class="font-semibold text-gray-700">Journal Entries</span>
                </a>
                @endcan
            </div>
        </div>
    </div>
</x-accounting::app-layout>
