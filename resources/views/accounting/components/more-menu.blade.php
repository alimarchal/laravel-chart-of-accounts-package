@props(['label' => 'More'])
{{-- The less common actions of a screen in one "More" button, so the header shows the one or two things you do most and nothing else. --}}
<details class="acc-more" data-acc-more>
    <summary class="acc-btn">
        {{ __($label) }}
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
    </summary>
    <div class="acc-more-panel">{{ $slot }}</div>
</details>
@once
    @push('modals')
        <script>
            document.addEventListener('click', function (event) {
                document.querySelectorAll('details[data-acc-more][open]').forEach(function (menu) { if (! menu.contains(event.target)) { menu.removeAttribute('open'); } });
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { document.querySelectorAll('details[data-acc-more][open]').forEach(function (menu) { menu.removeAttribute('open'); }); }
            });
        </script>
    @endpush
@endonce
