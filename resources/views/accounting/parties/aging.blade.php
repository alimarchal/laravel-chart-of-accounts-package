<x-accounting::app-layout title="Ageing">
    <x-slot name="header">
        <x-accounting::page-header :title="$report['side'] === 'receivable' ? 'Aged Receivables by Customer' : 'Aged Payables by Supplier'" :showSearch="false" backRoute="accounting.parties.index" />
    </x-slot>
    @php($m = fn ($v) => number_format((float) $v, 2))
    @php($columns = ['not_due' => 'Not due', 'days_1_30' => '1–30', 'days_31_60' => '31–60', 'days_61_90' => '61–90', 'over_90' => 'Over 90', 'unapplied' => 'Unapplied', 'total' => 'Total'])
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">Open invoices (bills) by days past due, less payments and credits not yet applied.</p>
            <div class="flex gap-3"><a class="text-xs font-semibold uppercase text-indigo-700 hover:underline" href="{{ route('accounting.receivables.aging', ['side' => $report['side'] === 'receivable' ? 'payable' : 'receivable']) }}">{{ $report['side'] === 'receivable' ? 'Payables' : 'Receivables' }}</a>@foreach (['csv', 'xlsx', 'pdf'] as $format)<a href="{{ route('accounting.receivables.aging.export', ['format' => $format, 'side' => $report['side'], 'as_of' => $report['as_of']]) }}" class="text-xs font-semibold uppercase text-indigo-700 hover:underline">{{ $format }}</a>@endforeach</div>
        </div>
        <form method="GET" action="{{ route('accounting.receivables.aging') }}" class="flex items-end gap-3"><input type="hidden" name="side" value="{{ $report['side'] }}"><div><x-accounting::label for="as_of" value="As of" /><x-accounting::input id="as_of" name="as_of" type="date" class="mt-1 block" :value="$report['as_of']" /></div><button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Apply</button></form>
        @if ((float) $reconciliation['difference'] !== 0.0)
            <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">The sub-ledger and the control account differ: control account {{ $m($reconciliation['ledger']) }} · sub-ledger {{ $m($reconciliation['subledger']) }} · difference {{ $m($reconciliation['difference']) }}. Look for manual entries on the control account.</div>
        @else
            <div class="text-sm text-gray-500">The sub-ledger agrees with the control account ({{ $m($reconciliation['ledger']) }}).</div>
        @endif
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">{{ $report['side'] === 'receivable' ? 'Customer' : 'Supplier' }}</th>@foreach ($columns as $label)<th class="py-2 px-3 text-right font-medium text-gray-600">{{ $label }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($report['rows'] as $row)
                <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.parties.show', ['party' => $row['party_id'], 'side' => $report['side']]) }}" class="text-indigo-700 hover:underline">{{ $row['code'] }} {{ $row['name'] }}</a></td>@foreach ($columns as $key => $label)<td class="py-2 px-3 text-right tabular-nums {{ $key === 'over_90' && (float) $row[$key] > 0 ? 'text-red-700' : '' }}">{{ (float) $row[$key] === 0.0 ? '—' : $m($row[$key]) }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-gray-500">Nothing outstanding.</td></tr>
            @endforelse
                <tr class="border-t bg-gray-50 font-medium"><td class="py-2 px-3">Total</td>@foreach ($columns as $key => $label)<td class="py-2 px-3 text-right tabular-nums">{{ $m($report['totals'][$key]) }}</td>@endforeach</tr>
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
