@props(['text'])

<div class="relative group inline-block">
    {{ $slot }}
    <div
        class="invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-opacity duration-300 absolute z-50 inline-block px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg shadow-sm -translate-x-1/2 left-1/2 bottom-full mb-2 dark:bg-gray-700 whitespace-nowrap min-w-[max-content]">
        {{ $text }}
        <div
            class="absolute w-2 h-2 bg-gray-900 dark:bg-gray-700 transform rotate-45 -bottom-1 left-1/2 -translate-x-1/2">
        </div>
    </div>
</div>
