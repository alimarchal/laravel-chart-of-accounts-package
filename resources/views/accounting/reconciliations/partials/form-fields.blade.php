@php $reconciliation = $reconciliation ?? null; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-accounting::label for="bank_account_id" value="Bank Account" />
        <select id="bank_account_id" name="bank_account_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
            <option value="">Select Bank Account</option>
            @foreach ($bankAccounts as $ba)
            <option value="{{ $ba->id }}" {{ old('bank_account_id', optional($reconciliation)->bank_account_id) == $ba->id ? 'selected' : '' }}>{{ $ba->account_name }} ({{ $ba->bank_name }})</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-accounting::label for="statement_date" value="Statement Date" />
        <x-accounting::input id="statement_date" type="date" name="statement_date" class="mt-1 block w-full" :value="old('statement_date', optional($reconciliation)->statement_date?->format('Y-m-d'))" required />
    </div>
    <div>
        <x-accounting::label for="statement_balance" value="Statement Balance" />
        <x-accounting::input id="statement_balance" type="number" step="0.01" name="statement_balance" class="mt-1 block w-full" :value="old('statement_balance', optional($reconciliation)->statement_balance)" required />
    </div>
    <div>
        <x-accounting::label for="book_balance" value="Book Balance" />
        <x-accounting::input id="book_balance" type="number" step="0.01" name="book_balance" class="mt-1 block w-full" :value="old('book_balance', optional($reconciliation)->book_balance)" required />
    </div>
    <div>
        <x-accounting::label for="status" value="Status" />
        <select id="status" name="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
            <option value="pending" {{ old('status', optional($reconciliation)->status ?? 'pending') === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="in_progress" {{ old('status', optional($reconciliation)->status) === 'in_progress' ? 'selected' : '' }}>In Progress</option>
            <option value="completed" {{ old('status', optional($reconciliation)->status) === 'completed' ? 'selected' : '' }}>Completed</option>
        </select>
    </div>
</div>
