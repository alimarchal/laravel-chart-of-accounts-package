{{-- Company letterhead shared by PDF reports and printed vouchers. --}}
<table class="letterhead">
    <tr>
        <td class="logo">
            @if ($company['logo'])<img src="{{ $company['logo'] }}" alt="">@endif
        </td>
        <td class="company">
            <div class="company-name">{{ $company['legal_name'] ?: $company['name'] }}</div>
            @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
            <div>
                @if ($company['phone']){{ $company['phone'] }}@endif
                @if ($company['phone'] && $company['email']) · @endif
                @if ($company['email']){{ $company['email'] }}@endif
            </div>
            <div>
                @if ($company['tax_number'])NTN / Tax no. {{ $company['tax_number'] }}@endif
                @if ($company['tax_number'] && $company['registration_number']) · @endif
                @if ($company['registration_number'])Reg. no. {{ $company['registration_number'] }}@endif
            </div>
        </td>
        <td class="doc-title">
            <div class="title">{{ $title }}</div>
            @isset($subtitle)<div class="subtitle">{{ $subtitle }}</div>@endisset
        </td>
    </tr>
</table>
