@if (session('success'))
<div {{ $attributes->merge(['class' => 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4 max-w-7xl mx-auto
    sm:px-6 lg:px-8 mb-2 shadow-xl']) }}
    data-status-sound="success"
    role="alert">
    <p class="font-bold">Success</p>
    <p>{{ session('success') }}</p>
</div>
@endif

@if (session('error'))
<div {{ $attributes->merge(['class' => 'max-w-7xl mx-auto sm:px-6 lg:px-8 bg-red-100 border-l-4 border-red-500
    text-red-700 py-4 mx-6']) }}
    data-status-sound="error"
    role="alert">
    <p class="font-bold">Error</p>
    @if(is_array(session('error')))
    <p>{{ session('error.message') }}</p>
    <p class="text-xs text-gray-500 mt-2">{{ session('error.db') }}</p>
    @else
    <p>{{ session('error') }}</p>
    @endif
</div>
@endif

@if (session('warning'))
<div {{ $attributes->merge(['class' => 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mx-6']) }}
    data-status-sound="warning"
    role="alert">
    <p class="font-bold">Warning</p>
    <p>{{ session('warning') }}</p>
</div>
@endif

@if (session('info'))
<div {{ $attributes->merge(['class' => 'bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mx-6']) }}
    data-status-sound="info"
    role="alert">
    <p class="font-bold">Information</p>
    <p>{{ session('info') }}</p>
</div>
@endif

@once
@push('scripts')
<script>
    (function () {
        const findTargets = () => document.querySelectorAll('[data-status-sound]');

        const playBeep = (tone = 'success') => {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) {
                return;
            }

            try {
                const ctx = new AudioCtx();
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();

                const settings = {
                    success: { type: 'sine', frequency: 880 },
                    error: { type: 'sawtooth', frequency: 360 },
                    warning: { type: 'triangle', frequency: 660 },
                    info: { type: 'square', frequency: 520 },
                }[tone] || { type: 'sine', frequency: 880 };

                oscillator.type = settings.type;
                oscillator.frequency.value = settings.frequency;
                oscillator.connect(gain);
                gain.connect(ctx.destination);

                const now = ctx.currentTime;
                gain.gain.setValueAtTime(0, now);
                gain.gain.linearRampToValueAtTime(0.18, now + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.4);

                oscillator.start(now);
                oscillator.stop(now + 0.4);
            } catch (error) {
                console.warn('Unable to play status sound', error);
            }
        };

        const init = () => {
            const targets = findTargets();
            if (!targets.length) {
                return;
            }

            try {
                const tone = targets[0].dataset.statusSound;
                playBeep(tone);
            } catch (err) {
                console.warn('Status sound failed', err);
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init, { once: true });
        } else {
            init();
        }
    })();
</script>
@endpush
@endonce
