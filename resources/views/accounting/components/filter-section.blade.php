@props([
    'action' => '#',
    'method' => 'GET',
    'maxWidth' => 'max-w-7xl',
])
@php
    $applied = collect(request()->input('filter', []))->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])->map(fn ($value) => is_array($value) ? implode(', ', $value) : $value);
@endphp
<div class="acc-wrap" style="margin-top:.75rem">
    @if($applied->isNotEmpty())
        <div class="acc-chips" style="margin-bottom:.6rem">
            <span class="acc-muted" style="font-size:.78rem">{{ __('Filtered by') }}</span>
            @foreach($applied as $name => $value)
                <span class="acc-chip">{{ \Illuminate\Support\Str::headline(str_replace(['entry_date_from', 'entry_date_to'], ['from', 'to'], $name)) }}<b>{{ \Illuminate\Support\Str::limit((string) $value, 28) }}</b></span>
            @endforeach
            <a href="{{ request()->url() }}" class="acc-btn acc-btn--ghost acc-btn--sm">{{ __('Clear all') }}</a>
        </div>
    @endif
    <div class="acc-card acc-filters {{ $applied->isNotEmpty() ? 'is-open' : '' }}" id="filters" data-open="{{ $applied->isNotEmpty() ? '1' : '0' }}">
        <div class="acc-card-b">
            <form method="{{ $method }}" action="{{ $action }}">
                @if($method !== 'GET')
                    @csrf
                    @method($method)
                @endif

                {{ $slot }}

                <x-accounting::submit-button />
            </form>
        </div>
    </div>
</div>

@push('modals')
    <script>
        (function () {
            const panel = document.getElementById('filters');
            if (!panel) return;
            let btn = document.getElementById('toggle');

            // A page whose header has no filter button (chart of accounts, snapshots …) gets one above the filters.
            if (!btn) {
                btn = document.createElement('button');
                btn.id = 'toggle';
                btn.type = 'button';
                btn.className = 'acc-btn';
                btn.textContent = @js(__('Filters'));
                const bar = document.createElement('div');
                bar.className = 'acc-wrap';
                bar.style.cssText = 'display:flex;justify-content:flex-end;margin-top:.75rem';
                bar.appendChild(btn);
                panel.closest('.acc-wrap').parentElement.insertBefore(bar, panel.closest('.acc-wrap'));
            }

            const set = (open) => {
                panel.classList.toggle('is-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) { const first = panel.querySelector('input:not([type=hidden]), select'); if (first && !panel.dataset.focused) { panel.dataset.focused = '1'; first.focus({ preventScroll: true }); } }
            };
            btn.setAttribute('aria-expanded', panel.classList.contains('is-open') ? 'true' : 'false');
            btn.addEventListener('click', (event) => { event.preventDefault(); set(!panel.classList.contains('is-open')); });
            // Esc closes the panel; clicking elsewhere does not (a dropdown opened inside it lives outside the panel).
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && panel.classList.contains('is-open') && !document.querySelector('.select2-container--open')) set(false); });
        })();
    </script>
@endpush
