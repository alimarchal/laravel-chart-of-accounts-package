<x-accounting::app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">Accounting</h2>
    </x-slot>

    <div class="py-6 bg-[#F2F1F5] min-h-full">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-accounting::status-message class="mb-4" />

            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-6">

                {{-- ─── Master Data ─── --}}
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Master Data</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">

                        @can('account-types.view')
                        <x-accounting::settings-row
                            href="{{ route('settings.account-types.index') }}"
                            label="Account Types"
                            description="Define categories for your ledger"
                            :count="$counts['accountTypes']"
                            icon-bg="bg-blue-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6C3.75 4.757 4.757 3.75 6 3.75H8.25C9.493 3.75 10.5 4.757 10.5 6V8.25C10.5 9.493 9.493 10.5 8.25 10.5H6C4.757 10.5 3.75 9.493 3.75 8.25V6ZM3.75 15.75C3.75 14.507 4.757 13.5 6 13.5H8.25C9.493 13.5 10.5 14.507 10.5 15.75V18C10.5 19.243 9.493 20.25 8.25 20.25H6C4.757 20.25 3.75 19.243 3.75 18V15.75ZM13.5 6C13.5 4.757 14.507 3.75 15.75 3.75H18C19.243 3.75 20.25 4.757 20.25 6V8.25C20.25 9.493 19.243 10.5 18 10.5H15.75C14.507 10.5 13.5 9.493 13.5 8.25V6ZM13.5 15.75C13.5 14.507 14.507 13.5 15.75 13.5H18C19.243 13.5 20.25 14.507 20.25 15.75V18C20.25 19.243 19.243 20.25 18 20.25H15.75C14.507 20.25 13.5 19.243 13.5 18V15.75Z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('chart-of-accounts.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.chart-of-accounts.index') }}"
                            label="Chart of Accounts"
                            description="Master list of all ledger accounts"
                            :count="$counts['accounts']"
                            icon-bg="bg-green-600">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3V14.25C3.75 15.493 4.757 16.5 6 16.5H8.25M3.75 3H2.25M3.75 3H20.25M20.25 3H21.75M20.25 3V14.25C20.25 15.493 19.243 16.5 18 16.5H15.75M8.25 16.5H15.75M8.25 16.5L7.25 19.5M15.75 16.5L16.75 19.5M7.25 19.5L6.75 21M7.25 19.5H16.75M16.75 19.5L17.25 21M7.5 12L10.5 9L12.648 11.148C13.654 9.703 14.97 8.49 16.5 7.605" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('currencies.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.currencies.index') }}"
                            label="Currencies"
                            description="Multi-currency setup & exchange rates"
                            :count="$counts['currencies']"
                            icon-bg="bg-yellow-400">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V18M9 15.182L9.879 15.841C11.05 16.72 12.95 16.72 14.121 15.841C15.293 14.962 15.293 13.538 14.121 12.659C13.536 12.22 12.768 12 12 12C11.275 12 10.55 11.78 9.997 11.341C8.891 10.462 8.891 9.038 9.997 8.159C11.103 7.28 12.897 7.28 14.003 8.159L14.418 8.489M21 12C21 16.971 16.971 21 12 21C7.029 21 3 16.971 3 12C3 7.029 7.029 3 12 3C16.971 3 21 7.029 21 12Z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('periods.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.periods.index') }}"
                            label="Fiscal Periods"
                            description="Manage financial months & years"
                            :count="$counts['periods']"
                            icon-bg="bg-emerald-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3V5.25M17.25 3V5.25M3 18.75V7.5C3 6.257 4.007 5.25 5.25 5.25H18.75C19.993 5.25 21 6.257 21 7.5V18.75M3 18.75C3 19.993 4.007 21 5.25 21H18.75C19.993 21 21 19.993 21 18.75M3 18.75V11.25C3 10.007 4.007 9 5.25 9H18.75C19.993 9 21 10.007 21 11.25V18.75" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('cost-centers.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.cost-centers.index') }}"
                            label="Cost Centers"
                            description="Department & project cost tracking"
                            :count="$counts['costCenters']"
                            icon-bg="bg-fuchsia-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('bank-accounts.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.bank-accounts.index') }}"
                            label="Bank Accounts"
                            description="Company bank accounts & balances"
                            :count="$counts['bankAccounts']"
                            icon-bg="bg-teal-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                    </div>
                </div>

                {{-- ─── Transactions ─── --}}
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Transactions</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">

                        @can('journal-entries.view')
                        <x-accounting::settings-row
                            href="{{ route('settings.journal-entries.index') }}"
                            label="Journal Entries"
                            description="Manual GL postings & adjustments"
                            :count="$counts['journalEntries']"
                            icon-bg="bg-violet-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('tax-codes.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.tax-codes.index') }}"
                            label="Tax Codes"
                            description="Define VAT, Sales Tax & other taxes"
                            :count="$counts['taxCodes']"
                            icon-bg="bg-red-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('tax-rates.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.tax-rates.index') }}"
                            label="Tax Rates"
                            description="Percentage rates for tax codes"
                            :count="$counts['taxRates']"
                            icon-bg="bg-rose-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('reconciliations.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.reconciliations.index') }}"
                            label="Reconciliations"
                            description="Bank & account reconciliation"
                            :count="$counts['reconciliations']"
                            icon-bg="bg-cyan-600">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('account-balance-snapshots.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.account-balance-snapshots.index') }}"
                            label="Balance Snapshots"
                            description="Point-in-time account balance records"
                            :count="$counts['balanceSnapshots']"
                            icon-bg="bg-orange-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('audit-logs.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.audit-logs.index') }}"
                            label="Audit Logs"
                            description="Full audit trail of accounting changes"
                            icon-bg="bg-slate-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                    </div>
                </div>

                {{-- ─── Reports ─── --}}
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Reports</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">

                        @can('reports.general-ledger.view')
                        <x-accounting::settings-row
                            href="{{ route('settings.reports.general-ledger') }}"
                            label="General Ledger"
                            description="Full transaction history by account"
                            icon-bg="bg-blue-700">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75.125v-5.25C2.25 11.246 3.246 10.5 4.375 10.5h1.5M6 18.375v-5.25c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V18M6 18h9M6 10.5V9C6 7.757 6.757 7.5 8.25 7.5h4.5c1.243 0 2.25.504 2.25 2.25v1.5M15.75 18v-5.25c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V18" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('reports.trial-balance.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.reports.trial-balance') }}"
                            label="Trial Balance"
                            description="Debit & credit totals for all accounts"
                            icon-bg="bg-indigo-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.97zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.97z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('reports.account-balances.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.reports.account-balances') }}"
                            label="Account Balances"
                            description="Running balances for all accounts"
                            icon-bg="bg-green-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('reports.balance-sheet.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.reports.balance-sheet') }}"
                            label="Balance Sheet"
                            description="Assets, liabilities & equity snapshot"
                            icon-bg="bg-violet-600">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5H12M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5H12m-8.25 5.25h16.5M12 16.5V21" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('reports.income-statement.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.reports.income-statement') }}"
                            label="Income Statement"
                            description="Revenue, expenses & net profit"
                            icon-bg="bg-emerald-600">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('reports.cash-flow.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.reports.cash-flow') }}"
                            label="Cash Flow"
                            description="Cash inflows & outflows summary"
                            icon-bg="bg-teal-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('reports.bank-book.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.reports.bank-book') }}"
                            label="Bank Book"
                            description="All bank account transactions"
                            icon-bg="bg-sky-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('reports.cash-book.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.reports.cash-book') }}"
                            label="Cash Book"
                            description="All cash account transactions"
                            icon-bg="bg-lime-600">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18-3a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6m18 0V6m0 0v.75M3 6v.75" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('reports.aged-receivables.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.reports.aged-receivables') }}"
                            label="Aged Receivables"
                            description="Outstanding customer invoices by age"
                            icon-bg="bg-amber-500">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('reports.aged-payables.view')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.reports.aged-payables') }}"
                            label="Aged Payables"
                            description="Outstanding supplier invoices by age"
                            icon-bg="bg-red-600">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                    </div>
                </div>

                {{-- ─── User Management ─── --}}
                <div>
                    <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">User Management</p>
                    <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">

                        @can('user.view')
                        <x-accounting::settings-row
                            href="{{ route('settings.users.index') }}"
                            label="Users"
                            description="Manage system users"
                            :count="$counts['users']"
                            icon-bg="bg-blue-600">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                        @can('accounting.manage-settings')
                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.roles.index') }}"
                            label="Roles"
                            description="Define user roles"
                            :count="$counts['roles']"
                            icon-bg="bg-purple-600">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>

                        <div class="ml-[58px] h-px bg-gray-100"></div>
                        <x-accounting::settings-row
                            href="{{ route('settings.permissions.index') }}"
                            label="Permissions"
                            description="View all system permissions"
                            :count="$counts['permissions']"
                            icon-bg="bg-orange-600">
                            <x-slot name="icon">
                                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                            </x-slot>
                        </x-accounting::settings-row>
                        @endcan

                    </div>
                </div>

            </div>
        </div>
    </div>
</x-accounting::app-layout>

