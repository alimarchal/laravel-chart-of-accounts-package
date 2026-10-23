{{-- Feedback for every action, in every layout: a confirm dialog instead of the browser's confirm() (delete, void, post …) and a sound on success / error. --}}
<script>
(function () {
    if (window.__accFeedback) { return; }
    window.__accFeedback = true;

    // ---- Sounds: a rising two-note chime for success, a falling low one for an error. The browser may keep audio locked until the user has clicked
    // something on the page; then the sound is played with the first click or key press.
    var ctx = null;
    var pending = null;
    var lastAt = 0;
    var tones = { success: [[660, 0], [880, 0.1]], error: [[330, 0], [220, 0.12]], warning: [[520, 0], [520, 0.12]], info: [[600, 0]] };

    function play(kind) {
        var Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx || Date.now() - lastAt < 500) { return; }
        try {
            ctx = ctx || new Ctx();
            if (ctx.state === 'suspended') { ctx.resume(); }
            if (ctx.state !== 'running') { pending = kind; return; }
            lastAt = Date.now();
            (tones[kind] || tones.info).forEach(function (note) {
                var osc = ctx.createOscillator(), gain = ctx.createGain(), start = ctx.currentTime + note[1];
                osc.type = kind === 'error' ? 'sawtooth' : 'sine';
                osc.frequency.value = note[0];
                gain.gain.setValueAtTime(0.0001, start);
                gain.gain.exponentialRampToValueAtTime(0.16, start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.22);
                osc.connect(gain); gain.connect(ctx.destination);
                osc.start(start); osc.stop(start + 0.25);
            });
        } catch (e) { /* no audio here */ }
    }
    window.accountingSound = play;

    ['pointerdown', 'keydown'].forEach(function (name) {
        document.addEventListener(name, function () {
            if (ctx && ctx.state === 'suspended') { ctx.resume(); }
            if (pending) { var kind = pending; pending = null; setTimeout(function () { play(kind); }, 30); }
        }, { capture: true });
    });

    function announce() {
        var flash = document.querySelector('[data-status-sound]');
        if (flash) { play(flash.getAttribute('data-status-sound')); }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', announce, { once: true }); } else { announce(); }
    // Livewire pages: a validation error or a notification shown without a page load.
    document.addEventListener('livewire:init', function () {
        if (!window.Livewire || !window.Livewire.hook) { return; }
        window.Livewire.hook('commit', function (h) {
            h.succeed(function () { setTimeout(function () { if (document.querySelector('[data-status-sound="error"]')) { play('error'); } }, 50); });
            h.fail(function () { play('error'); });
        });
    });

    // ---- The confirm dialog.
    var overlay = null;
    function dangerous(message) { return /\b(delete|remove|void|cancel|reverse|dispose|drop)\b/i.test(message); }

    function ask(message, onYes, onCancel) {
        if (overlay) { overlay.remove(); }
        var danger = dangerous(message);
        var dark = document.documentElement.classList.contains('dark');
        overlay = document.createElement('div');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(17,24,39,.45);backdrop-filter:blur(2px);font-family:inherit';
        var first = message.split('\n')[0];
        var rest = message.split('\n').slice(1).join('\n').trim();
        var iconBg = danger ? '#fee2e2' : '#dbeafe', iconFg = danger ? '#dc2626' : '#2563eb';
        var icon = danger
            ? '<svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>'
            : '<svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>';
        var card = document.createElement('div');
        card.style.cssText = 'width:100%;max-width:32rem;border-radius:.75rem;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);background:' + (dark ? '#18181b' : '#fff') + ';color:' + (dark ? '#f4f4f5' : '#111827');
        card.innerHTML = '<div style="display:flex;gap:1rem;padding:1.5rem 1.5rem 1.25rem"><div style="flex:none;width:2.75rem;height:2.75rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:' + iconBg + ';color:' + iconFg + '">' + icon + '</div><div style="min-width:0"><h3 style="margin:0;font-size:1.05rem;font-weight:600"></h3><p style="margin:.5rem 0 0;font-size:.9rem;color:' + (dark ? '#a1a1aa' : '#4b5563') + ';white-space:pre-line"></p></div></div><div style="display:flex;justify-content:flex-end;gap:.75rem;padding:1rem 1.5rem;background:' + (dark ? '#27272a' : '#f3f4f6') + '"></div>';
        card.querySelector('h3').textContent = danger ? 'Are you sure?' : 'Please confirm';
        card.querySelector('p').textContent = rest ? first + '\n' + rest : first;
        var buttons = card.lastChild;
        var base = 'padding:.5rem 1rem;border-radius:.375rem;font-size:.72rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;';
        var no = document.createElement('button');
        no.type = 'button'; no.textContent = 'Cancel';
        no.style.cssText = base + 'border:1px solid ' + (dark ? '#52525b' : '#d1d5db') + ';background:' + (dark ? '#18181b' : '#fff') + ';color:' + (dark ? '#e4e4e7' : '#374151');
        var yes = document.createElement('button');
        yes.type = 'button'; yes.textContent = danger ? (/\bdelete\b/i.test(message) ? 'Delete' : 'Yes, continue') : 'Confirm';
        yes.style.cssText = base + 'border:1px solid transparent;color:#fff;background:' + (danger ? '#dc2626' : '#1e3a8a');
        buttons.appendChild(no); buttons.appendChild(yes);
        overlay.appendChild(card);
        document.body.appendChild(overlay);
        no.focus();
        function close() { document.removeEventListener('keydown', onKey, true); if (overlay) { overlay.remove(); overlay = null; } }
        function onKey(e) { if (e.key === 'Escape') { close(); onCancel && onCancel(); } }
        document.addEventListener('keydown', onKey, true);
        no.addEventListener('click', function () { close(); onCancel && onCancel(); });
        overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(); onCancel && onCancel(); } });
        yes.addEventListener('click', function () { close(); onYes(); });
    }
    window.accountingConfirm = ask;

    function message(el, attribute) {
        var fixed = el.getAttribute('data-confirm');
        if (fixed) { return fixed; }
        var m = (el.getAttribute(attribute) || '').match(/confirm\(\s*(['"])([\s\S]*?)\1\s*\)/);

        return m ? m[2].replace(/\\n/g, '\n').replace(/\\(['"\\])/g, '$1') : null;
    }

    // Forms that ask before they submit: onsubmit="return confirm('…')" or data-confirm="…".
    document.addEventListener('submit', function (e) {
        var form = e.target, text = form && form.getAttribute ? message(form, 'onsubmit') : null;
        if (!text || form.__accConfirmed) { return; }
        e.preventDefault(); e.stopImmediatePropagation();
        var submitter = e.submitter || null;
        ask(text, function () {
            form.__accConfirmed = true;
            form.removeAttribute('onsubmit');
            if (submitter && submitter.removeAttribute) { submitter.removeAttribute('onclick'); }
            form.requestSubmit ? form.requestSubmit(submitter || undefined) : form.submit();
        });
    }, true);

    // Buttons that ask before they act: onclick="return confirm('…')".
    document.addEventListener('click', function (e) {
        var button = e.target.closest ? e.target.closest('[onclick*="confirm("], [data-confirm]:not(form)') : null;
        if (!button || button.__accConfirmed) { return; }
        var text = message(button, 'onclick');
        if (!text) { return; }
        e.preventDefault(); e.stopImmediatePropagation();
        ask(text, function () { button.__accConfirmed = true; button.removeAttribute('onclick'); button.click(); });
    }, true);

    // Submit once: a double click or a second Enter must not post the same form twice (receipts, payments, journals …).
    // Forms that are not plain POST/PUT/DELETE forms, and Livewire forms (which guard themselves), are left alone.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || !form || !form.getAttribute) { return; }
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'get' || form.hasAttribute('wire:submit') || form.hasAttribute('wire:submit.prevent') || form.hasAttribute('data-allow-resubmit')) { return; }
        if (form.__accSubmitting) { e.preventDefault(); return; }
        form.__accSubmitting = true;
        setTimeout(function () {
            Array.prototype.forEach.call(form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]'), function (b) { b.disabled = true; });
        }, 0);
        // A page that stays (validation error shown by script, download response) becomes usable again.
        setTimeout(function () {
            form.__accSubmitting = false;
            Array.prototype.forEach.call(form.querySelectorAll('button, input[type="submit"]'), function (b) { b.disabled = false; });
        }, 15000);
    });
    window.addEventListener('pageshow', function () {
        Array.prototype.forEach.call(document.forms, function (f) { f.__accSubmitting = false; });
    });
})();
</script>
