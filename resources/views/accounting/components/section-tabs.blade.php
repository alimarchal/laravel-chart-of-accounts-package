@props(['active' => 'accounting'])
{{-- The two parts of the accounting area: Accounting (the daily work) and Settings (users, roles, permissions, how the books are set up). --}}
@php
    $tabs = [['key' => 'accounting', 'title' => 'Accounting', 'href' => route('accounting.dashboard')]];
    if (auth()->user()?->canany(['user.view', 'accounting.manage-settings', 'currencies.view', 'periods.view', 'cost-centers.view', 'tax-codes.view', 'tax-rates.view', 'voucher-types.view', 'control-accounts.manage', 'branches.view', 'audit-logs.view', 'account-types.view'])) {
        $tabs[] = ['key' => 'settings', 'title' => 'Settings', 'href' => route('accounting.settings')];
    }
@endphp
<nav class="mb-6 flex gap-1 border-b border-gray-300" aria-label="Accounting sections">
    @foreach($tabs as $tab)
        <a href="{{ $tab['href'] }}" @if($active === $tab['key']) aria-current="page" @endif
           class="-mb-px border-b-2 px-4 py-2 text-sm font-medium {{ $active === $tab['key'] ? 'border-indigo-600 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-800' }}">{{ $tab['title'] }}</a>
    @endforeach
</nav>
