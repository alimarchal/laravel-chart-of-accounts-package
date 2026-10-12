{{-- Every <select> of the accounting screens becomes a Select2 (search, clear, keyboard), unless it has class "no-select2" or is a multiple.
     It also covers selects added later (Livewire, Alpine, modals). Select2 only triggers jQuery events, so a native "change" is dispatched on a choice:
     that is what onchange="…", Livewire (wire:model) and Alpine (x-model) listen to. An option may carry data-code and data-tag: it is then drawn as code + name + tag. --}}
@once
<script>
(function () {
    if (window.accSelect2) return;
    var kitClass = @js('acc-kit acc-kit-'.\Alimarchal\LaravelChartOfAccounts\Support\BladeKit::kit());
    var SKIP = 'select.no-select2, select[multiple], select.select2-hidden-accessible, select[data-no-select2], .select2-container select';

    function escapeHtml(text) { var d = document.createElement('div'); d.textContent = text == null ? '' : String(text); return d.innerHTML; }

    function rich(state) {
        if (!state.id) return state.text;
        var el = state.element, code = el && el.getAttribute && el.getAttribute('data-code');
        if (!code) return state.text;
        var name = el.getAttribute('data-name') || state.text, tag = el.getAttribute('data-tag');
        return window.jQuery('<span class="acc-opt"><span class="acc-opt-code">' + escapeHtml(code) + '</span><span class="acc-opt-name">' + escapeHtml(name) + '</span>' + (tag ? '<span class="acc-opt-tag">' + escapeHtml(tag) + '</span>' : '') + '</span>');
    }
    function richSelection(state) {
        if (!state.id) return state.text;
        var el = state.element, code = el && el.getAttribute && el.getAttribute('data-code');
        if (!code) return state.text;
        return window.jQuery('<span><span class="acc-opt-code">' + escapeHtml(code) + '</span>' + escapeHtml(el.getAttribute('data-name') || state.text) + '</span>');
    }

    function init(el) {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2 || !el.matches || el.matches(SKIP) || !el.closest('.acc-kit')) return;
        var $el = $(el), first = el.options && el.options[0];
        // An empty first option such as "All branches" is a choice in itself, not a placeholder to clear.
        var emptyIsValue = !!first && first.value === '' && (/^(all|any|no |none|head office)/i.test(first.textContent.trim()) || el.dataset.emptyIsValue !== undefined);
        var hasEmpty = !!first && first.value === '' && !emptyIsValue;
        var searchable = el.dataset.search === 'always' || el.options.length > 7;
        $el.select2({
            width: '100%',
            placeholder: hasEmpty ? (first.textContent.trim() || @js(__('Select…'))) : undefined,
            allowClear: hasEmpty && !el.required && el.dataset.clear !== 'false',
            minimumResultsForSearch: searchable ? 0 : Infinity,
            dropdownCssClass: kitClass,
            dropdownParent: $el.closest('[data-select2-parent], [role=dialog]').first().length ? $el.closest('[data-select2-parent], [role=dialog]').first() : undefined,
            templateResult: rich,
            templateSelection: richSelection,
            language: { noResults: function () { return @js(__('No match found')); } },
        });
        $el.on('select2:select select2:clear', function () { el.dispatchEvent(new Event('change', { bubbles: true })); });
        // Opening the dropdown puts the cursor in its search box.
        $el.on('select2:open', function () { var f = document.querySelector('.select2-container--open .select2-search__field'); if (f) f.focus(); });
    }

    function scan(root) {
        (root || document).querySelectorAll('.acc-kit select').forEach(init);
    }

    // Keeps a Select2 in step with a value that Alpine (or other code) changed.
    function sync(el, value) {
        var $ = window.jQuery;
        if (!$ || !el) return;
        var $el = $(el);
        if (String($el.val() == null ? '' : $el.val()) !== String(value == null ? '' : value)) $el.val(value == null ? '' : value).trigger('change.select2');
    }

    window.accSelect2 = { init: init, scan: scan, sync: sync };

    function start() {
        if (!window.jQuery || !window.jQuery.fn.select2) return;
        scan(document);
        document.addEventListener('click', function (event) {
            var label = event.target.closest && event.target.closest('label[for]');
            if (!label) return;
            var target = document.getElementById(label.getAttribute('for'));
            if (target && target.classList.contains('select2-hidden-accessible')) { event.preventDefault(); window.jQuery(target).select2('open'); }
        });
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                record.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.tagName === 'SELECT') init(node); else if (node.querySelectorAll) node.querySelectorAll('select').forEach(init);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
    document.addEventListener('livewire:navigated', function () { setTimeout(start, 0); });
})();
</script>
@endonce
