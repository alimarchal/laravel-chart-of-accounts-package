@props([
    'headers' => [],
    'title' => null,
    'customWidth' => null,
])

<div>
    @if($title)
        <h3 style="margin:0 0 .5rem;font-size:.95rem;font-weight:650;text-align:center">{{ $title }}</h3>
    @endif

    <div class="acc-card">
        <div class="acc-table-wrap">
            <table class="acc-table {{ $customWidth }}">
                <thead>
                    <tr>
                        @foreach($headers as $header)
                            <th style="{{ str_contains($header['align'] ?? '', 'right') ? 'text-align:right' : (str_contains($header['align'] ?? '', 'center') ? 'text-align:center' : 'text-align:left') }}">{{ $header['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    {{ $slot }}
                </tbody>
                @if(isset($footer))
                    <tfoot>
                        {{ $footer }}
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
