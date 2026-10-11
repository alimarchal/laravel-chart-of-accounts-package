@props(['kit' => 'plain'])
@php
    // The screens are written once, with Tailwind utility classes. This sheet makes them look native in the shell they are shown in
    // (it only touches what is inside .acc-kit): the Livewire starter kit (Flux: zinc, flat bordered cards, light/dark by the .dark class
    // on <html>) and Jetstream / Breeze (the dark mode of the host). It does not need to be compiled by the host's Tailwind.
    $dark = [
        '.bg-white, .bg-gray-50, .bg-slate-50' => 'background-color:#18181b',
        '.bg-gray-100, .bg-gray-200, .hover\:bg-gray-50:hover, .hover\:bg-gray-100:hover, thead, thead th' => 'background-color:#27272a',
        '[class*="F2F1F5"]' => 'background-color:transparent',
        '.text-gray-900, .text-gray-800, .text-black' => 'color:#f4f4f5',
        '.text-gray-700, .text-gray-600' => 'color:#d4d4d8',
        '.text-gray-500, .text-gray-400' => 'color:#a1a1aa',
        '.text-indigo-700, .text-indigo-600, .text-blue-700, .text-blue-600' => 'color:#a5b4fc',
        '.border-gray-100, .border-gray-200, .border-gray-300, .divide-gray-200 > * + *, .divide-gray-100 > * + *' => 'border-color:#3f3f46',
        '.h-px.bg-gray-100' => 'background-color:#3f3f46',
        'input:not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]), select, textarea' => 'background-color:#27272a;color:#f4f4f5;border-color:#52525b',
        '.select2-container .select2-selection--single, .select2-dropdown' => 'background-color:#27272a;border-color:#52525b',
        '.select2-container .select2-selection__rendered, .select2-results__option' => 'color:#f4f4f5',
        'table, td, th' => 'border-color:#3f3f46',
    ];
    $flux = [
        // A sidebar layout already gives the page its width: use all of it instead of a centred 80rem column.
        '.max-w-7xl, .max-w-6xl, .max-w-5xl' => 'max-width:none',
        '.sm\:px-6, .lg\:px-8' => 'padding-left:1rem;padding-right:1rem',
        '.py-12, .py-8' => 'padding-top:1rem;padding-bottom:1rem',
        '.lg\:grid-cols-3' => 'grid-template-columns:repeat(auto-fit,minmax(22rem,1fr))',
        '.overflow-x-auto, .overflow-auto' => 'max-width:100%',
        '[class*="F2F1F5"]' => 'background-color:transparent',
        '.bg-white.shadow-xl, .bg-white.shadow-lg, .bg-white.shadow-md, .bg-white.shadow, .bg-white.shadow-sm' => 'box-shadow:none;border:1px solid #e4e4e7;border-radius:.75rem',
        '.rounded-2xl' => 'border-radius:.75rem',
        '.bg-blue-950, .bg-indigo-600, .bg-indigo-700' => 'background-color:#27272a;border-color:#27272a;color:#fff',
        '.bg-blue-950:hover, .hover\:bg-green-950:hover, .hover\:bg-indigo-700:hover, .hover\:bg-indigo-800:hover' => 'background-color:#3f3f46',
        'thead th' => 'font-size:.75rem;letter-spacing:.04em',
        'tr.bg-green-800, tr.bg-green-700, tr.bg-gray-800' => 'background-color:#f4f4f5;color:#52525b',
        // The Flux kit has no @tailwindcss/forms: give the plain fields a border, as Flux's own inputs have.
        'input:not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]):not([type=hidden]), select, textarea' => 'border:1px solid #d4d4d8;border-radius:.5rem;padding:.4rem .75rem;background-color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04)',
        'input:focus, select:focus, textarea:focus' => 'outline:2px solid #71717a;outline-offset:1px;border-color:#71717a;box-shadow:none',
    ];
    $fluxDark = [
        '.bg-white.shadow-xl, .bg-white.shadow-lg, .bg-white.shadow-md, .bg-white.shadow, .bg-white.shadow-sm' => 'border-color:#3f3f46',
        '.bg-blue-950, .bg-indigo-600, .bg-indigo-700' => 'background-color:#fff;border-color:#fff;color:#27272a',
        '.bg-blue-950:hover, .hover\:bg-green-950:hover, .hover\:bg-indigo-700:hover, .hover\:bg-indigo-800:hover' => 'background-color:#e4e4e7',
        '.bg-blue-950 svg, .bg-indigo-600 svg' => 'color:#27272a',
        'tr.bg-green-800, tr.bg-green-700, tr.bg-gray-800' => 'background-color:#27272a;color:#a1a1aa',
    ];
    $css = function (array $rules, string $scope, string $prefix = '') {
        $out = '';
        foreach ($rules as $selectors => $declarations) {
            $list = collect(explode(',', preg_replace('/\s+/', ' ', $selectors)))->map(fn ($selector) => $prefix.$scope.' '.trim($selector))->implode(',');
            $out .= $list.'{'.$declarations.'}';
        }

        return $out;
    };
    $sheet = '';
    if ($kit === 'flux') {
        $sheet .= $css($flux, '.acc-kit');
        $sheet .= $css(array_merge($dark, $fluxDark), '.acc-kit', '.dark ');
    } elseif ($kit === 'jetstream') {
        $sheet .= '@media (prefers-color-scheme: dark){'.$css($dark, '.acc-kit').'}';
    } elseif ($kit === 'breeze') {
        $sheet .= $css($dark, '.acc-kit', '.dark ');
    }
@endphp
@if($sheet !== '')
    <style>{!! $sheet !!}</style>
@endif
