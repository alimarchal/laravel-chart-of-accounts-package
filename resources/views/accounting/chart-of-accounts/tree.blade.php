<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Manage Chart of Accounts Tree
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Visualize hierarchical account relationships and quickly expand or collapse branches.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('settings.chart-of-accounts.index') }}"
                    class="inline-flex items-center px-3 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Accounts
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-accounting::status-message />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white shadow-md rounded-lg p-4 border border-gray-200">
                    <p class="text-sm text-gray-500">Total Accounts</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900">{{ $totalAccounts }}</p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-4 border border-gray-200">
                    <p class="text-sm text-gray-500">Group (Non-posting)</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900">{{ $groupAccounts }}</p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-4 border border-gray-200">
                    <p class="text-sm text-gray-500">Posting Accounts</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900">{{ $postingAccounts }}</p>
                </div>
            </div>

            <div class="bg-white shadow-md rounded-lg border border-gray-200">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Account Hierarchy</h3>
                        <p class="text-xs text-gray-500 mt-1">Use the controls to expand or collapse
                            the entire tree, or click individual rows.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="window.dispatchEvent(new CustomEvent('coa-expand-all'))"
                            class="inline-flex items-center px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-md hover:bg-indigo-100 transition">
                            <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12h18M12 3v18" />
                            </svg>
                            Expand All
                        </button>
                        <button type="button" onclick="window.dispatchEvent(new CustomEvent('coa-collapse-all'))"
                            class="inline-flex items-center px-3 py-1.5 bg-slate-50 text-slate-600 text-xs font-semibold rounded-md hover:bg-slate-100 transition">
                            <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
                            </svg>
                            Collapse All
                        </button>
                    </div>
                </div>
                <div class="px-4 py-4">
                    @if($roots->isEmpty())
                    <div class="text-center py-16 text-gray-500">
                        <p>No chart of account records found.</p>
                        <p class="mt-2 text-sm">Add accounts to see them organized automatically in the tree.</p>
                    </div>
                    @else
                    <ul class="space-y-1">
                        @foreach ($roots as $root)
                        @include('accounting::chart-of-accounts.partials.tree-node', ['node' => $root])
                        @endforeach
                    </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-accounting::app-layout>
