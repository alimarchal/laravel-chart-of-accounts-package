import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export type AccountingI18n = {
    locale: string;
    dir: 'ltr' | 'rtl';
    locales: Record<string, string>;
    messages: Record<string, string>;
    switchUrl: string | null;
    rtlCss: string;
};

const SKIP = new Set(['SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE', 'NOSCRIPT']);
const ATTRIBUTES = ['placeholder', 'title', 'aria-label'];
let observer: MutationObserver | null = null;
let current: AccountingI18n | null = null;
let queued = false;

/**
 * Translates a piece of visible text when it equals a phrase of the dictionary exactly (surrounding whitespace is kept);
 * everything else, such as names, numbers and phrases that are not in the dictionary, stays as it is.
 */
function translateText(node: Text, messages: Record<string, string>): void {
    const parent = node.parentElement;

    if (!parent || SKIP.has(parent.tagName) || parent.closest('#accounting-language')) {
        return;
    }

    const raw = node.nodeValue ?? '';
    const plain = raw.replace(/\s+/g, ' ').trim();

    if (plain !== '' && Object.prototype.hasOwnProperty.call(messages, plain)) {
        const lead = raw.slice(0, raw.length - raw.trimStart().length);
        const trail = raw.slice(raw.trimEnd().length);
        node.nodeValue = lead + messages[plain] + trail;
    }
}

function translateTree(root: Node, messages: Record<string, string>): void {
    if (root.nodeType === Node.TEXT_NODE) {
        translateText(root as Text, messages);

        return;
    }

    if (root.nodeType !== Node.ELEMENT_NODE && root.nodeType !== Node.DOCUMENT_NODE) {
        return;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let node = walker.nextNode();

    while (node) {
        translateText(node as Text, messages);
        node = walker.nextNode();
    }

    const scope = root.nodeType === Node.ELEMENT_NODE ? (root as Element) : document.body;
    scope.querySelectorAll('[placeholder],[title],[aria-label]').forEach((element) => {
        ATTRIBUTES.forEach((attribute) => {
            const value = element.getAttribute(attribute);

            if (value !== null && Object.prototype.hasOwnProperty.call(messages, value)) {
                element.setAttribute(attribute, messages[value]);
            }
        });
    });
}

function schedule(): void {
    if (queued || !current) {
        return;
    }

    queued = true;
    window.requestAnimationFrame(() => {
        queued = false;

        if (current) {
            translateTree(document.body, current.messages);
        }
    });
}

function switcher(i18n: AccountingI18n): void {
    document.getElementById('accounting-language')?.remove();

    if (!i18n.switchUrl || Object.keys(i18n.locales).length < 2) {
        return;
    }

    const wrap = document.createElement('div');
    wrap.id = 'accounting-language';
    wrap.style.cssText = `position:fixed;bottom:12px;${i18n.dir === 'rtl' ? 'left' : 'right'}:12px;z-index:50;background:rgba(255,255,255,.95);border:1px solid #d4d4d8;border-radius:8px;padding:2px 6px;box-shadow:0 1px 4px rgba(0,0,0,.15)`;
    const select = document.createElement('select');
    select.setAttribute('aria-label', 'Language');
    select.style.cssText = 'border:0;background:transparent;font-size:13px;color:#111;outline:none';
    Object.entries(i18n.locales).forEach(([code, name]) => {
        const option = document.createElement('option');
        option.value = code;
        option.textContent = name;
        option.selected = code === i18n.locale;
        select.appendChild(option);
    });
    select.addEventListener('change', async () => {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        await fetch(i18n.switchUrl as string, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ locale: select.value }),
        });
        window.location.reload();
    });
    wrap.appendChild(select);
    document.body.appendChild(wrap);
}

/**
 * Applies the language of the accounting pages: direction and language of the document, the right-to-left stylesheet, a small
 * language switcher, and phrase-by-phrase translation of what is on screen, kept up to date as the page changes. It installs once
 * and survives page visits.
 */
export function applyAccountingI18n(i18n: AccountingI18n | undefined): void {
    if (typeof document === 'undefined') {
        return;
    }

    current = i18n && Object.keys(i18n.messages).length > 0 ? i18n : null;
    document.getElementById('accounting-rtl')?.remove();

    if (!i18n) {
        return;
    }

    document.documentElement.lang = i18n.locale;
    document.documentElement.dir = i18n.dir;

    if (i18n.dir === 'rtl') {
        const style = document.createElement('style');
        style.id = 'accounting-rtl';
        style.textContent = i18n.rtlCss;
        document.head.appendChild(style);
    }

    switcher(i18n);

    if (!current) {
        observer?.disconnect();
        observer = null;

        return;
    }

    if (!observer) {
        observer = new MutationObserver(schedule);
        observer.observe(document.body, { childList: true, subtree: true, characterData: true });
    }

    translateTree(document.body, current.messages);
}

export function useAccountingI18n(): AccountingI18n | undefined {
    const i18n = (usePage().props as { accountingI18n?: AccountingI18n }).accountingI18n;

    useEffect(() => {
        applyAccountingI18n(i18n);
    }, [i18n?.locale, i18n?.dir]);

    return i18n;
}
