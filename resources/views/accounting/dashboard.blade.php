<x-accounting::app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">Settings</h2>
    </x-slot>

    <div class="py-6 bg-[#F2F1F5] min-h-full">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-accounting::status-message class="mb-4" />

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- ─── Journal & Ledger ─── --}}
                @canany(['journal-entries.view','chart-of-accounts.view','account-types.view','account-balance-snapshots.view'])
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Journal &amp; Ledger</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                        @can('journal-entries.view')
                        <x-accounting::settings-row href="{{ route('accounting.journal-entries.index') }}" label="Journal Entries" description="Draft, post, reverse &amp; void entries" :count="$summary['journalEntries']" icon-bg="bg-blue-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('chart-of-accounts.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.chart-of-accounts.index') }}" label="Chart of Accounts" description="Hierarchical account tree" :count="$summary['accounts']" icon-bg="bg-indigo-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('account-types.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.account-types.index') }}" label="Account Types" description="Asset, liability, equity, revenue, expense" :count="$summary['accountTypes']" icon-bg="bg-violet-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('account-balance-snapshots.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.account-balance-snapshots.index') }}" label="Balance Snapshots" description="Period-end account balance records" :count="$summary['balanceSnapshots']" icon-bg="bg-teal-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                    </div>
                </div>
                @endcanany

                {{-- ─── Financial Reports ─── --}}
                @canany(['reports.general-ledger.view','reports.trial-balance.view','reports.balance-sheet.view','reports.income-statement.view','reports.cash-flow.view','reports.account-balances.view'])
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Financial Reports</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                        @can('reports.general-ledger.view')
                        <x-accounting::settings-row href="{{ route('accounting.reports.general-ledger') }}" label="General Ledger" description="Full transaction history per account" icon-bg="bg-emerald-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('reports.trial-balance.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.reports.trial-balance') }}" label="Trial Balance" description="Debit &amp; credit totals for all accounts" icon-bg="bg-blue-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('reports.balance-sheet.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.reports.balance-sheet') }}" label="Balance Sheet" description="Assets, liabilities &amp; equity" icon-bg="bg-indigo-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('reports.income-statement.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.reports.income-statement') }}" label="Income Statement" description="Revenue, expenses &amp; net profit" icon-bg="bg-green-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('reports.cash-flow.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.reports.cash-flow') }}" label="Cash Flow" description="Operating, investing &amp; financing" icon-bg="bg-cyan-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('reports.account-balances.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.reports.account-balances') }}" label="Account Balances" description="Running balances for all accounts" icon-bg="bg-orange-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                    </div>
                </div>
                @endcanany

                {{-- ─── Aging & Banking Reports ─── --}}
                @canany(['reports.aged-receivables.view','reports.aged-payables.view','reports.bank-book.view','reports.cash-book.view'])
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Aging &amp; Banking</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                        @can('reports.aged-receivables.view')
                        <x-accounting::settings-row href="{{ route('accounting.reports.aged-receivables') }}" label="Aged Receivables" description="Outstanding customer balances by age" icon-bg="bg-blue-400">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('reports.aged-payables.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.reports.aged-payables') }}" label="Aged Payables" description="Outstanding supplier balances by age" icon-bg="bg-red-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('reports.bank-book.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.reports.bank-book') }}" label="Bank Book" description="Bank transactions &amp; running balance" :count="$summary['bankAccounts']" icon-bg="bg-slate-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('reports.cash-book.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.reports.cash-book') }}" label="Cash Book" description="Cash transactions &amp; running balance" icon-bg="bg-lime-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                    </div>
                </div>
                @endcanany

                {{-- ─── Bank & Reconciliation ─── --}}
                @canany(['bank-accounts.view','reconciliations.view'])
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Bank &amp; Reconciliation</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                        @can('bank-accounts.view')
                        <x-accounting::settings-row href="{{ route('accounting.bank-accounts.index') }}" label="Bank Accounts" description="Linked bank accounts" :count="$summary['bankAccounts']" icon-bg="bg-sky-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('reconciliations.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.reconciliations.index') }}" label="Reconciliations" description="Match bank statements to entries" :count="$summary['reconciliations']" icon-bg="bg-teal-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                    </div>
                </div>
                @endcanany

                {{-- ─── Master Data ─── --}}
                @canany(['currencies.view','periods.view','cost-centers.view','tax-codes.view','tax-rates.view'])
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Master Data</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                        @can('currencies.view')
                        <x-accounting::settings-row href="{{ route('accounting.currencies.index') }}" label="Currencies" description="Exchange rates &amp; base currency" :count="$summary['currencies']" icon-bg="bg-yellow-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('periods.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.accounting-periods.index') }}" label="Accounting Periods" description="Open &amp; close fiscal periods" :count="$summary['periods']" icon-bg="bg-purple-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('cost-centers.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.cost-centers.index') }}" label="Cost Centers" description="Departmental cost allocation" :count="$summary['costCenters']" icon-bg="bg-orange-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('tax-codes.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.tax-codes.index') }}" label="Tax Codes" description="Tax categories &amp; codes" :count="$summary['taxCodes']" icon-bg="bg-red-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('tax-rates.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.tax-rates.index') }}" label="Tax Rates" description="Percentage rates per tax code" :count="$summary['taxRates']" icon-bg="bg-pink-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                    </div>
                </div>
                @endcanany

                {{-- ─── Access & Identity ─── --}}
                @canany(['user.view','accounting.manage-settings'])
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Access &amp; Identity</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                        @can('user.view')
                        <x-accounting::settings-row href="{{ route('settings.users.index') }}" label="Users" description="Manage system users &amp; logins" :count="$summary['users']" icon-bg="bg-indigo-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('accounting.manage-settings')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('settings.roles.index') }}" label="Roles" description="Permissions &amp; access levels" :count="$summary['roles']" icon-bg="bg-blue-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('settings.permissions.index') }}" label="Permissions" description="Fine-grained access control" :count="$summary['permissions']" icon-bg="bg-orange-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                    </div>
                </div>
                @endcanany

                {{-- ─── Audit & Logs ─── --}}
                @can('audit-logs.view')
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Audit</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                        <x-accounting::settings-row href="{{ route('accounting.audit-logs.index') }}" label="Audit Logs" description="Full change trail for all transactions" icon-bg="bg-gray-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></x-slot>
                        </x-accounting::settings-row>
                    </div>
                </div>
                @endcan

            </div>
        </div>
    </div>
</x-accounting::app-layout>
