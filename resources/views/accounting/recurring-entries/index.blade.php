<x-accounting::app-layout title="Recurring Entries">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Recurring Entries</h2>
            <div class="flex gap-2">
                @can('recurring-entries.create')<a href="{{ route('accounting.recurring-entries.create') }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">New recurring entry</a>@endcan
                <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    @php($styles = ['active' => 'bg-emerald-100 text-emerald-800', 'paused' => 'bg-amber-100 text-amber-800', 'finished' => 'bg-gray-100 text-gray-600'])
    @php($units = ['daily' => 'days', 'weekly' => 'weeks', 'monthly' => 'months', 'quarterly' => 'quarters', 'yearly' => 'years'])

    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <p class="text-sm text-gray-600">Entries that repeat on a schedule — rent, subscriptions, depreciation, accruals — generated daily by <code class="text-xs">accounting:run-recurring</code>.</p>
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Name</th><th class="py-2 px-3 text-left font-medium text-gray-600">Repeats</th>
                    <th class="py-2 px-3 text-right font-medium text-gray-600">Amount</th><th class="py-2 px-3 text-left font-medium text-gray-600">Next run</th>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Generated</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th><th class="py-2 px-3 text-right font-medium text-gray-600">Actions</th>
                </tr></thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr class="border-t">
                            <td class="py-2 px-3"><a href="{{ route('accounting.recurring-entries.show', $entry['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $entry['name'] }}</a><div class="text-xs text-gray-500">{{ $entry['mode'] === 'post' ? 'posts automatically' : 'creates a draft' }}</div></td>
                            <td class="py-2 px-3">{{ $entry['interval'] > 1 ? 'every '.$entry['interval'].' '.($units[$entry['frequency']] ?? '') : $entry['frequency'] }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $entry['amount'], 2) }}</td>
                            <td class="py-2 px-3">{{ $entry['next_run_date'] ?? '—' }}</td>
                            <td class="py-2 px-3">{{ $entry['runs_count'] }}{{ $entry['max_runs'] ? ' / '.$entry['max_runs'] : '' }}</td>
                            <td class="py-2 px-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $styles[$entry['status']] }}">{{ $entry['status'] }}</span></td>
                            <td class="py-2 px-3 text-right whitespace-nowrap">
                                @if ($entry['status'] !== 'finished')@can('recurring-entries.run')<form method="POST" action="{{ route('accounting.recurring-entries.run', $entry['id']) }}" class="inline">@csrf<button class="text-indigo-700 hover:underline">Generate now</button></form>@endcan @endif
                                @if ($entry['status'] === 'active')@can('recurring-entries.update')<form method="POST" action="{{ route('accounting.recurring-entries.pause', $entry['id']) }}" class="inline ml-2">@csrf<button class="text-amber-700 hover:underline">Pause</button></form>@endcan @endif
                                @if ($entry['status'] === 'paused')@can('recurring-entries.update')<form method="POST" action="{{ route('accounting.recurring-entries.resume', $entry['id']) }}" class="inline ml-2">@csrf<button class="text-emerald-700 hover:underline">Resume</button></form>@endcan @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-gray-500">No recurring entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div></div>
</x-accounting::app-layout>
