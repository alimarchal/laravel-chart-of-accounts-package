@props(['kit' => 'jetstream'])
{{-- The accounting menu as one "Accounting" dropdown in a top navigation (Jetstream, Breeze, the Flux header): sections side by side, the settings after them. --}}
@php($menu = \Alimarchal\LaravelChartOfAccounts\Support\AccountingMenu::for(auth()->user()))
@if ($menu !== [])
    @php($areas = collect($menu)->groupBy('area'))
    @php($current = collect($menu)->contains(fn ($section) => collect($section['items'])->contains('active', true)))
    @once
        <style>
            .acc-mega{--m-text:#111827;--m-muted:#6b7280;--m-bg:#fff;--m-border:#e5e7eb;--m-hover:#f3f4f6;--m-accent:#4f46e5;position:relative;display:inline-flex;align-items:center;height:100%}
            :is(.dark) .acc-mega{--m-text:#f4f4f5;--m-muted:#a1a1aa;--m-bg:#18181b;--m-border:#3f3f46;--m-hover:#27272a;--m-accent:#a5b4fc}
            @media (prefers-color-scheme: dark){:root:not(.light) .acc-mega.acc-mega--auto{--m-text:#f4f4f5;--m-muted:#a1a1aa;--m-bg:#18181b;--m-border:#3f3f46;--m-hover:#27272a;--m-accent:#a5b4fc}}
            .acc-mega-trigger{display:inline-flex;align-items:center;gap:.35rem;height:100%;padding:0 .25rem;border:0;border-bottom:2px solid transparent;background:transparent;color:var(--m-muted);font-size:.875rem;font-weight:500;line-height:1.25rem;font-family:inherit;cursor:pointer}
            .acc-mega-trigger:hover{color:var(--m-text)}
            .acc-mega-trigger[aria-current=true]{color:var(--m-text);border-bottom-color:var(--m-accent)}
            .acc-mega--flux .acc-mega-trigger{height:2.5rem;padding:0 .75rem;border:0;border-radius:.5rem;gap:.5rem}
            .acc-mega--flux .acc-mega-trigger:hover{background:var(--m-hover)}
            .acc-mega--flux .acc-mega-trigger[aria-current=true]{background:var(--m-hover)}
            .acc-mega-trigger svg{width:.9rem;height:.9rem;transition:transform .15s}
            .acc-mega-trigger[aria-expanded=true] svg{transform:rotate(180deg)}
            .acc-mega-panel{position:absolute;top:calc(100% + .4rem);left:0;z-index:60;width:min(58rem,92vw);padding:1rem;background:var(--m-bg);border:1px solid var(--m-border);border-radius:.8rem;box-shadow:0 18px 50px rgba(15,23,42,.18);display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.25rem 1.25rem}
            .acc-mega-col h4{margin:.5rem 0 .25rem;padding:0 .5rem;font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--m-muted)}
            .acc-mega-col a{display:block;padding:.3rem .5rem;border-radius:.4rem;font-size:.84rem;color:var(--m-text);text-decoration:none}
            .acc-mega-col a:hover{background:var(--m-hover)}
            .acc-mega-col a[aria-current=page]{background:var(--m-hover);color:var(--m-accent);font-weight:650}
            .acc-mega-settings{grid-column:1/-1;margin-top:.5rem;padding-top:.7rem;border-top:1px solid var(--m-border)}
            .acc-mega-settings>p{margin:0 0 .1rem;padding:0 .5rem;font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--m-accent)}
            .acc-mega-settings-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.25rem 1.25rem}
            .acc-mega-col--wide{grid-column:span 3}
            .acc-mega-col--wide .acc-mega-list{columns:3;column-gap:1.25rem}
            .acc-mega-col--wide .acc-mega-list a{break-inside:avoid}
            .acc-mega-foot{grid-column:1/-1;display:flex;justify-content:space-between;gap:1rem;margin-top:.5rem;padding-top:.6rem;border-top:1px solid var(--m-border);font-size:.8rem}
            .acc-mega-foot a{color:var(--m-accent);font-weight:600;text-decoration:none}
            @media (max-width:900px){.acc-mega-panel,.acc-mega-settings-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.acc-mega-col--wide{grid-column:1/-1}.acc-mega-col--wide .acc-mega-list{columns:2}}
        </style>
    @endonce
    <div class="acc-mega acc-mega--{{ $kit }} {{ $kit === 'jetstream' ? 'acc-mega--auto' : '' }}" x-data="{ open: false, place() { const p = this.$refs.panel; p.style.left = '0px'; const over = p.getBoundingClientRect().right - (window.innerWidth - 16); if (over > 0) { p.style.left = (-over) + 'px'; } const left = p.getBoundingClientRect().left; if (left < 16) { p.style.left = (parseFloat(p.style.left) + 16 - left) + 'px'; } } }" @keydown.escape.window="open = false" @click.outside="open = false">
        {{-- accounting:menu --}}
        <button type="button" class="acc-mega-trigger" @click="open = ! open; open && $nextTick(() => place())" :aria-expanded="open" aria-haspopup="true" @if ($current) aria-current="true" @endif>
            {{ __('Accounting') }}
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="acc-mega-panel" x-ref="panel" x-show="open" x-cloak x-transition.opacity.duration.100ms style="display:none">
            @foreach ($menu as $section)
                @continue($section['area'] !== 'accounting')
                <div class="acc-mega-col">
                    <h4>{{ __($section['title']) }}</h4>
                    @foreach ($section['items'] as $item)
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif>{{ __($item['title']) }}</a>
                    @endforeach
                </div>
            @endforeach
            @php($settings = collect($menu)->where('area', 'settings'))
            @if ($settings->isNotEmpty())
                <div class="acc-mega-settings">
                    <p>{{ __('Accounting settings') }}</p>
                    <div class="acc-mega-settings-grid">
                        @foreach ($settings as $section)
                            <div class="acc-mega-col {{ count($section['items']) > 5 ? 'acc-mega-col--wide' : '' }}">
                                <h4>{{ __($section['title']) }}</h4>
                                <div class="acc-mega-list">
                                    @foreach ($section['items'] as $item)
                                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif>{{ __($item['title']) }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @php($help = collect($menu)->firstWhere('area', 'help'))
            @if ($help)
                <div class="acc-mega-foot">
                    <span style="opacity:.7">{{ __('Every screen has a ? button with a short guide.') }}</span>
                    <a href="{{ $help['items'][0]['url'] }}">{{ __('Open the help centre') }} →</a>
                </div>
            @endif
        </div>
    </div>
@endif
