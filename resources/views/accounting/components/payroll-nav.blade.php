{{-- The payroll area as one navigation strip: runs, people, pay set-up, time and money owed, reports. Shown at the top of every payroll screen. --}}
@php
    $groups = [
        [['Runs', 'accounting.payroll.index', ['accounting.payroll.index', 'accounting.payroll.runs.*', 'accounting.payroll.payslips.*'], null, null]],
        [
            ['Employees', 'accounting.payroll.employees.index', ['accounting.payroll.employees.*'], null, null],
            ['Grades', 'accounting.payroll.grades.index', ['accounting.payroll.grades.*'], 'payroll_grades', null],
            ['Pay items', 'accounting.payroll.components.index', ['accounting.payroll.components.*'], null, null],
            ['Contributions', 'accounting.payroll.schemes.index', ['accounting.payroll.schemes.*'], 'payroll_contributions', null],
            ['Bulk changes', 'accounting.payroll.bulk', ['accounting.payroll.bulk*', 'accounting.payroll.revisions.*'], null, 'payroll.manage'],
        ],
        [
            ['Attendance', 'accounting.payroll.attendance.index', ['accounting.payroll.attendance.*'], 'payroll_attendance', null],
            ['Leave', 'accounting.payroll.leaves.index', ['accounting.payroll.leaves.*', 'accounting.payroll.leave-types.*'], 'payroll_attendance', null],
            ['Loans', 'accounting.payroll.loans.index', ['accounting.payroll.loans.*'], 'payroll_loans', null],
            ['Arrears', 'accounting.payroll.arrears.index', ['accounting.payroll.arrears.*'], 'payroll_arrears', null],
            ['Bonuses', 'accounting.payroll.adjustments.index', ['accounting.payroll.adjustments.*'], 'payroll_adjustments', null],
            ['Settlements', 'accounting.payroll.settlements.index', ['accounting.payroll.settlements.*'], 'payroll_settlements', null],
        ],
        [
            ['Reports', 'accounting.payroll.reports.index', ['accounting.payroll.reports.*'], 'payroll_reports', null],
            ['Tax', 'accounting.payroll.tax.index', ['accounting.payroll.tax.*'], 'payroll_reports', null],
        ],
    ];
    $features = app(\Alimarchal\LaravelChartOfAccounts\Support\FeatureManager::class);
@endphp
<nav class="acc-subnav acc-no-print" aria-label="{{ __('Payroll') }}">
    @foreach ($groups as $gi => $group)
        @php($links = collect($group)->filter(fn ($l) => (! $l[3] || $features->enabled($l[3])) && (! $l[4] || auth()->user()?->can($l[4])) && \Illuminate\Support\Facades\Route::has($l[1])))
        @continue($links->isEmpty())
        @if (! $loop->first && ($shown ?? false))<span class="acc-subnav-sep"></span>@endif
        @foreach ($links as $l)
            <a href="{{ route($l[1]) }}" @if (request()->routeIs(...$l[2])) aria-current="page" @endif>{{ __($l[0]) }}</a>
        @endforeach
        @php($shown = true)
    @endforeach
</nav>
