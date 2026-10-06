<x-accounting::app-layout title="Import Bank Statement">
    <x-slot name="header">
        <x-accounting::page-header title="Import Bank Statement" :showSearch="false" backRoute="accounting.bank-statements.index" />
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Upload a CSV or Excel statement. Columns are recognised by name (Date, Description, Reference, Withdrawal / Debit, Deposit / Credit or a signed Amount, Balance). Transactions imported before are skipped. Nothing is saved until you confirm the preview.</p>
        <form method="POST" action="{{ route('accounting.bank-statements.import.preview') }}" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-3">
            @csrf
            <div><x-accounting::label for="bank_account_id" value="Bank account" /><select id="bank_account_id" name="bank_account_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">@foreach ($banks as $bank)<option value="{{ $bank['id'] }}" @selected(old('bank_account_id', $preview['bank_account_id'] ?? null) == $bank['id'])>{{ $bank['name'] }}</option>@endforeach</select></div>
            <div><x-accounting::label for="file" value="File" /><input id="file" name="file" type="file" accept=".csv,.txt,.xlsx" required class="mt-1 block w-full text-sm"></div>
            <div class="flex items-end"><button class="px-4 py-2 bg-blue-950 rounded-md font-semibold text-xs text-white uppercase tracking-widest">Preview</button></div>
        </form>
        @if ($preview)
        @php($styles = ['new' => 'bg-emerald-100 text-emerald-800', 'duplicate' => 'bg-gray-100 text-gray-600', 'error' => 'bg-red-100 text-red-700'])
        <div class="bg-white shadow rounded-lg p-5 space-y-4">
            <h3 class="font-semibold text-gray-800">{{ $preview['filename'] }} <span class="text-xs font-normal text-gray-500">{{ $preview['summary']['new'] }} new · {{ $preview['summary']['duplicate'] }} already imported · {{ $preview['summary']['error'] }} with errors @if($preview['from_date']) · {{ $preview['from_date'] }} → {{ $preview['to_date'] }}@endif</span></h3>
            <div class="max-h-[28rem] overflow-auto"><table class="min-w-full text-sm">
                <thead class="bg-gray-50 sticky top-0"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Row</th><th class="py-2 px-3 text-left font-medium text-gray-600">Date</th><th class="py-2 px-3 text-left font-medium text-gray-600">Description</th><th class="py-2 px-3 text-left font-medium text-gray-600">Reference</th><th class="py-2 px-3 text-right font-medium text-gray-600">Withdrawal</th><th class="py-2 px-3 text-right font-medium text-gray-600">Deposit</th><th class="py-2 px-3 text-left font-medium text-gray-600">Result</th></tr></thead>
                <tbody>
                @foreach ($preview['lines'] as $line)
                    <tr class="border-t"><td class="py-2 px-3">{{ $line['line'] }}</td><td class="py-2 px-3">{{ $line['txn_date'] ?? '—' }}</td><td class="py-2 px-3">{{ $line['description'] }}</td><td class="py-2 px-3">{{ $line['reference'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $line['withdrawal'] ? number_format((float) $line['withdrawal'], 2) : '' }}</td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $line['deposit'] ? number_format((float) $line['deposit'], 2) : '' }}</td><td class="py-2 px-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $styles[$line['status']] }}">{{ $line['status'] }}</span> @if($line['error'])<span class="text-xs text-red-700">{{ $line['error'] }}</span>@endif</td></tr>
                @endforeach
                </tbody>
            </table></div>
            @if ($preview['token'])
            <form method="POST" action="{{ route('accounting.bank-statements.import.store') }}" class="flex flex-wrap items-end gap-4">
                @csrf <input type="hidden" name="token" value="{{ $preview['token'] }}">
                <div><x-accounting::label for="closing_balance" value="Statement closing balance" /><input id="closing_balance" name="closing_balance" type="number" step="0.01" value="{{ $preview['closing_balance'] }}" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm"></div>
                @if ($preview['summary']['new'] > 0)<button class="px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">Import {{ $preview['summary']['new'] }} transactions</button>@endif
            </form>
            @else
            <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">Some rows have errors: correct them and upload the file again.</div>
            @endif
        </div>
        @endif
    </div></div>
</x-accounting::app-layout>
