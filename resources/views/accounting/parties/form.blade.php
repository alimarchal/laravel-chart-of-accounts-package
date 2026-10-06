<x-accounting::app-layout :title="$party ? 'Edit Customer or Supplier' : 'New Customer or Supplier'">
    <x-slot name="header">
        <x-accounting::page-header :title="$party ? 'Edit '.$party['name'] : 'New Customer or Supplier'" :showSearch="false" backRoute="accounting.parties.index" />
    </x-slot>
    @php($v = fn (string $key, $default = '') => old($key, $party[$key] ?? $default))
    @php($input = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm')
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Invoices and receipts post to the receivables control account, bills and payments to the payables one, unless you set accounts here.</p>
        <form method="POST" action="{{ $party ? route('accounting.parties.update', $party['id']) : route('accounting.parties.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-3">
            @csrf @if($party) @method('PUT') @endif
            <div><x-accounting::label for="type" value="Type" /><select id="type" name="type" class="{{ $input }}">@foreach (['customer' => 'Customer', 'supplier' => 'Supplier', 'both' => 'Both'] as $value => $label)<option value="{{ $value }}" @selected($v('type', 'customer') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><x-accounting::label for="code" value="Code" /><x-accounting::input id="code" name="code" class="mt-1 block w-full" :value="$v('code')" required /></div>
            <div><x-accounting::label for="name" value="Name" /><x-accounting::input id="name" name="name" class="mt-1 block w-full" :value="$v('name')" required /></div>
            <div><x-accounting::label for="email" value="Email" /><x-accounting::input id="email" name="email" type="email" class="mt-1 block w-full" :value="$v('email')" /></div>
            <div><x-accounting::label for="phone" value="Phone" /><x-accounting::input id="phone" name="phone" class="mt-1 block w-full" :value="$v('phone')" /></div>
            <div><x-accounting::label for="tax_number" value="Tax number (NTN / STRN)" /><x-accounting::input id="tax_number" name="tax_number" class="mt-1 block w-full" :value="$v('tax_number')" /></div>
            <div><x-accounting::label for="payment_terms_days" value="Payment terms (days)" /><x-accounting::input id="payment_terms_days" name="payment_terms_days" type="number" min="0" class="mt-1 block w-full" :value="$v('payment_terms_days', 30)" /></div>
            <div><x-accounting::label for="credit_limit" value="Credit limit" /><input id="credit_limit" name="credit_limit" type="number" step="0.01" min="0" value="{{ $v('credit_limit') }}" class="{{ $input }}"></div>
            <div class="md:col-span-3"><x-accounting::label for="address" value="Address" /><x-accounting::input id="address" name="address" class="mt-1 block w-full" :value="$v('address')" /></div>
            <div><x-accounting::label for="receivable_account_id" value="Receivable account (optional)" /><select id="receivable_account_id" name="receivable_account_id" class="{{ $input }}"><option value="">Control account</option>@foreach ($receivableAccounts as $account)<option value="{{ $account->id }}" @selected((string) $v('receivable_account_id') === (string) $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>@endforeach</select></div>
            <div><x-accounting::label for="payable_account_id" value="Payable account (optional)" /><select id="payable_account_id" name="payable_account_id" class="{{ $input }}"><option value="">Control account</option>@foreach ($payableAccounts as $account)<option value="{{ $account->id }}" @selected((string) $v('payable_account_id') === (string) $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>@endforeach</select></div>
            <label class="flex items-center gap-2 pt-7 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) $v('is_active', true))> Active</label>
            <div class="md:col-span-3"><x-accounting::label for="notes" value="Notes" /><x-accounting::input id="notes" name="notes" class="mt-1 block w-full" :value="$v('notes')" /></div>
            <div class="md:col-span-3"><button class="px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">Save</button></div>
        </form>
    </div></div>
</x-accounting::app-layout>
