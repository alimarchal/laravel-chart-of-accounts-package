@php
    $icons = [
        'ok' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        'bad' => 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9 3.75h.01',
        'warn' => 'M12 9v3.75m-9.3 3.4c-.87 1.5.2 3.35 1.94 3.35h14.72c1.73 0 2.8-1.85 1.94-3.35L13.94 4.5c-.87-1.5-3.01-1.5-3.88 0L2.7 16.15zM12 15.75h.01',
        'info' => 'M11.25 11.25l.04-.02a.75.75 0 0 1 1.06.85l-.7 2.8a.75.75 0 0 0 1.06.85l.04-.02M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9-3.75h.01',
    ];
@endphp
@foreach ([['success', 'ok', 'Success'], ['error', 'bad', 'Error'], ['warning', 'warn', 'Warning'], ['info', 'info', 'Information']] as [$key, $tone, $heading])
    @if (session($key))
        <div {{ $attributes->merge(['class' => 'acc-alert acc-alert--'.$tone]) }} style="margin-bottom:.75rem" data-status-sound="{{ $key }}" role="{{ $key === 'error' ? 'alert' : 'status' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$tone] }}"/></svg>
            <div>
                <strong>{{ __($heading) }}</strong>
                @if($key === 'error' && is_array(session('error')))
                    <div>{{ session('error.message') }}</div>
                    <div style="font-size:.74rem;opacity:.8;margin-top:.25rem">{{ session('error.db') }}</div>
                @else
                    <div>{{ session($key) }}</div>
                @endif
            </div>
        </div>
    @endif
@endforeach
