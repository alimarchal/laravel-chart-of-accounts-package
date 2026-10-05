@props(['report'])
{{-- CSV / Excel / PDF of the report with the current filters. Large Excel/PDF exports go to "My exports". --}}
<div class="flex flex-wrap items-center justify-end gap-2 text-xs">
    <span class="text-gray-500">Export:</span>
    @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
        <a href="{{ route('accounting.reports.export', ['report' => $report, 'format' => $format] + request()->query()) }}"
           class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1 font-semibold text-gray-700 hover:bg-gray-50">{{ $label }}</a>
    @endforeach
    <a href="{{ route('accounting.exports.index') }}" class="text-indigo-700 hover:underline">My exports</a>
</div>
