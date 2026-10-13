{{-- The accounting menu in the hamburger menu of Jetstream and Breeze: one block, the sections open and close. --}}
@php($menu = \Alimarchal\LaravelChartOfAccounts\Support\AccountingMenu::for(auth()->user()))
@if ($menu !== [])
    @once
        <style>
            .acc-rmenu{padding:.25rem 1rem .5rem;border-top:1px solid rgba(107,114,128,.25)}
            .acc-rmenu>p{margin:.6rem 0 .2rem;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;opacity:.6}
            .acc-rmenu summary{cursor:pointer;padding:.5rem 0;font-size:.95rem;font-weight:600;list-style:none}
            .acc-rmenu summary::-webkit-details-marker{display:none}
            .acc-rmenu summary::after{content:"+";float:right;opacity:.6}
            .acc-rmenu details[open]>summary::after{content:"–"}
            .acc-rmenu a{display:block;padding:.35rem 0 .35rem .9rem;font-size:.9rem;text-decoration:none;color:inherit;opacity:.85}
            .acc-rmenu a[aria-current=page]{font-weight:700;opacity:1;color:#4f46e5}
        </style>
    @endonce
    {{-- accounting:menu --}}
    <div class="acc-rmenu">
        <p>{{ __('Accounting') }}</p>
        @php($settingsShown = false)
        @foreach ($menu as $section)
            @if ($section['area'] === 'settings' && ! $settingsShown)
                @php($settingsShown = true)
                <p>{{ __('Accounting settings') }}</p>
            @endif
            <details @if ($section['open']) open @endif>
                <summary>{{ __($section['title']) }}</summary>
                @foreach ($section['items'] as $item)
                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif>{{ __($item['title']) }}</a>
                @endforeach
            </details>
        @endforeach
    </div>
@endif
