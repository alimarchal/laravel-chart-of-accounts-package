<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Journal Entry #{{ $journalEntry->id }} — {{ $journalEntry->reference }}</h2>
            <div class="flex gap-2">
                @if ($journalEntry->status === 'draft')
                    @can('journal-entries.post')
                    <form method="POST" action="{{ route('accounting.journal-entries.post', $journalEntry) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition" onclick="return confirm('Post this entry?')">Post</button>
                    </form>
                    @endcan
                @endif
                @if ($journalEntry->status === 'posted')
                    @can('journal-entries.reverse')
                    <form method="POST" action="{{ route('accounting.journal-entries.reverse', $journalEntry) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-500 transition" onclick="return confirm('Reverse this entry?')">Reverse</button>
                    </form>
                    @endcan
                @endif
                @if (in_array($journalEntry->status, ['draft', 'posted']))
                    @can('journal-entries.void')
                    <form method="POST" action="{{ route('accounting.journal-entries.void', $journalEntry) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 transition" onclick="return confirm('Void this entry?')">Void</button>
                    </form>
                    @endcan
                @endif
                <a href="{{ route('accounting.journal-entries.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <x-accounting::status-message class="mb-4" />
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div><x-accounting::label value="Date" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$journalEntry->entry_date->format('Y-m-d')" disabled readonly /></div>
                <div><x-accounting::label value="Period" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($journalEntry->accountingPeriod)->name" disabled readonly /></div>
                <div><x-accounting::label value="Currency" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($journalEntry->currency)->code" disabled readonly /></div>
                <div><x-accounting::label value="Status" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100 capitalize" :value="$journalEntry->status" disabled readonly /></div>
                <div><x-accounting::label value="Reference" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$journalEntry->reference" disabled readonly /></div>
                <div class="md:col-span-3"><x-accounting::label value="Description" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$journalEntry->description" disabled readonly /></div>
            </div>
        </div>

        @if ($journalEntry->status === 'draft')
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-4">
            <h3 class="font-semibold text-gray-700 mb-4">Edit Draft</h3>
            @livewire('accounting::journal-entry-form', ['entry' => $journalEntry])
        </div>
        @endif

        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="font-semibold text-gray-700 mb-2">Entry Lines</h3>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">#</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Account</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Cost Center</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600">Debit</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600">Credit</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Note</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($journalEntry->lines as $line)
                    <tr class="border-t border-gray-100">
                        <td class="py-1 px-3">{{ $line->line_no }}</td>
                        <td class="py-1 px-3">{{ optional($line->account)->account_code }} — {{ optional($line->account)->account_name }}</td>
                        <td class="py-1 px-3">{{ optional($line->costCenter)->name }}</td>
                        <td class="py-1 px-3 text-right font-mono">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                        <td class="py-1 px-3 text-right font-mono">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                        <td class="py-1 px-3 text-gray-500">{{ $line->description }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                    <tr>
                        <td colspan="3" class="py-2 px-3 text-right font-semibold">Totals</td>
                        <td class="py-2 px-3 text-right font-semibold font-mono">{{ number_format($journalEntry->lines->sum('debit'), 2) }}</td>
                        <td class="py-2 px-3 text-right font-semibold font-mono">{{ number_format($journalEntry->lines->sum('credit'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div></div>
</x-accounting::app-layout>
