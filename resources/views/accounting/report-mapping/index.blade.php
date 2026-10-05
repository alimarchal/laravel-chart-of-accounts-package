<x-accounting::app-layout title="Report Mapping">
    <x-slot name="header">
        <x-accounting::page-header title="Report Mapping" :showSearch="false" backRoute="accounting.dashboard" />
    </x-slot>

    @php
        $statementLines = collect($lines)->where('statement', $statement);
        $lineNames = collect($lines)->pluck('name', 'id');
        $byId = collect($accounts)->keyBy('id');
        $depth = function (array $account) use ($byId): int { $level = 0; $parent = $account['parent_id']; while ($parent && $level < 20) { $level++; $parent = $byId[$parent]['parent_id'] ?? null; } return $level; };
        $visible = collect($accounts)->where('statement', $statement)->filter(fn ($a) => ! $onlyUnmapped || ($a['resolved_line_id'] === null && ! $a['is_group']));
    @endphp

    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        @if ($unmapped > 0)<div class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">{{ $unmapped }} posting accounts are not mapped: they appear on an “unmapped” line of their section and as unclassified in the cash flow.</div>@endif

        <div class="flex flex-wrap items-center gap-2">
            @foreach (['balance_sheet' => 'Balance sheet', 'income_statement' => 'Income statement'] as $value => $label)
                <a href="{{ route('accounting.report-mapping.index', ['statement' => $value]) }}" @class(['px-3 py-1.5 rounded-md text-sm font-semibold border', 'bg-indigo-700 text-white border-indigo-700' => $statement === $value, 'bg-white text-gray-700 border-gray-300' => $statement !== $value])>{{ $label }}</a>
            @endforeach
            <a href="{{ route('accounting.report-mapping.index', ['statement' => $statement, 'unmapped' => $onlyUnmapped ? 0 : 1]) }}" class="text-sm text-indigo-700 hover:underline ml-2">{{ $onlyUnmapped ? 'Show all accounts' : 'Unmapped only' }}</a>
            <form method="POST" action="{{ route('accounting.report-mapping.recommended') }}" class="ml-auto">@csrf<button type="submit" class="px-3 py-1.5 rounded-md text-sm font-semibold border border-gray-300 bg-white hover:bg-gray-50">Apply recommended mapping</button></form>
            <a href="{{ route('accounting.reports.financial-statements') }}" class="px-3 py-1.5 rounded-md text-sm font-semibold border border-gray-300 bg-white hover:bg-gray-50">Financial statements</a>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <div class="bg-white shadow rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50"><tr>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Account</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 w-64">Line</th>
                        @if ($statement === 'balance_sheet')<th class="py-2 px-3 text-left font-medium text-gray-600 w-56">Cash flow</th>@endif
                    </tr></thead>
                    <tbody>
                        @foreach ($visible as $account)
                            <tr class="border-t">
                                <td class="py-1.5 px-3" style="padding-left: {{ 0.75 + $depth($account) }}rem"><span class="font-mono">{{ $account['account_code'] }}</span> <span @class(['font-semibold' => $account['is_group']])>{{ $account['account_name'] }}</span></td>
                                <td class="py-1.5 px-3" colspan="{{ $statement === 'balance_sheet' ? 2 : 1 }}">
                                    <form method="POST" action="{{ route('accounting.chart-of-accounts.report-mapping', $account['id']) }}" class="grid gap-2 {{ $statement === 'balance_sheet' ? 'grid-cols-2' : '' }}">
                                        @csrf @method('PUT')
                                        <select name="report_line_id" onchange="this.form.submit()" aria-label="Line of {{ $account['account_code'] }}" class="border-gray-300 rounded-md text-sm py-1">
                                            <option value="">{{ $account['inherited'] && $account['resolved_line_id'] ? '↳ '.($lineNames[$account['resolved_line_id']] ?? '') : '— not mapped —' }}</option>
                                            @foreach ($statementLines as $line)<option value="{{ $line['id'] }}" @selected($account['report_line_id'] === $line['id'])>{{ $line['name'] }}</option>@endforeach
                                        </select>
                                        @if ($statement === 'balance_sheet')
                                            <select name="cash_flow_category" onchange="this.form.submit()" aria-label="Cash flow of {{ $account['account_code'] }}" class="border-gray-300 rounded-md text-sm py-1">
                                                <option value="">{{ $account['resolved_cash_flow_category'] ? '↳ '.$cashFlowCategories[$account['resolved_cash_flow_category']] : '— unclassified —' }}</option>
                                                @foreach ($cashFlowCategories as $value => $label)<option value="{{ $value }}" @selected($account['cash_flow_category'] === $value)>{{ $label }}</option>@endforeach
                                            </select>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bg-white shadow rounded-lg p-4 space-y-4 h-fit">
                <h3 class="font-semibold text-gray-800">Lines</h3>
                @foreach ($sections[$statement] as $section => $label)
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">{{ $label }}</div>
                        <ul class="mt-1 space-y-1 text-sm">
                            @foreach ($statementLines->where('section', $section) as $line)
                                <li class="flex items-center justify-between gap-2">
                                    <span>{{ $line['name'] }}</span>
                                    <span class="flex items-center gap-1 text-xs">
                                        @if ($line['cash_flow_category'])<span class="rounded border px-1 text-gray-500">{{ $line['cash_flow_category'] }}</span>@endif
                                        <span class="rounded bg-gray-100 px-1.5">{{ $line['accounts_count'] }}</span>
                                        @unless ($line['is_system'])
                                            <form method="POST" action="{{ route('accounting.report-lines.destroy', $line['id']) }}" onsubmit="return confirm('Delete this line?')">@csrf @method('DELETE')<button type="submit" class="text-red-700 hover:underline">delete</button></form>
                                        @endunless
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <form method="POST" action="{{ route('accounting.report-lines.store') }}" class="space-y-2 border-t pt-3">
                    @csrf
                    <input type="hidden" name="statement" value="{{ $statement }}">
                    <div class="text-sm font-medium">Add a line</div>
                    <div class="grid grid-cols-2 gap-2">
                        <x-accounting::input name="code" placeholder="Code" required />
                        <select name="section" required class="border-gray-300 rounded-md text-sm"><option value="">Section</option>@foreach ($sections[$statement] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </div>
                    <x-accounting::input name="name" placeholder="Name" class="w-full" required />
                    @if ($statement === 'balance_sheet')
                        <select name="cash_flow_category" class="w-full border-gray-300 rounded-md text-sm"><option value="">Unclassified</option>@foreach ($cashFlowCategories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    @endif
                    <button type="submit" class="px-3 py-1.5 rounded-md text-xs font-semibold uppercase tracking-widest bg-indigo-700 text-white hover:bg-indigo-600">Add line</button>
                </form>
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
