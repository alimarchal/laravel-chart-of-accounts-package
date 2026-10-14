{{-- The payroll area as five tabs; the choices inside a tab (employees, grades, loans …) show only once you are in it, so nobody faces fourteen
     links at once. Shown at the top of every payroll screen. --}}
@php
    $groups = [
        ['Runs', [['Runs', 'accounting.payroll.index', ['accounting.payroll.index', 'accounting.payroll.runs.*', 'accounting.payroll.payslips.*'], null, null]]],
        ['People & pay', [
            ['Employees', 'accounting.payroll.employees.index', ['accounting.payroll.employees.*'], null, null],
            ['Grades', 'accounting.payroll.grades.index', ['accounting.payroll.grades.*'], 'payroll_grades', null],
            ['Pay items', 'accounting.payroll.components.index', ['accounting.payroll.components.*'], null, null],
            ['Contributions', 'accounting.payroll.schemes.index', ['accounting.payroll.schemes.*'], 'payroll_contributions', null],
            ['Bulk changes', 'accounting.payroll.bulk', ['accounting.payroll.bulk*', 'accounting.payroll.revisions.*'], null, 'payroll.manage'],
        ]],
        ['Attendance & leave', [
            ['Attendance', 'accounting.payroll.attendance.index', ['accounting.payroll.attendance.*'], 'payroll_attendance', null],
            ['Leave', 'accounting.payroll.leaves.index', ['accounting.payroll.leaves.*', 'accounting.payroll.leave-types.*'], 'payroll_attendance', null],
        ]],
        ['Loans & extras', [
            ['Loans', 'accounting.payroll.loans.index', ['accounting.payroll.loans.*'], 'payroll_loans', null],
            ['Arrears', 'accounting.payroll.arrears.index', ['accounting.payroll.arrears.*'], 'payroll_arrears', null],
            ['Bonuses', 'accounting.payroll.adjustments.index', ['accounting.payroll.adjustments.*'], 'payroll_adjustments', null],
            ['Settlements', 'accounting.payroll.settlements.index', ['accounting.payroll.settlements.*'], 'payroll_settlements', null],
        ]],
        ['Reports & tax', [
            ['Reports', 'accounting.payroll.reports.index', ['accounting.payroll.reports.*'], 'payroll_reports', null],
            ['Tax', 'accounting.payroll.tax.index', ['accounting.payroll.tax.*'], 'payroll_reports', null],
        ]],
    ];
    $features = app(\Alimarchal\LaravelChartOfAccounts\Support\FeatureManager::class);
    $visible = collect($groups)->map(fn ($g) => [
        'title' => $g[0],
        'links' => collect($g[1])->filter(fn ($l) => (! $l[3] || $features->enabled($l[3])) && (! $l[4] || auth()->user()?->can($l[4])) && \Illuminate\Support\Facades\Route::has($l[1]))->map(fn ($l) => ['title' => $l[0], 'url' => route($l[1]), 'active' => request()->routeIs(...$l[2])])->values(),
    ])->filter(fn ($g) => $g['links']->isNotEmpty())->values();
    $current = $visible->first(fn ($g) => $g['links']->contains('active', true));
@endphp
<nav class="acc-subnav acc-no-print" aria-label="{{ __('Payroll') }}">
    @foreach ($visible as $group)
        <a href="{{ $group['links'][0]['url'] }}" @if ($current && $current['title'] === $group['title']) aria-current="page" @endif>{{ __($group['title']) }}</a>
    @endforeach
</nav>
@if ($current && $current['links']->count() > 1)
    <nav class="acc-subnav2 acc-no-print" aria-label="{{ __($current['title']) }}">
        @foreach ($current['links'] as $link)
            <a href="{{ $link['url'] }}" @if ($link['active']) aria-current="page" @endif>{{ __($link['title']) }}</a>
        @endforeach
    </nav>
@endif
