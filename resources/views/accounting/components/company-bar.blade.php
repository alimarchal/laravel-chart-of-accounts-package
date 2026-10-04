{{-- Current company and switcher (multi-company only). --}}
@php
    $companyContext = app(\Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany::class);
@endphp
@if(\Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany::enabled() && auth()->check())
    @php
        $currentCompany = $companyContext->get();
        $accessibleCompanies = $companyContext->accessibleBy(auth()->user());
    @endphp
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        <div class="flex items-center justify-end gap-2 text-sm text-gray-700">
            <span class="text-gray-500">Company:</span>
            @if($accessibleCompanies->count() > 1)
                <form method="POST" action="{{ route(config('accounting.route_name_prefix', 'accounting').'.company.switch') }}">
                    @csrf
                    <select name="company_id" aria-label="Company" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                        @foreach($accessibleCompanies as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $currentCompany->id)>{{ $option->name }} ({{ $option->code }})</option>
                        @endforeach
                    </select>
                </form>
            @else
                <span class="font-semibold">{{ $currentCompany->name }}</span>
            @endif
        </div>
    </div>
@endif
