<x-accounting::app-layout title="Import Chart of Accounts">
    <x-slot name="header">
        <x-accounting::page-header title="Import Chart of Accounts" :showSearch="false" backRoute="accounting.chart-of-accounts.index" />
    </x-slot>

    @php($styles = ['create' => 'bg-emerald-100 text-emerald-800', 'update' => 'bg-blue-100 text-blue-800', 'unchanged' => 'bg-gray-100 text-gray-600', 'error' => 'bg-red-100 text-red-700'])

    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />

        <div class="bg-white shadow rounded-lg p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-gray-800">1. Choose a CSV or Excel file</h3>
                <div class="flex gap-3 text-sm">
                    <a href="{{ route('accounting.chart-of-accounts.import.template', 'xlsx') }}" class="text-indigo-700 hover:underline">Template (Excel)</a>
                    <a href="{{ route('accounting.chart-of-accounts.import.template', 'csv') }}" class="text-indigo-700 hover:underline">Template (CSV)</a>
                    <a href="{{ route('accounting.chart-of-accounts.export', 'xlsx') }}" class="text-indigo-700 hover:underline">Current chart</a>
                </div>
            </div>
            <form method="POST" action="{{ route('accounting.chart-of-accounts.import.preview') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-[1fr_220px_auto] md:items-end">
                @csrf
                <div>
                    <x-accounting::label for="file" value="File" />
                    <input id="file" name="file" type="file" accept=".csv,.txt,.xlsx" required class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2" />
                </div>
                <div>
                    <x-accounting::label for="mode" value="Existing account codes" />
                    <select id="mode" name="mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="upsert" @selected(($preview['mode'] ?? 'upsert') === 'upsert')>Update them</option>
                        <option value="create" @selected(($preview['mode'] ?? null) === 'create')>Leave them unchanged</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-indigo-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600">Preview</button>
            </form>
            <p class="text-sm text-gray-600">Columns: <code class="text-xs">{{ implode(', ', $columns) }}</code>. Only <code class="text-xs">account_code</code> is always required; parents may appear anywhere in the file. Blank cells keep an existing account's value; a new account takes its parent's type and currency.</p>
        </div>

        @if ($preview)
            @php($toImport = $preview['summary']['create'] + $preview['summary']['update'])
            <div class="bg-white shadow rounded-lg p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold text-gray-800">2. Check the changes in {{ $preview['filename'] }}</h3>
                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <span class="rounded-full px-2 py-0.5 {{ $styles['create'] }}">{{ $preview['summary']['create'] }} new</span>
                        <span class="rounded-full px-2 py-0.5 {{ $styles['update'] }}">{{ $preview['summary']['update'] }} updated</span>
                        <span class="rounded-full px-2 py-0.5 {{ $styles['unchanged'] }}">{{ $preview['summary']['unchanged'] }} unchanged</span>
                        <span class="rounded-full px-2 py-0.5 {{ $styles['error'] }}">{{ $preview['summary']['error'] }} errors</span>
                    </div>
                </div>

                @if ($preview['summary']['error'] > 0)
                    <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">Fix the rows in error and upload the file again. Nothing is imported while any row has an error.</div>
                @elseif ($toImport === 0)
                    <div class="rounded-md bg-gray-50 border border-gray-200 p-3 text-sm text-gray-700">Nothing to import: every account in the file is already up to date.</div>
                @endif

                <div class="overflow-x-auto border rounded-md">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50"><tr>
                            <th class="py-2 px-3 text-left font-medium text-gray-600">Line</th>
                            <th class="py-2 px-3 text-left font-medium text-gray-600">Account</th>
                            <th class="py-2 px-3 text-left font-medium text-gray-600">Result</th>
                            <th class="py-2 px-3 text-left font-medium text-gray-600">Details</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($preview['rows'] as $row)
                                <tr class="border-t align-top">
                                    <td class="py-2 px-3 text-gray-500">{{ $row['line'] }}</td>
                                    <td class="py-2 px-3"><span class="font-mono">{{ $row['account_code'] ?: '—' }}</span> {{ $row['account_name'] }}</td>
                                    <td class="py-2 px-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $styles[$row['action']] }}">{{ $row['action'] }}</span></td>
                                    <td class="py-2 px-3">
                                        @if ($row['errors'])
                                            <ul class="text-red-700 space-y-1">@foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                                        @elseif ($row['action'] === 'update')
                                            <ul class="space-y-1">@foreach ($row['changes'] as $field => [$from, $to])<li><span class="text-gray-500">{{ $field }}:</span> <span class="line-through opacity-60">{{ $from ?? '—' }}</span> → {{ $to ?? '—' }}</li>@endforeach</ul>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('accounting.chart-of-accounts.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">Cancel</a>
                    @if ($preview['token'] && $toImport > 0)
                        <form method="POST" action="{{ route('accounting.chart-of-accounts.import.store') }}">
                            @csrf
                            <input type="hidden" name="token" value="{{ $preview['token'] }}">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">Import {{ $toImport }} {{ \Illuminate\Support\Str::plural('account', $toImport) }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div></div>
</x-accounting::app-layout>
