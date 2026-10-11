/**
 * A confirm dialog in place of window.confirm(): a centred card with an icon, the question, and Cancel / Delete (or Confirm) buttons.
 * It is plain DOM with inline styles, so it looks the same whatever CSS the host application compiles.
 *
 *   confirmThen(`Delete ${name}?`, () => router.delete(url));
 */
const dangerous = (message: string): boolean => /\b(delete|remove|void|cancel|reverse|dispose|drop|stop)\b/i.test(message);

let open: HTMLElement | null = null;

export function confirmThen(message: string, onConfirm: () => void, onCancel?: () => void): void {
    if (typeof document === 'undefined') {
        return;
    }

    open?.remove();

    const danger = dangerous(message);
    const dark = document.documentElement.classList.contains('dark');
    const [first, ...others] = message.split('\n');
    const rest = others.join('\n').trim();

    const overlay = document.createElement('div');
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.style.cssText =
        'position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(17,24,39,.45);backdrop-filter:blur(2px);font-family:inherit';

    const icon = danger
        ? '<svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>'
        : '<svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>';

    const card = document.createElement('div');
    card.style.cssText = `width:100%;max-width:32rem;border-radius:.75rem;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);background:${dark ? '#18181b' : '#fff'};color:${dark ? '#f4f4f5' : '#111827'}`;
    card.innerHTML = `<div style="display:flex;gap:1rem;padding:1.5rem 1.5rem 1.25rem"><div style="flex:none;width:2.75rem;height:2.75rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:${danger ? '#fee2e2' : '#dbeafe'};color:${danger ? '#dc2626' : '#2563eb'}">${icon}</div><div style="min-width:0"><h3 style="margin:0;font-size:1.05rem;font-weight:600"></h3><p style="margin:.5rem 0 0;font-size:.9rem;color:${dark ? '#a1a1aa' : '#4b5563'};white-space:pre-line"></p></div></div><div style="display:flex;justify-content:flex-end;gap:.75rem;padding:1rem 1.5rem;background:${dark ? '#27272a' : '#f3f4f6'}"></div>`;
    (card.querySelector('h3') as HTMLElement).textContent = danger ? 'Are you sure?' : 'Please confirm';
    (card.querySelector('p') as HTMLElement).textContent = rest ? `${first}\n${rest}` : first;

    const base =
        'padding:.5rem 1rem;border-radius:.375rem;font-size:.72rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;';
    const no = document.createElement('button');
    no.type = 'button';
    no.textContent = 'Cancel';
    no.style.cssText = `${base}border:1px solid ${dark ? '#52525b' : '#d1d5db'};background:${dark ? '#18181b' : '#fff'};color:${dark ? '#e4e4e7' : '#374151'}`;
    const yes = document.createElement('button');
    yes.type = 'button';
    yes.textContent = danger ? (/\bdelete\b/i.test(message) ? 'Delete' : 'Yes, continue') : 'Confirm';
    yes.style.cssText = `${base}border:1px solid transparent;color:#fff;background:${danger ? '#dc2626' : '#1e3a8a'}`;
    (card.lastElementChild as HTMLElement).append(no, yes);
    overlay.append(card);
    document.body.append(overlay);
    open = overlay;
    no.focus();

    const close = (): void => {
        document.removeEventListener('keydown', onKey, true);
        overlay.remove();
        open = null;
    };
    const onKey = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') {
            close();
            onCancel?.();
        }
    };
    document.addEventListener('keydown', onKey, true);
    no.addEventListener('click', () => {
        close();
        onCancel?.();
    });
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            close();
            onCancel?.();
        }
    });
    yes.addEventListener('click', () => {
        close();
        onConfirm();
    });
}

/** Ask only when there is something to ask (a button that confirms sometimes). */
export function confirmIf(message: string | undefined, onConfirm: () => void): void {
    if (message) {
        confirmThen(message, onConfirm);
    } else {
        onConfirm();
    }
}
