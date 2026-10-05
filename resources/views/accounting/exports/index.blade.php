<x-accounting::app-layout title="My exports">
    <x-slot name="header">
        <x-accounting::page-header title="My exports" :showSearch="false" backRoute="accounting.dashboard" />
    </x-slot>

    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <p class="text-sm text-gray-600">Large Excel and PDF reports are prepared in the background and kept here for a few days. <a href="{{ route('accounting.exports.index') }}" class="text-indigo-700 hover:underline">Refresh</a></p>

        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Report</th>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Filters</th>
                    <th class="py-2 px-3 text-left font-medium text-gray-600">Status</th>
                    <th class="py-2 px-3 text-right font-medium text-gray-600">Rows</th>
                    <th class="py-2 px-3 text-right font-medium text-gray-600">Actions</th>
                </tr></thead>
                <tbody>
                    @forelse ($exports as $export)
                        <tr class="border-t">
                            <td class="py-2 px-3"><span class="font-semibold">{{ $export['title'] }}</span> <span class="text-xs uppercase text-gray-500">{{ $export['format'] }}</span><div class="text-xs text-gray-500">{{ $export['created_at'] ? \Illuminate\Support\Carbon::parse($export['created_at'])->format('Y-m-d H:i') : '' }}</div></td>
                            <td class="py-2 px-3 text-xs text-gray-500">{{ collect($export['filters'])->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ') ?: '—' }}</td>
                            <td class="py-2 px-3"><span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-gray-100 text-gray-600' => $export['status'] === 'queued', 'bg-amber-100 text-amber-800' => $export['status'] === 'running', 'bg-emerald-100 text-emerald-800' => $export['status'] === 'ready', 'bg-red-100 text-red-700' => $export['status'] === 'failed'])>{{ $export['status'] }}</span>@if ($export['error'])<div class="text-xs text-red-600">{{ $export['error'] }}</div>@endif</td>
                            <td class="py-2 px-3 text-right">{{ $export['rows'] ?? '—' }}</td>
                            <td class="py-2 px-3 text-right whitespace-nowrap">
                                @if ($export['status'] === 'ready')<a href="{{ route('accounting.exports.download', $export['id']) }}" class="font-semibold text-indigo-700 hover:underline">Download</a>@endif
                                <form method="POST" action="{{ route('accounting.exports.destroy', $export['id']) }}" class="inline">@csrf @method('DELETE')<button type="submit" class="ml-2 text-red-700 hover:underline">Delete</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">No exports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div></div>
</x-accounting::app-layout>
