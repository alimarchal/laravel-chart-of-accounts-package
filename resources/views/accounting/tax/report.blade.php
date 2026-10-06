<x-accounting::app-layout title="Tax Report">
    <x-slot name="header">
        <x-accounting::page-header title="Tax Report" :showSearch="false" backRoute="accounting.tax.index" />
    </x-slot>
    @php($query = http_build_query(array_filter(['date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'tax_code_id' => $filters['tax_code_id']])))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">Taxable amounts and tax by tax code, from posted entries in the base currency.</p>
            <div class="flex gap-3">@foreach (['csv', 'xlsx', 'pdf'] as $format)<a href="{{ route('accounting.tax.returns.export', ['format' => $format]) }}?{{ $query }}" class="text-xs font-semibold uppercase text-indigo-700 hover:underline">{{ $format }}</a>@endforeach</div>
        </div>
        <form method="GET" action="{{ route('accounting.tax.returns.report') }}" class="flex flex-wrap items-end gap-3">
            <div><x-accounting::label for="date_from" value="From" /><x-accounting::input id="date_from" name="date_from" type="date" class="mt-1 block" :value="$filters['date_from']" /></div>
            <div><x-accounting::label for="date_to" value="To" /><x-accounting::input id="date_to" name="date_to" type="date" class="mt-1 block" :value="$filters['date_to']" /></div>
            <div><x-accounting::label for="tax_code_id" value="Tax code" /><select id="tax_code_id" name="tax_code_id" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm"><option value="">All</option>@foreach ($taxCodes as $code)<option value="{{ $code->id }}" @selected((string) ($filters['tax_code_id'] ?? '') === (string) $code->id)>{{ $code->code }}</option>@endforeach</select></div>
            <button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Apply</button>
        </form>
        <div class="grid gap-3 md:grid-cols-5">
            @foreach ([['Output tax', 'output_tax'], ['Input tax', 'input_tax'], ['Net payable', 'net_payable'], ['Withheld by us', 'withheld'], ['Advance tax', 'advance']] as [$label, $key])
                <div class="bg-white shadow rounded-lg p-3"><div class="text-xs uppercase text-gray-500">{{ $label }}</div><div class="mt-1 text-lg tabular-nums">{{ number_format((float) $report['totals'][$key], 2) }}</div></div>
            @endforeach
        </div>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Tax code</th><th class="py-2 px-3 text-left font-medium text-gray-600">Kind</th><th class="py-2 px-3 text-right font-medium text-gray-600">Taxable base</th><th class="py-2 px-3 text-right font-medium text-gray-600">Tax</th><th class="py-2 px-3 text-right font-medium text-gray-600">Documents</th></tr></thead>
            <tbody>
            @forelse ($report['rows'] as $row)
                <tr class="border-t"><td class="py-2 px-3">{{ $row['code'] }} <span class="text-xs text-gray-500">{{ $row['name'] }}</span></td><td class="py-2 px-3">{{ $row['kind'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row['base'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row['tax'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $row['documents'] }}</td></tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-gray-500">No taxed documents in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <h3 class="text-sm font-medium text-gray-700">Documents</h3>
        <div class="bg-white shadow rounded-lg overflow-x-auto max-h-[28rem] overflow-y-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50 sticky top-0"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Date</th><th class="py-2 px-3 text-left font-medium text-gray-600">Entry</th><th class="py-2 px-3 text-left font-medium text-gray-600">Reference</th><th class="py-2 px-3 text-left font-medium text-gray-600">Tax code</th><th class="py-2 px-3 text-left font-medium text-gray-600">Line</th><th class="py-2 px-3 text-right font-medium text-gray-600">Amount</th></tr></thead>
            <tbody>
            @foreach ($detail as $row)
                <tr class="border-t"><td class="py-2 px-3">{{ $row['entry_date'] }}</td><td class="py-2 px-3"><a class="text-indigo-700 hover:underline" href="{{ route('accounting.journal-entries.show', $row['journal_entry_id']) }}">{{ $row['voucher_number'] ?? '#'.$row['journal_entry_id'] }}</a></td><td class="py-2 px-3">{{ $row['reference'] }}</td><td class="py-2 px-3">{{ $row['tax_code'] }}</td><td class="py-2 px-3">{{ $row['role'] === 'base' ? 'taxable amount' : 'tax'.($row['rate'] ? ' @ '.(float) $row['rate'].'%' : '') }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row['amount'], 2) }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
