import { usePage } from '@inertiajs/react';
import { useAccountingI18n } from '@/lib/i18n';

export type CompanySummary = { id: number; code: string; name: string };

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

export function useAccounting(): AccountingShared {
    useAccountingI18n();
    const props = usePage().props as { accounting?: Partial<AccountingShared> };

    return {
        permissions: props.accounting?.permissions ?? {},
        flash: props.accounting?.flash ?? {},
        features: props.accounting?.features ?? {},
        disabledPaths: props.accounting?.disabledPaths ?? [],
        approvals: props.accounting?.approvals ?? {
            enabled: false,
            threshold: '0',
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
function tone(frequencies: number[], duration = 0.09): void {
    if (typeof window === 'undefined') {
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
        const context = new AudioContextClass();
        frequencies.forEach((frequency, index) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            const start = context.currentTime + index * duration;

            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.06, start);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start(start);
            oscillator.stop(start + duration);
        });
    } catch {
        // Audio is a nicety; never break the page for it.
    }
}

export const playSuccessSound = (): void => tone([660, 880]);
export const playErrorSound = (): void => tone([330, 220]);
