import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { useAccountingI18n } from '@/lib/i18n';

export type CompanySummary = { id: number; code: string; name: string };
export type BranchSummary = { id: number; code: string; name: string };

/**
 * Data shared with every accounting page by the package's ShareAccountingInertiaData middleware
 * (independent of the host app's HandleInertiaRequests).
 */
export type AccountingShared = {
    permissions: Record<string, boolean>;
    company: {
        enabled: boolean;
        current: CompanySummary | null;
        list: CompanySummary[];
    };
    branch: {
        enabled: boolean;
        current: BranchSummary | null;
        list: BranchSummary[];
        restricted: boolean;
    };
    flash: { success?: string | null; error?: string | null };
    approvals: { enabled: boolean; threshold: string };
    features: Record<string, boolean>;
    disabledPaths: string[];
};

/**
 * False when the screen at this link belongs to a feature that is switched off (the server shares the switched-off paths as globs).
 */
export function linkEnabled(href: string, disabledPaths: string[]): boolean {
    const path = href.split(/[?#]/)[0].replace(/\/+$/, '');

    return !disabledPaths.some((pattern) => new RegExp('^' + pattern.split('*').map((part) => part.replace(/[.+?^${}()|[\]\\]/g, '\\$&')).join('.*') + '$').test(path));
}

const announced = new WeakSet<object>();

export function useAccounting(): AccountingShared {
    useAccountingI18n();
    const props = usePage().props as { accounting?: Partial<AccountingShared>; errors?: Record<string, string> };

    // A sound for every result: a chime when the server answers with a success message, a lower one for an error message or a validation error.
    // Each flash message is announced once, however many components ask for the shared data.
    const flashMessage = props.accounting?.flash;
    useEffect(() => {
        if (!flashMessage || announced.has(flashMessage)) {
            return;
        }

        announced.add(flashMessage);

        if (flashMessage.error) {
            playErrorSound();
        } else if (flashMessage.success) {
            playSuccessSound();
        }
    }, [flashMessage]);

    const validationErrors = props.errors;
    useEffect(() => {
        if (validationErrors && Object.keys(validationErrors).length > 0 && !announced.has(validationErrors)) {
            announced.add(validationErrors);
            playErrorSound();
        }
    }, [validationErrors]);

    return {
        permissions: props.accounting?.permissions ?? {},
        flash: props.accounting?.flash ?? {},
        features: props.accounting?.features ?? {},
        disabledPaths: props.accounting?.disabledPaths ?? [],
        approvals: props.accounting?.approvals ?? {
            enabled: false,
            threshold: '0',
        },
        branch: props.accounting?.branch ?? {
            enabled: false,
            current: null,
            list: [],
            restricted: false,
        },
        company: props.accounting?.company ?? {
            enabled: false,
            current: null,
            list: [],
        },
    };
}

/**
 * Short confirmation tones generated with the Web Audio API (no audio files needed).
 * Silently does nothing where audio is unavailable or blocked.
 */
let context: AudioContext | null = null;
let lastPlayed = 0;

function tone(frequencies: number[], duration = 0.09, type: OscillatorType = 'sine'): void {
    if (typeof window === 'undefined' || Date.now() - lastPlayed < 500) {
        return;
    }

    const AudioContextClass =
        window.AudioContext ??
        (window as unknown as { webkitAudioContext?: typeof AudioContext })
            .webkitAudioContext;

    if (!AudioContextClass) {
        return;
    }

    try {
        context = context ?? new AudioContextClass();

        if (context.state === 'suspended') {
            void context.resume();
        }

        if (context.state !== 'running') {
            return;
        }

        lastPlayed = Date.now();
        const audio = context;
        frequencies.forEach((frequency, index) => {
            const oscillator = audio.createOscillator();
            const gain = audio.createGain();
            const start = audio.currentTime + index * duration;

            oscillator.type = type;
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.16, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + duration + 0.12);
            oscillator.connect(gain).connect(audio.destination);
            oscillator.start(start);
            oscillator.stop(start + duration + 0.15);
        });
    } catch {
        // Audio is a nicety; never break the page for it.
    }
}

export const playSuccessSound = (): void => tone([660, 880], 0.1);
export const playErrorSound = (): void => tone([330, 220], 0.12, 'sawtooth');
