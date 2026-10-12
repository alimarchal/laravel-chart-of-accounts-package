@props([
    'items' => [],
    'headers' => [],
    'emptyMessage' => 'No records found.',
    'emptyRoute' => null,
    'emptyLinkText' => 'Add a new record',
])

<div class="acc-wrap" style="margin-top:1rem;padding-bottom:4rem">
    <x-accounting::status-message />
    <div class="acc-card">
        @if ($items->count() > 0)
            <div class="acc-table-wrap">
                <table class="acc-table">
                    <thead>
                        <tr>
                            @foreach($headers as $header)
                                <th class="{{ $header['align'] ?? 'text-left' }}" style="{{ str_contains($header['align'] ?? '', 'right') ? 'text-align:right' : (str_contains($header['align'] ?? '', 'center') ? 'text-align:center' : 'text-align:left') }}">{!! $header['label'] !!}</th>
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
            @if (method_exists($items, 'hasPages') && $items->hasPages())
                <div class="acc-pager">
                    {{ $items->links() }}
                </div>
            @elseif (method_exists($items, 'total'))
                <div class="acc-pager acc-muted" style="font-size:.78rem">{{ trans_choice(':count record|:count records', $items->total()) }}</div>
            @endif
        @else
            <div class="acc-empty">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.63 10.5a2.25 2.25 0 0 1-2.24 2.12H6.62a2.25 2.25 0 0 1-2.24-2.12L3.75 7.5m6 4.13h4.5M3.38 7.5h17.24c.62 0 1.13-.5 1.13-1.13v-1.5c0-.62-.5-1.12-1.13-1.12H3.38c-.62 0-1.13.5-1.13 1.12v1.5c0 .62.5 1.13 1.13 1.13z"/></svg>
                <div>{{ $emptyMessage }}</div>
                @if($emptyRoute)
                    <div style="margin-top:.4rem"><a href="{{ $emptyRoute }}">{{ $emptyLinkText }} →</a></div>
                @endif
            </div>
        @endif
    </div>
</div>
