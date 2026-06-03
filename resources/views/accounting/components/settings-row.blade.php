@props([
    'href',
    'label',
    'description' => '',
    'count' => null,
    'iconBg' => 'bg-gray-500',
])

<a href="{{ $href }}"
    class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 active:bg-[#D1D0D4] transition-colors duration-150 group">
    <div class="flex-shrink-0 w-[30px] h-[30px] rounded-[7px] {{ $iconBg }} flex items-center justify-center shadow-sm">
        {{ $icon }}
    </div>
    <div class="flex-1 min-w-0">
        <div class="text-[15px] font-normal text-gray-900 leading-snug">{{ $label }}</div>
        @if ($description)
            <div class="text-[11px] text-gray-400 truncate leading-tight mt-0.5">{{ $description }}</div>
        @endif
    </div>
    <div class="flex items-center gap-1.5 flex-shrink-0">
        @if (!is_null($count))
            <span class="text-[15px] text-gray-400 font-normal tabular-nums">{{ $count }}</span>
        @endif
        <svg class="w-[7px] h-[12px] text-gray-300 group-hover:text-gray-400 transition-colors" fill="none"
            stroke="currentColor" viewBox="0 0 7 12" stroke-width="2.2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M1 1l5 5-5 5" />
        </svg>
    </div>
</a>
