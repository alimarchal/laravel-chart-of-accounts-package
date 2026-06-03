@props([
    'eventName',
    'title',
    'message' => '',
    'confirmButtonText' => 'Confirm',
    'confirmButtonClass' => 'bg-red-600 hover:bg-red-700',
    'cancelButtonText' => 'Cancel',
    'iconBgClass' => 'bg-red-100',
    'iconColorClass' => 'text-red-600',
    'iconPath' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    'formAction' => '',
    'formMethod' => 'POST',
    'csrfMethod' => null,
    'dynamicAction' => false,
])

<div x-data="{ show: false, actionUrl: '{{ $formAction }}', dynamicData: {}, isSubmitting: false }"
    x-on:{{ $eventName }}.window="show = true; isSubmitting = false; if ($event.detail) { dynamicData = $event.detail; if ($event.detail.url) { actionUrl = $event.detail.url; } }"
    x-on:keydown.escape.window="if (show) { show = false }"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50"
    style="display: none;">

    {{-- Backdrop --}}
    <div x-show="show"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 backdrop-blur-none" x-transition:enter-end="opacity-100 backdrop-blur-sm"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 backdrop-blur-sm" x-transition:leave-end="opacity-0 backdrop-blur-none"
         class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-all" @click="show = false">
    </div>

    {{-- Modal Panel --}}
    <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
        <div x-show="show"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
             @click.outside="show = false">

            {{-- Body --}}
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full {{ $iconBgClass }} sm:mx-0 sm:size-10">
                        <svg class="size-6 {{ $iconColorClass }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">{{ $title }}</h3>
                        <div class="mt-2">
                            @if ($message)
                                <p class="text-sm text-gray-600">{!! $message !!}</p>
                            @else
                                {{ $slot }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex flex-row justify-end gap-3 bg-gray-100 px-6 py-4">
                <button type="button" @click="show = false"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                    {{ $cancelButtonText }}
                </button>
                <form :action="actionUrl" method="{{ $formMethod }}"
                    @submit="if (isSubmitting) { $event.preventDefault(); return; } isSubmitting = true;">
                    @csrf
                    @if ($csrfMethod)
                        @method($csrfMethod)
                    @endif
                    <button type="submit" :disabled="isSubmitting"
                        class="inline-flex items-center rounded-md border border-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition {{ $confirmButtonClass }}">
                        <span x-show="!isSubmitting">{{ $confirmButtonText }}</span>
                        <span x-show="isSubmitting">Processing...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
