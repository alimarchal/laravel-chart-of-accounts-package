<x-accounting::app-layout title="Bank Statement">
    <x-slot name="header">
        <x-accounting::page-header :title="$statement['file_name']" :showSearch="false" backRoute="accounting.bank-statements.index" />
    </x-slot>
    @php($styles = ['unmatched' => 'bg-amber-100 text-amber-800', 'matched' => 'bg-emerald-100 text-emerald-800', 'created' => 'bg-blue-100 text-blue-800', 'ignored' => 'bg-gray-100 text-gray-600'])
    @php($canMatch = ! $statement['reconciliation_id'])
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">{{ $statement['bank'] }} · {{ $statement['from_date'] }} → {{ $statement['to_date'] }} · {{ $statement['lines_count'] }} transactions, {{ $statement['unmatched_count'] }} to match @if($statement['reconciliation_id'])<span class="rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800">reconciled</span>@endif</p>
            @can('bank-statements.match') @if($canMatch)
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('accounting.bank-statements.update', $statement['id']) }}" class="flex items-center gap-2">@csrf @method('PUT')
                    <label class="text-sm text-gray-600" for="closing_balance">Closing balance</label><input id="closing_balance" name="closing_balance" type="number" step="0.01" value="{{ $statement['closing_balance'] }}" class="border-gray-300 rounded-md text-sm w-36"><button class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Save</button></form>
                <form method="POST" action="{{ route('accounting.bank-statements.auto-match', $statement['id']) }}">@csrf<button class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Auto-match</button></form>
                <form method="POST" action="{{ route('accounting.bank-statements.reconcile', $statement['id']) }}">@csrf<button class="px-3 py-1.5 bg-blue-950 rounded-md text-xs font-semibold text-white uppercase tracking-widest" @disabled($statement['unmatched_count'] > 0)>Reconcile</button></form>
                <form method="POST" action="{{ route('accounting.bank-statements.destroy', $statement['id']) }}" onsubmit="return confirm('Delete this statement? Its matches are released.')">@csrf @method('DELETE')<button class="px-3 py-1.5 text-red-700 text-xs font-semibold uppercase tracking-widest hover:underline">Delete</button></form>
            </div>
            @endif @endcan
        </div>
        <div class="bg-white shadow rounded-lg overflow-x-auto" x-data="{ open: null, candidates: [], account: '',
            async load(id) { this.open = id; this.candidates = []; const r = await fetch('{{ route('accounting.bank-statements.lines.candidates', '__ID__') }}'.replace('__ID__', id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); this.candidates = (await r.json()).data; } }">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Date</th><th class="py-2 px-3 text-left font-medium text-gray-600">Description</th><th class="py-2 px-3 text-left font-medium text-gray-600">Reference</th><th class="py-2 px-3 text-right font-medium text-gray-600">Withdrawal</th><th class="py-2 px-3 text-right font-medium text-gray-600">Deposit</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th><th class="py-2 px-3 text-right font-medium text-gray-600">Actions</th></tr></thead>
                <tbody>
                @foreach ($lines as $line)
                    <tr class="border-t">
                        <td class="py-2 px-3">{{ $line['txn_date'] }}</td><td class="py-2 px-3">{{ $line['description'] }}</td><td class="py-2 px-3">{{ $line['reference'] }}</td>
                        <td class="py-2 px-3 text-right tabular-nums">{{ (float) $line['withdrawal'] ? number_format((float) $line['withdrawal'], 2) : '' }}</td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $line['deposit'] ? number_format((float) $line['deposit'], 2) : '' }}</td>
                        <td class="py-2 px-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $styles[$line['status']] }}">{{ $line['status'] }}</span> @if($line['journal_entry_id'])<a class="ml-2 text-xs text-indigo-700 hover:underline" href="{{ route('accounting.journal-entries.show', $line['journal_entry_id']) }}">{{ $line['voucher_number'] ?? 'entry #'.$line['journal_entry_id'] }}{{ $line['journal_status'] === 'draft' ? ' (draft)' : '' }}</a>@endif</td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">
                            @can('bank-statements.match') @if($canMatch)
                                @if ($line['status'] === 'unmatched')
                                    <button type="button" class="text-indigo-700 hover:underline" @click="open === {{ $line['id'] }} ? open = null : load({{ $line['id'] }})">Match / book</button>
                                    <form method="POST" action="{{ route('accounting.bank-statements.lines.ignore', $line['id']) }}" class="inline ml-2">@csrf<button class="text-gray-600 hover:underline">Ignore</button></form>
                                @elseif ($line['status'] === 'ignored')
                                    <form method="POST" action="{{ route('accounting.bank-statements.lines.ignore', $line['id']) }}" class="inline">@csrf<input type="hidden" name="ignored" value="0"><button class="text-gray-600 hover:underline">Restore</button></form>
                                @else
                                    <form method="POST" action="{{ route('accounting.bank-statements.lines.unmatch', $line['id']) }}" class="inline">@csrf<button class="text-amber-700 hover:underline">Unmatch</button></form>
                                @endif
                            @endif @endcan
                        </td>
                    </tr>
                    @if ($line['status'] === 'unmatched')
                    <tr class="border-t bg-gray-50" x-show="open === {{ $line['id'] }}" x-cloak>
                        <td colspan="7" class="px-3 py-3"><div class="grid gap-4 md:grid-cols-2">
                            <div><div class="mb-1 text-xs font-medium uppercase text-gray-500">Ledger lines with this amount</div>
                                <div x-show="candidates.length === 0" class="text-sm text-gray-500">None found nearby.</div>
                                <template x-for="candidate in candidates" :key="candidate.id"><form method="POST" action="{{ route('accounting.bank-statements.lines.match', $line['id']) }}" class="flex items-center justify-between gap-2 py-1 text-sm">@csrf<input type="hidden" name="journal_entry_line_id" :value="candidate.id"><span x-text="`${candidate.date} · ${candidate.voucher_number ?? '—'} · ${candidate.description ?? candidate.reference ?? ''}`"></span><button class="px-2 py-1 bg-blue-950 rounded text-xs text-white">Match</button></form></template>
                            </div>
                            <form method="POST" action="{{ route('accounting.bank-statements.lines.create-entry', $line['id']) }}">@csrf
                                <div class="mb-1 text-xs font-medium uppercase text-gray-500">Or book it to an account</div>
                                <div class="flex gap-2"><select name="chart_of_account_id" required class="flex-1 border-gray-300 rounded-md text-sm"><option value="">Select an account</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} - {{ $account->account_name }}</option>@endforeach</select><button class="px-3 py-1.5 bg-green-700 rounded-md text-xs font-semibold text-white uppercase tracking-widest">Book</button></div>
                            </form>
                        </div></td>
                    </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div></div>
</x-accounting::app-layout>
