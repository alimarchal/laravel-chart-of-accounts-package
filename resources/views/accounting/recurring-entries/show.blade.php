<x-accounting::app-layout :title="$entry['name']">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $entry['name'] }}</h2>
            <div class="flex gap-2">
                @if ($entry['status'] !== 'finished')@can('recurring-entries.run')<form method="POST" action="{{ route('accounting.recurring-entries.run', $entry['id']) }}">@csrf<button class="inline-flex items-center px-4 py-2 bg-indigo-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600">Generate now</button></form>@endcan @endif
                @can('recurring-entries.update')<a href="{{ route('accounting.recurring-entries.edit', $entry['id']) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">Edit</a>@endcan
                @if (count($runs) === 0)@can('recurring-entries.delete')<form method="POST" action="{{ route('accounting.recurring-entries.destroy', $entry['id']) }}" onsubmit="return confirm('Delete this recurring entry?')">@csrf @method('DELETE')<button class="inline-flex items-center px-4 py-2 bg-white border border-red-300 text-red-700 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-red-50">Delete</button></form>@endcan @endif
                <a href="{{ route('accounting.recurring-entries.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 rounded-md font-semibold text-xs text-white uppercase tracking-widest"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    @php($runStyles = ['posted' => 'bg-emerald-100 text-emerald-800', 'submitted' => 'bg-blue-100 text-blue-800', 'draft' => 'bg-amber-100 text-amber-800', 'failed' => 'bg-red-100 text-red-700'])
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
            <div class="bg-white shadow rounded-lg p-5">
                <h3 class="font-semibold text-gray-800 mb-2">The entry</h3>
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-gray-500"><th class="py-1 font-medium">Account</th><th class="py-1 font-medium">Cost center</th><th class="py-1 text-right font-medium">Debit</th><th class="py-1 text-right font-medium">Credit</th></tr></thead>
                    <tbody>@foreach ($entry['lines'] as $line)<tr class="border-t"><td class="py-1.5">{{ $line['account'] }}</td><td class="py-1.5">{{ $line['cost_center'] ?? '—' }}</td><td class="py-1.5 text-right tabular-nums">{{ (float) $line['debit'] > 0 ? number_format((float) $line['debit'], 2) : '' }}</td><td class="py-1.5 text-right tabular-nums">{{ (float) $line['credit'] > 0 ? number_format((float) $line['credit'], 2) : '' }}</td></tr>@endforeach</tbody>
                </table>
                @if ($entry['description'])<p class="mt-3 text-sm text-gray-600">{{ $entry['description'] }}</p>@endif
            </div>
            <div class="bg-white shadow rounded-lg p-5 text-sm space-y-2 h-fit">
                <h3 class="font-semibold text-gray-800">Schedule <span class="ml-1 rounded border px-1.5 text-xs">{{ $entry['status'] }}</span></h3>
                <div>{{ ucfirst($entry['frequency']) }}{{ $entry['interval'] > 1 ? ', every '.$entry['interval'] : '' }}{{ $entry['day_of_month'] ? ', day '.$entry['day_of_month'] : '' }} · {{ $entry['mode'] === 'post' ? 'posts automatically' : 'creates a draft' }}</div>
                <div>Starts {{ $entry['start_date'] }}{{ $entry['end_date'] ? ', ends '.$entry['end_date'] : '' }}</div>
                <div>Generated {{ $entry['runs_count'] }}{{ $entry['max_runs'] ? ' of '.$entry['max_runs'] : '' }}</div>
                <div class="pt-1 font-medium">Upcoming</div>
                <ul class="text-gray-500">@forelse ($upcoming as $date)<li>{{ $date }}</li>@empty<li>Nothing left to generate.</li>@endforelse</ul>
            </div>
        </div>
        <div class="bg-white shadow rounded-lg p-5">
            <h3 class="font-semibold text-gray-800 mb-2">Generated entries</h3>
            @if (count($runs))
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-gray-500"><th class="py-1 font-medium">Date</th><th class="py-1 font-medium">Result</th><th class="py-1 font-medium">Entry</th><th class="py-1 font-medium">Note</th></tr></thead>
                    <tbody>@foreach ($runs as $run)
                        <tr class="border-t align-top"><td class="py-1.5">{{ $run['run_date'] }}</td><td class="py-1.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $runStyles[$run['status']] ?? '' }}">{{ $run['status'] }}</span></td>
                            <td class="py-1.5">@if ($run['journal_entry_id'])<a href="{{ route('accounting.journal-entries.show', $run['journal_entry_id']) }}" class="text-indigo-700 hover:underline">{{ $run['voucher_number'] ?? 'Draft #'.$run['journal_entry_id'] }}</a>@else — @endif</td>
                            <td class="py-1.5 text-gray-500">{{ $run['error'] }}</td></tr>
                    @endforeach</tbody>
                </table>
            @else<p class="text-sm text-gray-500">Nothing generated yet.</p>@endif
        </div>
    </div></div>
</x-accounting::app-layout>
