<x-accounting::app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">Settings</h2>
    </x-slot>

    <div class="py-6 bg-[#F2F1F5] min-h-full">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-accounting::status-message class="mb-4" />
            <x-accounting::section-tabs active="settings" />


            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- ─── Users & access ─── --}}
                @canany(['user.view','accounting.manage-settings'])
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Users &amp; access</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                        @can('user.view')
                        <x-accounting::settings-row href="{{ route('settings.users.index') }}" label="Users" description="Manage system users & logins" :count="$summary['users']" icon-bg="bg-indigo-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('accounting.manage-settings')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('settings.roles.index') }}" label="Roles" description="Permissions & access levels" :count="$summary['roles']" icon-bg="bg-blue-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('settings.permissions.index') }}" label="Permissions" description="Fine-grained access control" :count="$summary['permissions']" icon-bg="bg-orange-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('settings.features.index') }}" label="Features" description="Switch modules and payroll features on or off" icon-bg="bg-emerald-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></x-slot>
                        </x-accounting::settings-row>
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('settings.account-roles.index') }}" label="Account roles" description="Which account the modules use for cash, bank, salaries and more" icon-bg="bg-indigo-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                    </div>
                </div>
                @endcanany

                {{-- ─── Setup ─── --}}
                @canany(['currencies.view','periods.view','cost-centers.view','tax-codes.view','tax-rates.view','voucher-types.view'])
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Setup</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                        @can('currencies.view')
                        <x-accounting::settings-row href="{{ route('accounting.currencies.index') }}" label="Currencies" description="Exchange rates & base currency" :count="$summary['currencies']" icon-bg="bg-yellow-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('periods.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.periods.index') }}" label="Accounting Periods" description="Open & close fiscal periods" :count="$summary['periods']" icon-bg="bg-purple-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('cost-centers.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.cost-centers.index') }}" label="Cost Centers" description="Departmental cost allocation" :count="$summary['costCenters']" icon-bg="bg-orange-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @if(\Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch::enabled())
                        @can('branches.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.branches.index') }}" label="Branches" description="Head office, campuses and shops of the company" icon-bg="bg-emerald-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3v12m0 0a3 3 0 103 3 3 3 0 00-3-3zm12-6a3 3 0 10-3-3 3 3 0 003 3zm0 0a9 9 0 01-9 9"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @endif
                        @can('tax-codes.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.tax-codes.index') }}" label="Tax Codes" description="Tax categories & codes" :count="$summary['taxCodes']" icon-bg="bg-red-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('tax-rates.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.tax-rates.index') }}" label="Tax Rates" description="Percentage rates per tax code" :count="$summary['taxRates']" icon-bg="bg-pink-500">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('chart-of-accounts.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.control-accounts.index') }}" label="Control Accounts" description="Receivables, payables, stock & other sub-ledgers" icon-bg="bg-indigo-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></x-slot>
                        </x-accounting::settings-row>
                        @endcan
                        @can('voucher-types.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row href="{{ route('accounting.voucher-types.index') }}" label="Voucher Types" description="JV, CPV, CRV, BPV, BRV & number series" :count="$summary['voucherTypes'] ?? null" icon-bg="bg-sky-600">
                            <x-slot name="icon"><svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg></x-slot>
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
