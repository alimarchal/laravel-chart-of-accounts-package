@php
    $period = $checklist['period'];
    $isOpen = $period['status'] === 'open';
    $failures = collect($checklist['checks'])->where('status', 'fail')->count();
    $warnings = collect($checklist['checks'])->where('status', 'warn')->count();
    $title = $checklist['year_end'] ? 'Year-end close' : 'Month-end close';
    $icon = ['pass' => '✓', 'fail' => '✕', 'warn' => '!', 'info' => 'i'];
    $iconClass = ['pass' => 'bg-emerald-100 text-emerald-700', 'fail' => 'bg-red-100 text-red-700', 'warn' => 'bg-amber-100 text-amber-700', 'info' => 'bg-gray-100 text-gray-600'];
@endphp
<x-accounting::app-layout :title="$title">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}: {{ $period['name'] }}</h2>
                <p class="text-sm text-gray-500">{{ \Illuminate\Support\Carbon::parse($period['start_date'])->format('d-m-Y') }} – {{ \Illuminate\Support\Carbon::parse($period['end_date'])->format('d-m-Y') }} · <span class="capitalize">{{ $period['status'] }}</span></p>
            </div>
            <div class="flex items-center gap-2">
                @if($isOpen && $isFiscalYearEnd)
                    <a href="{{ route('accounting.periods.close.show', ['period' => $period['id'], 'year_end' => $checklist['year_end'] ? null : 1]) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">{{ $checklist['year_end'] ? 'Month-end close only' : 'Year-end close' }}</a>
                @endif
                <a href="{{ route('accounting.periods.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900">All periods</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))
            <div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach(['Posted entries' => $checklist['summary']['posted_entries'], 'Net income (period)' => number_format((float) $checklist['summary']['net_income'], 2), 'Total debits to date' => number_format((float) $checklist['summary']['total_debits'], 2), 'Total credits to date' => number_format((float) $checklist['summary']['total_credits'], 2)] as $label => $value)
                <div class="bg-white shadow rounded-lg p-4">
                    <div class="text-sm text-gray-500">{{ $label }}</div>
                    <div class="text-xl font-semibold font-mono">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="bg-white shadow rounded-lg">
            <div class="flex items-center justify-between border-b p-4">
                <h3 class="font-semibold text-gray-700">Close checklist</h3>
                <span class="text-sm text-gray-500">{{ $failures ? "{$failures} to fix" : 'Ready' }}{{ $warnings ? " · {$warnings} to review" : '' }}</span>
            </div>
            <ul class="divide-y">
                @foreach($checklist['checks'] as $check)
                    <li class="flex items-start gap-3 p-4">
                        <span class="inline-flex w-6 h-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $iconClass[$check['status']] }}">{{ $icon[$check['status']] }}</span>
                        <div class="flex-1">
                            <div class="font-medium text-gray-800">{{ $check['label'] }}</div>
                            @if($check['detail'])<div class="text-sm text-gray-500">{{ $check['detail'] }}</div>@endif
                        </div>
                        @if($check['link'] && $check['status'] !== 'pass')
                            <a href="{{ url(trim(config('accounting.route_prefix', 'accounting'), '/').'/'.$check['link']) }}" class="text-sm font-semibold text-indigo-700 hover:underline">Open</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @if($checklist['year_end'] && $checklist['closing_entry'])
            <div class="bg-white shadow rounded-lg">
                <div class="border-b p-4">
                    <h3 class="font-semibold text-gray-700">Closing entry preview</h3>
                    <p class="text-sm text-gray-500">Zeroes every income and expense account for the year and moves the result ({{ number_format((float) $checklist['closing_entry']['net_income'], 2) }}) to {{ $checklist['closing_entry']['retained_earnings'] ? $checklist['closing_entry']['retained_earnings']['account_code'].' '.$checklist['closing_entry']['retained_earnings']['account_name'] : 'retained earnings' }}.</p>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50"><tr>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Account</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600">Debit</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600">Credit</th>
                    </tr></thead>
                    <tbody>
                        @forelse($checklist['closing_entry']['lines'] as $line)
                            <tr class="border-t">
                                <td class="py-1 px-3"><span class="font-mono">{{ $line['account_code'] }}</span> {{ $line['account_name'] }}</td>
                                <td class="py-1 px-3 text-right font-mono">{{ (float) $line['debit'] > 0 ? number_format((float) $line['debit'], 2) : '' }}</td>
                                <td class="py-1 px-3 text-right font-mono">{{ (float) $line['credit'] > 0 ? number_format((float) $line['credit'], 2) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-gray-500">No income or expense balances: no closing entry is needed.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            @if($isOpen)
                @can('periods.close')
                    <form method="POST" action="{{ route($checklist['year_end'] ? 'accounting.periods.close-fiscal-year' : 'accounting.periods.close', $period['id']) }}" onsubmit="return confirm(@js($checklist['year_end'] ? "Post the year-end closing entry and close {$period['name']}?" : "Close {$period['name']}? No entries can be posted into it afterwards."))">
                        @csrf
                        <button type="submit" @disabled(! $checklist['can_close']) class="inline-flex items-center px-4 py-2 bg-indigo-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600 disabled:opacity-40 disabled:cursor-not-allowed">
                            {{ $checklist['year_end'] ? 'Post closing entry & close year' : 'Close '.$period['name'] }}
                        </button>
                    </form>
                @endcan
                @unless($checklist['can_close'])<span class="text-sm text-gray-500">Fix the items marked ✕ to close.</span>@endunless
            @else
                @can('periods.reopen')
                    <form method="POST" action="{{ route('accounting.periods.reopen', $period['id']) }}" class="flex flex-wrap items-end gap-2 rounded-md border border-amber-200 bg-amber-50 p-3">
                        @csrf
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700">Reason for reopening</label>
                            <input id="reason" name="reason" required minlength="5" class="mt-1 rounded-md border-gray-300 text-sm w-80" value="{{ old('reason') }}">
                            @error('reason')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-600">Reopen {{ $period['name'] }}</button>
                        @if($period['closing_journal_entry_id'])<p class="w-full text-xs text-gray-600">The year-end closing entry will be reversed; close the year again afterwards.</p>@endif
                    </form>
                @endcan
            @endif
        </div>
    </div></div>
</x-accounting::app-layout>
