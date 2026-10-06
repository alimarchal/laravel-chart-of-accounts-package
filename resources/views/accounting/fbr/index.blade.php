<x-accounting::app-layout title="FBR invoices">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">FBR invoices</h2><a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">All modules</a></div></x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        @unless($enabled)<div class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">FBR integration is switched off. Set ACCOUNTING_FBR_ENABLED=true and the seller details in the accounting config to send invoices.</div>@endunless
        @if($enabled && $mode === 'fake')<div class="rounded-md bg-sky-50 border border-sky-200 p-3 text-sm text-sky-800">Test mode: invoices are accepted locally and nothing is sent to FBR. Set ACCOUNTING_FBR_MODE=live and the service URL and token to go live.</div>@endif
        <form method="GET" class="flex gap-3 items-end"><select name="status" class="border-gray-300 rounded-md shadow-sm text-sm"><option value="">All</option><option value="none" @selected($filters['status'] === 'none')>Not sent</option><option value="accepted" @selected($filters['status'] === 'accepted')>Accepted</option><option value="failed" @selected($filters['status'] === 'failed')>Failed</option></select><button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Filter</button></form>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Document</th><th class="py-2 px-3 text-left font-medium text-gray-600">Date</th><th class="py-2 px-3 text-left font-medium text-gray-600">Customer</th><th class="py-2 px-3 text-right font-medium text-gray-600">Total</th><th class="py-2 px-3 text-left font-medium text-gray-600">FBR</th><th class="py-2 px-3 text-left font-medium text-gray-600">FBR invoice number</th><th></th></tr></thead>
            <tbody>
            @forelse ($documents as $row)
                <tr class="border-t align-top"><td class="py-2 px-3 font-medium">{{ $row['number'] }} <span class="text-xs font-normal text-gray-500">{{ str_replace('_', ' ', $row['kind']) }}</span></td><td class="py-2 px-3">{{ $row['issue_date'] }}</td><td class="py-2 px-3">{{ $row['customer'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($row['total']) }}</td>
                    <td class="py-2 px-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $row['status'] === 'failed' ? 'bg-red-100 text-red-700' : ($row['status'] === 'accepted' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700') }}">{{ $row['status'] === 'none' ? 'not sent' : $row['status'] }}</span>@if($row['error'])<p class="mt-1 max-w-xs text-xs text-red-700">{{ $row['error'] }}</p>@endif</td>
                    <td class="py-2 px-3 font-mono text-xs">{{ $row['fbr_invoice_number'] }}</td>
                    <td class="py-2 px-3 text-right">@if($enabled && $row['status'] !== 'accepted')@can('fbr.submit')<form method="POST" action="{{ route('accounting.fbr.submit', $row['document_id']) }}">@csrf<button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">{{ $row['status'] === 'failed' ? 'Retry' : 'Send' }}</button></form>@endcan @endif</td></tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-gray-500">No posted sales invoices yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
