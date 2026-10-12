{{-- The context of the page in one row: language (when more than one is configured), the current company (multi-company only) and the working branch (branches only). --}}
@php
    $prefix = config('accounting.route_name_prefix', 'accounting');
    $languages = \Alimarchal\LaravelChartOfAccounts\Http\Middleware\SetAccountingLocale::locales();
    $showLanguage = count($languages) > 1 && auth()->check() && \Illuminate\Support\Facades\Route::has($prefix.'.locale');

    $companyContext = app(\Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany::class);
    $showCompany = \Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany::enabled() && auth()->check();
    if ($showCompany) {
        $currentCompany = $companyContext->get();
        $accessibleCompanies = $companyContext->accessibleBy(auth()->user());
    }

    $showBranch = \Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch::enabled() && auth()->check() && \Illuminate\Support\Facades\Route::has($prefix.'.branch.switch');
    if ($showBranch) {
        $branchOptions = \Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch::options();
        $workingBranch = \Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch::selectedId();
        $showBranch = count($branchOptions) > 0;
    }
@endphp
@if($showLanguage || $showCompany || $showBranch)
    <div class="acc-wrap">
        <div class="acc-context" data-acc-context>
            @if($showCompany)
                <span>{{ __('Company') }}:</span>
                @if($accessibleCompanies->count() > 1)
                    <form method="POST" action="{{ route($prefix.'.company.switch') }}" style="min-width:12rem">
                        @csrf
                        <select name="company_id" aria-label="{{ __('Company') }}" onchange="this.form.submit()">
                            @foreach($accessibleCompanies as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $currentCompany->id)>{{ $option->name }} ({{ $option->code }})</option>
                            @endforeach
                        </select>
                    </form>
                @else
                    <b>{{ $currentCompany->name }}</b>
                @endif
            @endif

            @if($showBranch)
                <span>{{ __('Branch') }}:</span>
                <form method="POST" action="{{ route($prefix.'.branch.switch') }}" style="min-width:12rem">
                    @csrf
                    <select id="acc-branch" name="branch_id" aria-label="{{ __('Branch') }}" onchange="this.form.submit()">
                        <option value="">{{ \Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch::restricted() ? __('All my branches') : __('All branches') }}</option>
                        @foreach($branchOptions as $option)
                            <option value="{{ $option['id'] }}" @selected($workingBranch === $option['id'])>{{ $option['name'] }} ({{ $option['code'] }})</option>
                        @endforeach
                    </select>
                </form>
            @endif

            @if($showLanguage)
                <form method="POST" action="{{ route($prefix.'.locale') }}" style="min-width:7rem">
                    @csrf
                    <select name="locale" aria-label="{{ __('Language') }}" onchange="this.form.submit()">
                        @foreach($languages as $code => $name)
                            <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </div>
@endif
