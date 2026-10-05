<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>@include('accounting::pdf.partials.styles')</head>
<body>
    <div class="footer">Generated {{ $generatedAt }}{{ $generatedBy ? ' by '.$generatedBy : '' }} · {{ $company['name'] }} · {{ $rowCount }} {{ \Illuminate\Support\Str::plural('row', $rowCount) }}</div>

    @include('accounting::pdf.partials.letterhead', ['title' => $title, 'subtitle' => $subtitle])

    @if ($filters !== [])
        <div class="meta">@foreach ($filters as $label => $value)<span><strong>{{ $label }}:</strong> {{ $value }}</span>@endforeach</div>
    @endif

    <table class="data{{ count($columns) > 11 ? ' compact' : '' }}">
        <thead><tr>@foreach ($columns as $column)<th class="{{ $column['numeric'] ? 'num' : '' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($columns as $column)<td class="{{ $column['numeric'] ? 'num' : '' }}">{{ $column['numeric'] && is_numeric($row[$column['key']] ?? null) ? number_format((float) $row[$column['key']], 2) : ($row[$column['key']] ?? '') }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">No rows for these filters.</td></tr>
            @endforelse
        </tbody>
        @if ($totals !== [])
            <tfoot><tr>@foreach ($columns as $index => $column)<td class="{{ $column['numeric'] ? 'num' : '' }}">{{ array_key_exists($column['key'], $totals) ? number_format($totals[$column['key']], 2) : ($index === 0 ? 'Total' : '') }}</td>@endforeach</tr></tfoot>
        @endif
    </table>
</body></html>
