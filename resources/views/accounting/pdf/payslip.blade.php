<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Payslip {{ $employee->code }} {{ $month }}</title>@include('accounting::pdf.partials.styles')
<style>
    .grid { margin: 8px 0 12px; }
    .grid td { padding: 3px 6px 3px 0; vertical-align: top; width: 25%; }
    .grid .label { color: #6b7280; font-size: 8px; text-transform: uppercase; }
    .grid .value { font-size: 10px; font-weight: bold; }
    .cols td { vertical-align: top; width: 50%; padding-right: 12px; }
    .net { margin: 12px 0; padding: 8px 10px; border: 1px solid #c7d2fe; background: #eef2ff; font-size: 12px; font-weight: bold; }
    @media print { .screen-only { display: none; } body { margin: 0; } }
    .screen-only { margin: 12px 0; text-align: right; }
    .screen-only button { font-size: 12px; padding: 6px 14px; background: #1e3a8a; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
</style>
</head>
<body @if ($forScreen) style="max-width: 900px; margin: 20px auto; font-size: 11px;" @endif>
    @if ($forScreen)<div class="screen-only"><button type="button" onclick="window.print()">Print</button></div>@endif
    @include('accounting::pdf.partials.letterhead', ['title' => $title, 'subtitle' => $subtitle])
    <table class="grid">
        <tr>
            <td><div class="label">Employee</div><div class="value">{{ $employee->name }} ({{ $employee->code }})</div></td>
            <td><div class="label">Designation</div><div class="value">{{ $employee->designation ?: '—' }}</div></td>
            <td><div class="label">National ID</div><div class="value">{{ $employee->national_id ?: '—' }}</div></td>
            <td><div class="label">Days paid</div><div class="value">{{ (float) $slip->days_paid }} of {{ (float) $slip->days_in_month }}</div></td>
        </tr>
        <tr>
            <td colspan="2"><div class="label">Bank</div><div class="value">{{ $employee->bank_name ? $employee->bank_name.' '.$employee->bank_account : '—' }}</div></td>
            <td colspan="2"><div class="label">Month</div><div class="value">{{ $month }}</div></td>
        </tr>
    </table>
    <table class="cols"><tr>
        <td><table class="data"><thead><tr><th>Earnings</th><th class="num">Amount</th></tr></thead><tbody>
            @foreach ($earnings as $line)<tr><td>{{ $line->description }}</td><td class="num">{{ number_format((float) $line->amount, 2) }}</td></tr>@endforeach
        </tbody><tfoot><tr><td>Gross</td><td class="num">{{ number_format((float) $slip->gross, 2) }}</td></tr></tfoot></table></td>
        <td><table class="data"><thead><tr><th>Deductions</th><th class="num">Amount</th></tr></thead><tbody>
            @foreach ($deductions as $line)<tr><td>{{ $line->description }}</td><td class="num">{{ number_format((float) $line->amount, 2) }}</td></tr>@endforeach
        </tbody><tfoot><tr><td>Total</td><td class="num">{{ number_format((float) $slip->deductions + (float) $slip->tax, 2) }}</td></tr></tfoot></table></td>
    </tr></table>
    @if ($employerLines->isNotEmpty())
        <p style="margin-top: 8px"><strong>Employer contributions</strong> (paid by the company, not deducted from pay):
            {{ $employerLines->map(fn ($line) => $line->description.' '.number_format((float) $line->amount, 2))->implode(' · ') }}</p>
    @endif
    <div class="net">Net pay: {{ number_format((float) $slip->net, 2) }}</div>
    <div class="words"><strong>In words:</strong> {{ $amountInWords }}</div>
    <div class="footer">Printed {{ $printedAt }} · {{ $company['name'] }} · This is a computer-generated payslip.</div>
</body></html>
