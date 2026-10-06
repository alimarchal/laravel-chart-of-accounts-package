<x-accounting::app-layout title="Receipt or Payment">
    <x-slot name="header">
        <x-accounting::page-header :title="$kind === 'receipt' ? 'Receive Payment from a Customer' : 'Pay a Supplier'" :showSearch="false" backRoute="accounting.party-payments.index" />
    </x-slot>
    @php($input = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm')
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Posted straight away: the bank account against the party's control account.</p>
        <form method="POST" action="{{ route('accounting.party-payments.store') }}" class="space-y-4" x-data="{ party: @js((string) old('party_id', $partyId ?? '')), docs: @js($openDocuments), get open() { return this.docs.filter(d => String(d.party_id) === this.party) } }">
            @csrf
            <input type="hidden" name="kind" value="{{ $kind }}">
            <div class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-3">
                <div><x-accounting::label for="party_id" :value="$kind === 'receipt' ? 'Customer' : 'Supplier'" /><select id="party_id" name="party_id" x-model="party" required class="{{ $input }}"><option value="">Select</option>@foreach ($parties as $party)<option value="{{ $party->id }}">{{ $party->code }} - {{ $party->name }}</option>@endforeach</select></div>
                <div><x-accounting::label for="payment_date" value="Date" /><x-accounting::input id="payment_date" name="payment_date" type="date" class="mt-1 block w-full" :value="old('payment_date', $today)" required /></div>
                <div><x-accounting::label for="amount" value="Amount" /><input id="amount" name="amount" type="number" step="0.01" min="0" required value="{{ old('amount') }}" class="{{ $input }}"></div>
                <div><x-accounting::label for="account_id" :value="$kind === 'receipt' ? 'Deposited to' : 'Paid from'" /><select id="account_id" name="account_id" required class="{{ $input }}"><option value="">Select a bank or cash account</option>@foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>@endforeach</select></div>
                <div><x-accounting::label for="method" value="Method" /><x-accounting::input id="method" name="method" class="mt-1 block w-full" :value="old('method')" /></div>
                <div><x-accounting::label for="reference" value="Reference (cheque / transfer no.)" /><x-accounting::input id="reference" name="reference" class="mt-1 block w-full" :value="old('reference')" /></div>
            </div>
            <div class="bg-white shadow rounded-lg p-5 space-y-3">
                <h3 class="font-semibold text-gray-800">Settles <span class="text-xs font-normal text-gray-500">enter amounts against specific {{ $kind === 'receipt' ? 'invoices' : 'bills' }}, or leave them empty to settle the oldest first; what is not allocated stays on the account</span></h3>
                <div class="text-sm text-gray-500" x-show="open.length === 0" x-text="party === '' ? 'Choose a party first.' : 'Nothing open for this party.'"></div>
                <div class="overflow-x-auto" x-show="open.length > 0"><table class="min-w-full text-sm"><tbody>
                    <template x-for="(doc, i) in open" :key="doc.id"><tr class="border-t first:border-t-0"><td class="py-2 px-3" x-text="doc.number"></td><td class="py-2 px-3" x-text="'due ' + doc.due_date"></td><td class="py-2 px-3 text-right tabular-nums" x-text="'open ' + Number(doc.open).toFixed(2)"></td><td class="py-2 px-3 text-right"><input type="hidden" :name="`allocations[${i}][document_id]`" :value="doc.id"><input type="number" step="0.01" min="0" placeholder="Amount" :name="`allocations[${i}][amount]`" class="w-32 border-gray-300 rounded-md text-sm"></td></tr></template>
                </tbody></table></div>
                <input type="hidden" name="auto_allocate" value="1">
            </div>
            <button class="px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">Record</button>
        </form>
    </div></div>
</x-accounting::app-layout>
