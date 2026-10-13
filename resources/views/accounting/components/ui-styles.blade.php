{{--
    The look of the accounting screens, in one place. Everything is scoped to .acc-kit (the wrapper the layout puts around a page), written as plain
    CSS with custom properties, so it does not need to be compiled by the host's Tailwind and cannot leak into the host's own pages.
    Light and dark follow the host: the .dark class on <html> (Livewire starter kit, Breeze) or the browser's setting (Jetstream).
    The older screens still use Tailwind utility classes; the second half of this sheet gives those the same look (flat headers, readable weights).
--}}
@once
<style id="acc-ui">
.acc-kit{--acc-surface:#fff;--acc-surface-2:#f8fafc;--acc-border:#e2e8f0;--acc-border-strong:#cbd5e1;--acc-text:#0f172a;--acc-muted:#64748b;--acc-faint:#94a3b8;
  --acc-accent:#4f46e5;--acc-accent-hover:#4338ca;--acc-accent-soft:#eef2ff;--acc-accent-ink:#fff;--acc-ok:#047857;--acc-ok-soft:#ecfdf5;--acc-bad:#be123c;--acc-bad-soft:#fff1f2;
  --acc-warn:#b45309;--acc-warn-soft:#fffbeb;--acc-info:#0369a1;--acc-info-soft:#f0f9ff;--acc-hover:#f1f5f9;--acc-ring:rgba(79,70,229,.28);--acc-shadow:0 1px 2px rgba(15,23,42,.05),0 1px 3px rgba(15,23,42,.04);
  --acc-radius:.75rem;font-feature-settings:"cv11","ss01";color:var(--acc-text)}
.dark .acc-kit{--acc-surface:#18181b;--acc-surface-2:#1f1f23;--acc-border:#3f3f46;--acc-border-strong:#52525b;--acc-text:#f4f4f5;--acc-muted:#a1a1aa;--acc-faint:#71717a;
  --acc-accent:#818cf8;--acc-accent-hover:#a5b4fc;--acc-accent-soft:rgba(129,140,248,.14);--acc-accent-ink:#18181b;--acc-ok:#34d399;--acc-ok-soft:rgba(52,211,153,.12);--acc-bad:#fb7185;--acc-bad-soft:rgba(251,113,133,.12);
  --acc-warn:#fbbf24;--acc-warn-soft:rgba(251,191,36,.12);--acc-info:#38bdf8;--acc-info-soft:rgba(56,189,248,.12);--acc-hover:#27272a;--acc-ring:rgba(129,140,248,.35);--acc-shadow:none}
@media (prefers-color-scheme:dark){.acc-kit-jetstream{--acc-surface:#18181b;--acc-surface-2:#1f1f23;--acc-border:#3f3f46;--acc-border-strong:#52525b;--acc-text:#f4f4f5;--acc-muted:#a1a1aa;--acc-faint:#71717a;
  --acc-accent:#818cf8;--acc-accent-hover:#a5b4fc;--acc-accent-soft:rgba(129,140,248,.14);--acc-accent-ink:#18181b;--acc-ok:#34d399;--acc-ok-soft:rgba(52,211,153,.12);--acc-bad:#fb7185;--acc-bad-soft:rgba(251,113,133,.12);
  --acc-warn:#fbbf24;--acc-warn-soft:rgba(251,191,36,.12);--acc-info:#38bdf8;--acc-info-soft:rgba(56,189,248,.12);--acc-hover:#27272a;--acc-ring:rgba(129,140,248,.35);--acc-shadow:none}}

/* ---- page frame ---- */
.acc-kit .acc-wrap{max-width:80rem;margin:0 auto;padding:0 1rem}
@media(min-width:640px){.acc-kit .acc-wrap{padding:0 1.5rem}}
@media(min-width:1024px){.acc-kit .acc-wrap{padding:0 2rem}}
.acc-kit-flux .acc-wrap{max-width:none;padding:0 1rem}
.acc-kit .acc-stack{display:flex;flex-direction:column;gap:1rem;padding-bottom:4rem}
.acc-kit .acc-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:.75rem 1.25rem;width:100%}
.acc-kit .acc-head-l{min-width:0}
.acc-kit .acc-crumb{display:inline-flex;align-items:center;gap:.25rem;font-size:.75rem;font-weight:500;color:var(--acc-muted);text-decoration:none;margin-bottom:.15rem}
.acc-kit .acc-crumb:hover{color:var(--acc-accent)}
.acc-kit .acc-title{margin:0;font-size:1.35rem;line-height:1.75rem;font-weight:650;letter-spacing:-.01em;color:var(--acc-text)}
.acc-kit .acc-sub{margin:.15rem 0 0;font-size:.85rem;color:var(--acc-muted)}
.acc-kit .acc-head-r{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem}

/* ---- buttons ---- */
.acc-kit .acc-btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;height:2.25rem;padding:0 .9rem;border-radius:.6rem;border:1px solid var(--acc-border-strong);background:var(--acc-surface);color:var(--acc-text);
  font-size:.82rem;font-weight:600;line-height:1;text-decoration:none;text-transform:none;letter-spacing:0;cursor:pointer;white-space:nowrap;box-shadow:var(--acc-shadow);transition:background-color .12s,border-color .12s,box-shadow .12s,transform .05s}
.acc-kit .acc-btn:hover{background:var(--acc-hover)}
.acc-kit .acc-btn:active{transform:translateY(1px)}
.acc-kit .acc-btn:focus-visible{outline:none;box-shadow:0 0 0 3px var(--acc-ring)}
.acc-kit .acc-btn svg{width:1.05rem;height:1.05rem;flex:none}
.acc-kit .acc-btn[disabled],.acc-kit .acc-btn.is-disabled{opacity:.5;cursor:not-allowed;transform:none}
.acc-kit .acc-btn--primary{background:var(--acc-accent);border-color:var(--acc-accent);color:var(--acc-accent-ink)}
.acc-kit .acc-btn--primary:hover{background:var(--acc-accent-hover);border-color:var(--acc-accent-hover)}
.acc-kit .acc-btn--success{background:var(--acc-ok);border-color:var(--acc-ok);color:#fff}
.acc-kit .acc-btn--success:hover{filter:brightness(1.08);background:var(--acc-ok)}
.acc-kit .acc-btn--danger{background:var(--acc-bad);border-color:var(--acc-bad);color:#fff}
.acc-kit .acc-btn--danger:hover{filter:brightness(1.08);background:var(--acc-bad)}
.acc-kit .acc-btn--ghost{background:transparent;border-color:transparent;box-shadow:none;color:var(--acc-muted)}
.acc-kit .acc-btn--ghost:hover{background:var(--acc-hover);color:var(--acc-text)}
.acc-kit .acc-btn--icon{width:2.25rem;padding:0}
.acc-kit .acc-btn--sm{height:1.9rem;padding:0 .65rem;font-size:.78rem}
.acc-kit .acc-count{display:inline-flex;align-items:center;justify-content:center;min-width:1.2rem;height:1.2rem;padding:0 .3rem;border-radius:999px;background:var(--acc-accent);color:var(--acc-accent-ink);font-size:.7rem;font-weight:700}

/* ---- cards ---- */
.acc-kit .acc-card{background:var(--acc-surface);border:1px solid var(--acc-border);border-radius:var(--acc-radius);box-shadow:var(--acc-shadow);overflow:hidden}
.acc-kit .acc-card-h{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem 1rem;padding:.85rem 1.1rem;border-bottom:1px solid var(--acc-border);background:var(--acc-surface-2)}
.acc-kit .acc-card-h h3{margin:0;font-size:.9rem;font-weight:650;color:var(--acc-text)}
.acc-kit .acc-card-h p{margin:.1rem 0 0;font-size:.78rem;color:var(--acc-muted)}
.acc-kit .acc-card-b{padding:1.1rem}

/* ---- fields ---- */
.acc-kit .acc-grid{display:grid;gap:.9rem 1rem;grid-template-columns:repeat(auto-fill,minmax(14rem,1fr))}
.acc-kit .acc-grid-2{display:grid;gap:.9rem 1rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,18rem),1fr))}
.acc-kit .acc-span{grid-column:1/-1}
.acc-kit .acc-field{display:flex;flex-direction:column;gap:.3rem;min-width:0}
.acc-kit .acc-label{font-size:.75rem;font-weight:600;color:var(--acc-muted);letter-spacing:.01em}
.acc-kit .acc-field>label{font-size:.75rem;font-weight:600;color:var(--acc-muted);letter-spacing:.01em}
.acc-kit .acc-label .req{color:var(--acc-bad);margin-left:.15rem}
.acc-kit .acc-hint{font-size:.74rem;color:var(--acc-faint)}
.acc-kit .acc-err{font-size:.76rem;color:var(--acc-bad)}
.acc-kit .acc-input,.acc-kit .acc-select{display:block;width:100%;height:2.4rem;padding:0 .75rem;border-radius:.6rem;border:1px solid var(--acc-border-strong);background:var(--acc-surface);color:var(--acc-text);font-size:.88rem;box-shadow:var(--acc-shadow);transition:border-color .12s,box-shadow .12s}
.acc-kit textarea.acc-input{height:auto;padding:.55rem .75rem}
.acc-kit .acc-input:focus,.acc-kit .acc-select:focus{outline:none;border-color:var(--acc-accent);box-shadow:0 0 0 3px var(--acc-ring)}
.acc-kit .acc-input::placeholder{color:var(--acc-faint)}
.acc-kit .acc-input[disabled],.acc-kit .acc-input[readonly]{background:var(--acc-surface-2);color:var(--acc-muted)}
.acc-kit .acc-input.is-bad{border-color:var(--acc-bad)}
.acc-kit .acc-num{text-align:right;font-variant-numeric:tabular-nums}

/* ---- file inputs ---- */
.acc-kit input[type=file]{font-size:.84rem;color:var(--acc-muted)}
.acc-kit input[type=file]::file-selector-button{margin-right:.7rem;height:2.1rem;padding:0 .9rem;border-radius:.55rem;border:1px solid var(--acc-border-strong);background:var(--acc-surface);color:var(--acc-text);font-size:.8rem;font-weight:600;cursor:pointer}
.acc-kit input[type=file]::file-selector-button:hover{background:var(--acc-hover)}

/* ---- filters ---- */
.acc-kit .acc-filters{display:none}
.acc-kit .acc-filters.is-open{display:block;animation:acc-drop .18s ease-out}
@keyframes acc-drop{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
.acc-kit .acc-filters-actions{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--acc-border)}
.acc-kit .acc-chips{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
.acc-kit .acc-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .6rem;border-radius:999px;background:var(--acc-accent-soft);color:var(--acc-accent);font-size:.75rem;font-weight:600}
.acc-kit .acc-chip b{font-weight:500;color:var(--acc-muted)}

/* ---- tables ---- */
.acc-kit .acc-table-wrap{overflow-x:auto}
.acc-kit .acc-table{width:100%;border-collapse:separate;border-spacing:0;font-size:.855rem}
.acc-kit .acc-table thead th{text-align:left;position:sticky;top:0;z-index:1;padding:.65rem .9rem;background:var(--acc-surface-2);border-bottom:1px solid var(--acc-border);color:var(--acc-muted);font-size:.7rem;font-weight:650;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}
.acc-kit .acc-table tbody td{padding:.7rem .9rem;border-bottom:1px solid var(--acc-border);vertical-align:middle;color:var(--acc-text);font-weight:400}
.acc-kit .acc-table tbody tr:last-child td{border-bottom:0}
.acc-kit .acc-table tbody tr:hover td{background:var(--acc-hover)}
.acc-kit .acc-table tfoot td{padding:.7rem .9rem;border-top:1px solid var(--acc-border-strong);background:var(--acc-surface-2);font-weight:650}
.acc-kit .acc-table .num,.acc-kit .acc-table .text-right{font-variant-numeric:tabular-nums}
.acc-kit .acc-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.82rem}
.acc-kit .acc-muted{color:var(--acc-muted)}
.acc-kit .acc-empty{padding:3rem 1rem;text-align:center;color:var(--acc-muted)}
.acc-kit .acc-empty svg{width:2.5rem;height:2.5rem;margin:0 auto .6rem;color:var(--acc-faint)}
.acc-kit .acc-empty a{color:var(--acc-accent);font-weight:600}
.acc-kit .acc-pager{padding:.75rem 1rem;border-top:1px solid var(--acc-border)}

/* ---- badges & alerts ---- */
.acc-kit .acc-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.15rem .55rem;border-radius:999px;font-size:.72rem;font-weight:650;line-height:1.1rem;background:var(--acc-hover);color:var(--acc-muted)}
.acc-kit .acc-badge::before{content:"";width:.4rem;height:.4rem;border-radius:50%;background:currentColor}
.acc-kit .acc-badge--ok{background:var(--acc-ok-soft);color:var(--acc-ok)}
.acc-kit .acc-badge--bad{background:var(--acc-bad-soft);color:var(--acc-bad)}
.acc-kit .acc-badge--warn{background:var(--acc-warn-soft);color:var(--acc-warn)}
.acc-kit .acc-badge--info{background:var(--acc-info-soft);color:var(--acc-info)}
.acc-kit .acc-badge--accent{background:var(--acc-accent-soft);color:var(--acc-accent)}
.acc-kit .acc-alert{display:flex;gap:.7rem;align-items:flex-start;padding:.8rem 1rem;border-radius:.7rem;border:1px solid;font-size:.86rem}
.acc-kit .acc-alert svg{width:1.15rem;height:1.15rem;flex:none;margin-top:.1rem}
.acc-kit .acc-alert strong{display:block;font-weight:650}
.acc-kit .acc-alert--ok{background:var(--acc-ok-soft);border-color:color-mix(in srgb,var(--acc-ok) 30%,transparent);color:var(--acc-ok)}
.acc-kit .acc-alert--bad{background:var(--acc-bad-soft);border-color:color-mix(in srgb,var(--acc-bad) 30%,transparent);color:var(--acc-bad)}
.acc-kit .acc-alert--warn{background:var(--acc-warn-soft);border-color:color-mix(in srgb,var(--acc-warn) 30%,transparent);color:var(--acc-warn)}
.acc-kit .acc-alert--info{background:var(--acc-info-soft);border-color:color-mix(in srgb,var(--acc-info) 30%,transparent);color:var(--acc-info)}
.acc-kit .acc-alert ul{margin:.3rem 0 0;padding-left:1.1rem;list-style:disc}

/* ---- context bar (language, company, branch) ---- */
.acc-kit .acc-context{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:.5rem 1rem;padding:.6rem 0 0;font-size:.8rem;color:var(--acc-muted)}
.acc-kit .acc-context form{display:inline-flex;align-items:center;gap:.4rem;margin:0}
.acc-kit .acc-context select{height:2rem;padding:0 1.8rem 0 .6rem;font-size:.8rem;border-radius:.55rem;border:1px solid var(--acc-border-strong);background-color:var(--acc-surface);color:var(--acc-text)}
.acc-kit .acc-context b{color:var(--acc-text);font-weight:650}
.acc-kit .acc-context .select2-container .select2-selection--single{height:2rem!important}

/* ---- section tabs ---- */
.acc-kit .acc-tabs{display:inline-flex;gap:.25rem;padding:.25rem;margin:.9rem 0 1rem;border-radius:.8rem;background:var(--acc-hover)}
.acc-kit .acc-tabs a{padding:.4rem 1rem;border-radius:.6rem;font-size:.85rem;font-weight:600;color:var(--acc-muted);text-decoration:none}
.acc-kit .acc-tabs a:hover{color:var(--acc-text)}
.acc-kit .acc-tabs a[aria-current]{background:var(--acc-surface);color:var(--acc-text);box-shadow:var(--acc-shadow)}

/* ---- select2 (every dropdown of the accounting screens) ---- */
.acc-kit .select2-container{width:100%!important;display:block}
.acc-kit .select2-container .select2-selection--single{height:2.4rem!important;padding:0 .35rem 0 .75rem!important;margin-top:0!important;display:flex!important;align-items:center;border:1px solid var(--acc-border-strong)!important;border-radius:.6rem!important;background:var(--acc-surface)!important;box-shadow:var(--acc-shadow)!important;transition:border-color .12s,box-shadow .12s}
.acc-kit .select2-container--focus .select2-selection--single,.acc-kit .select2-container--open .select2-selection--single{border-color:var(--acc-accent)!important;box-shadow:0 0 0 3px var(--acc-ring)!important}
.acc-kit .select2-container .select2-selection--single .select2-selection__rendered{flex:1;padding:0!important;line-height:1.25!important;font-size:.88rem;color:var(--acc-text)!important;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.acc-kit .select2-container .select2-selection--single .select2-selection__placeholder{color:var(--acc-faint)!important}
.acc-kit .select2-container .select2-selection--single .select2-selection__arrow{position:relative!important;height:100%!important;width:1.4rem!important;top:auto!important;right:auto!important;flex:none}
.acc-kit .select2-container .select2-selection--single .select2-selection__clear{order:2;margin:0 .15rem 0 .4rem!important;padding:0!important;height:auto!important;font-size:1.1rem!important;font-weight:500!important;line-height:1!important;color:var(--acc-faint)!important;float:none!important}
.acc-kit .select2-container .select2-selection--single .select2-selection__clear:hover{color:var(--acc-bad)!important}
.acc-kit .select2-container--disabled .select2-selection--single{background:var(--acc-surface-2)!important;opacity:.7}
.acc-select2-drop.select2-dropdown,.select2-container--open .select2-dropdown{border:1px solid var(--acc-border,#e2e8f0)!important;border-radius:.7rem!important;box-shadow:0 12px 32px rgba(15,23,42,.16)!important;overflow:hidden;margin-top:.25rem;background:var(--acc-surface,#fff)!important;color:var(--acc-text,#0f172a)}
.select2-container--open .select2-dropdown--above{margin-top:-.25rem}
.select2-dropdown .select2-search--dropdown{padding:.5rem!important;border-bottom:1px solid var(--acc-border,#e2e8f0)}
.select2-dropdown .select2-search--dropdown .select2-search__field{height:2.2rem;padding:0 .7rem!important;border:1px solid var(--acc-border-strong,#cbd5e1)!important;border-radius:.5rem!important;background:var(--acc-surface,#fff)!important;color:var(--acc-text,#0f172a)!important;font-size:.85rem;outline:none}
.select2-dropdown .select2-search--dropdown .select2-search__field:focus{border-color:var(--acc-accent,#4f46e5)!important;box-shadow:0 0 0 3px var(--acc-ring,rgba(79,70,229,.28))}
.select2-container--default .select2-results>.select2-results__options{max-height:18rem!important;padding:.3rem}
.select2-container--default .select2-results__option{padding:.45rem .6rem!important;border-radius:.45rem;font-size:.86rem;color:var(--acc-text,#0f172a)!important}
.select2-container--default .select2-results__option--highlighted[aria-selected],.select2-container--default .select2-results__option--highlighted.select2-results__option--selectable{background:var(--acc-accent-soft,#eef2ff)!important;color:var(--acc-accent,#4f46e5)!important}
.select2-container--default .select2-results__option[aria-selected=true],.select2-container--default .select2-results__option--selected{background:var(--acc-hover,#f1f5f9)!important;font-weight:650}
.select2-results__option .acc-opt{display:flex;align-items:baseline;gap:.6rem;min-width:0}
.select2-results__option .acc-opt-code{flex:none;min-width:3.6rem;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.8rem;font-weight:700;color:var(--acc-accent,#4f46e5)}
.select2-results__option .acc-opt-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.select2-results__option .acc-opt-tag{flex:none;font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--acc-muted,#64748b)}
.select2-results__option--highlighted .acc-opt-code{color:inherit}
.select2-selection__rendered .acc-opt-code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-weight:700;margin-right:.45rem;color:var(--acc-accent)}

/* ---- the legacy utility-class screens, in the same look ---- */
.acc-kit tr.bg-green-800,.acc-kit tr.bg-green-700,.acc-kit tr.bg-gray-800{background:var(--acc-surface-2)!important;color:var(--acc-muted)!important}
.acc-kit tr.bg-green-800 th,.acc-kit tr.bg-green-700 th,.acc-kit tr.bg-gray-800 th{border-bottom:1px solid var(--acc-border)!important;font-size:.7rem;font-weight:650;letter-spacing:.05em;color:var(--acc-muted)!important}
.acc-kit tbody.font-extrabold,.acc-kit tbody .font-extrabold{font-weight:400!important}
.acc-kit .bg-white.shadow-xl,.acc-kit .bg-white.shadow-lg,.acc-kit .bg-white.shadow-md,.acc-kit .bg-white.shadow{box-shadow:var(--acc-shadow)!important;border:1px solid var(--acc-border);border-radius:var(--acc-radius)!important}
.acc-kit .bg-blue-950,.acc-kit .bg-gray-800.text-white,.acc-kit .bg-indigo-700,.acc-kit .bg-indigo-600{background:var(--acc-accent)!important;border-color:var(--acc-accent)!important;color:var(--acc-accent-ink)!important;border-radius:.6rem!important;text-transform:none!important;letter-spacing:0!important;font-size:.82rem!important}
.acc-kit .bg-blue-950:hover,.acc-kit .bg-gray-800.text-white:hover,.acc-kit .bg-indigo-700:hover,.acc-kit .bg-indigo-600:hover{background:var(--acc-accent-hover)!important}
.acc-kit label.block.font-medium,.acc-kit label.text-sm.font-medium{font-size:.75rem;font-weight:600;color:var(--acc-muted)}
.acc-kit input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=file]):not([type=submit]):not([type=button]):not(.select2-search__field),.acc-kit select:not(.select2-hidden-accessible),.acc-kit textarea{border-color:var(--acc-border-strong);border-radius:.6rem}
.acc-kit input:focus,.acc-kit select:focus,.acc-kit textarea:focus{border-color:var(--acc-accent)!important;box-shadow:0 0 0 3px var(--acc-ring)!important;outline:none}
.acc-kit :is(a,button,input[type=submit]).uppercase.tracking-widest{text-transform:none!important;letter-spacing:0!important;font-size:.82rem!important;font-weight:600!important;border-radius:.6rem!important}
.acc-kit :is(a,button).bg-green-600,.acc-kit :is(a,button).bg-green-700,.acc-kit :is(a,button).bg-green-800{background:var(--acc-accent)!important;border-color:var(--acc-accent)!important;color:var(--acc-accent-ink)!important}
.acc-kit :is(a,button).bg-green-600:hover,.acc-kit :is(a,button).bg-green-700:hover,.acc-kit :is(a,button).bg-green-800:hover{background:var(--acc-accent-hover)!important}
/* a field the browser will not let you leave empty gets a red star on its label */
.acc-kit div:has(>:is(input,select,textarea)[required])>label::after,.acc-kit .acc-field:has(:is(input,select,textarea)[required])>label::after{content:" *";color:var(--acc-bad);font-weight:700}
.acc-kit :is(a,button).bg-red-600,.acc-kit :is(a,button).bg-red-700{background:var(--acc-bad)!important;border-color:var(--acc-bad)!important;color:#fff!important}
.acc-kit :is(a,button).bg-orange-600,.acc-kit :is(a,button).bg-orange-500{background:var(--acc-warn)!important;border-color:var(--acc-warn)!important;color:#fff!important}
.acc-kit :is(a,button).bg-purple-600,.acc-kit :is(a,button).bg-purple-700{background:var(--acc-accent)!important;border-color:var(--acc-accent)!important;color:var(--acc-accent-ink)!important}
/* ---- legacy tables: spacing and header look at zero specificity, so any utility class on a cell still wins ---- */
:where(.acc-kit) table:where(:not(.acc-table)) th,:where(.acc-kit) table:where(:not(.acc-table)) td{padding:.6rem .9rem;vertical-align:middle}
:where(.acc-kit) table:where(:not(.acc-table)) th{text-align:left;font-weight:650}
:where(.acc-kit) table:where(:not(.acc-table)) thead th{font-size:.7rem;letter-spacing:.05em;text-transform:uppercase;color:var(--acc-muted);background:var(--acc-surface-2);border-bottom:1px solid var(--acc-border);white-space:nowrap}
:where(.acc-kit) table:where(:not(.acc-table)) tbody tr:hover td{background:var(--acc-hover)}
:where(.acc-kit) table:where(:not(.acc-table)) tbody tr+tr td{border-top:1px solid var(--acc-border)}
.acc-kit table th.text-right,.acc-kit table td.text-right{text-align:right}

/* labels of the older forms read like the new ones */
.acc-kit label>span.text-gray-700:first-child,.acc-kit label>span.text-gray-600:first-child{font-size:.75rem;font-weight:600;color:var(--acc-muted);letter-spacing:.01em}

/* ---- the ledger-style reports (general ledger, books, account balances) ---- */
@media screen{
.acc-kit .report-table{border:0;border-collapse:separate;border-spacing:0;font-size:.84rem}
.acc-kit .report-table th,.acc-kit .report-table td{border:0;border-bottom:1px solid var(--acc-border);padding:.6rem .8rem}
.acc-kit .report-table th{background:var(--acc-surface-2);color:var(--acc-muted);font-size:.7rem;letter-spacing:.05em}
.acc-kit .report-table tbody tr:nth-child(even){background:transparent}
.acc-kit .report-table tbody tr:hover{background:var(--acc-hover)}
.acc-kit .report-table tfoot td{background:var(--acc-surface-2);color:var(--acc-text);border-top:1px solid var(--acc-border-strong);font-weight:650}
}

/* ---- help centre ---- */
.acc-kit .acc-help-search{position:relative;max-width:34rem;margin-bottom:1.25rem}
.acc-kit .acc-help-search svg{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:var(--acc-faint)}
.acc-kit .acc-help-search .acc-input{padding-left:2.5rem}
.acc-kit .acc-help-h{margin:1.4rem 0 .6rem;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--acc-muted)}
.acc-kit .acc-help-grid{display:grid;gap:.75rem;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr))}
.acc-kit .acc-help-card{display:flex;flex-direction:column;gap:.25rem;padding:.9rem 1rem;background:var(--acc-surface);border:1px solid var(--acc-border);border-radius:var(--acc-radius);box-shadow:var(--acc-shadow);text-decoration:none;color:var(--acc-text);transition:border-color .12s,transform .12s}
.acc-kit .acc-help-card:hover{border-color:var(--acc-accent);transform:translateY(-1px)}
.acc-kit .acc-help-card strong{font-size:.92rem}
.acc-kit .acc-help-card span{font-size:.8rem;color:var(--acc-muted);line-height:1.35}
.acc-kit .acc-help-layout{display:grid;gap:1.25rem;grid-template-columns:15rem minmax(0,1fr);align-items:start;margin-top:1rem}
@media (max-width:860px){.acc-kit .acc-help-layout{grid-template-columns:1fr}}
.acc-kit .acc-help-nav{position:sticky;top:1rem;max-height:calc(100vh - 2rem);overflow:auto;font-size:.84rem}
.acc-kit .acc-help-nav summary{cursor:pointer;padding:.45rem .5rem;font-weight:650;color:var(--acc-text);list-style:none}
.acc-kit .acc-help-nav summary::-webkit-details-marker{display:none}
.acc-kit .acc-help-nav a{display:block;padding:.3rem .5rem .3rem 1rem;border-radius:.45rem;color:var(--acc-muted);text-decoration:none}
.acc-kit .acc-help-nav a:hover{background:var(--acc-hover);color:var(--acc-text)}
.acc-kit .acc-help-nav a[aria-current]{background:var(--acc-accent-soft);color:var(--acc-accent);font-weight:650}
.acc-kit .acc-prose{max-width:46rem;font-size:.93rem;line-height:1.65;color:var(--acc-text)}
.acc-kit .acc-prose h2{margin:1.6rem 0 .5rem;font-size:1.05rem;font-weight:700}
.acc-kit .acc-prose h2:first-child{margin-top:0}
.acc-kit .acc-prose h3{margin:1.1rem 0 .35rem;font-size:.95rem;font-weight:650}
.acc-kit .acc-prose p{margin:.5rem 0}
.acc-kit .acc-prose ul{margin:.5rem 0;padding-left:1.25rem;list-style:disc}
.acc-kit .acc-prose ol{margin:.5rem 0;padding-left:1.35rem;list-style:decimal}
.acc-kit .acc-prose li{margin:.25rem 0}
.acc-kit .acc-prose a{color:var(--acc-accent);font-weight:600;text-decoration:none}
.acc-kit .acc-prose a:hover{text-decoration:underline}
.acc-kit .acc-prose code{padding:.1rem .35rem;border-radius:.35rem;background:var(--acc-hover);font-size:.85em}
.acc-kit .acc-prose blockquote{margin:.8rem 0;padding:.6rem .9rem;border-left:3px solid var(--acc-accent);background:var(--acc-accent-soft);border-radius:0 .5rem .5rem 0}
.acc-kit .acc-prose blockquote p{margin:.2rem 0}
.acc-kit .acc-prose table{width:100%;margin:.8rem 0;font-size:.86rem;border-collapse:collapse}
.acc-kit .acc-prose th,.acc-kit .acc-prose td{padding:.4rem .6rem;border-bottom:1px solid var(--acc-border);text-align:left}
.acc-kit .acc-prose kbd{padding:.05rem .4rem;border:1px solid var(--acc-border-strong);border-radius:.3rem;background:var(--acc-surface-2);font-size:.8em}

/* ---- the "More" menu of a screen's header ---- */
.acc-kit .acc-more{position:relative}
.acc-kit .acc-more>summary{list-style:none;cursor:pointer}
.acc-kit .acc-more>summary::-webkit-details-marker{display:none}
.acc-kit .acc-more>summary svg{width:.85rem;height:.85rem;transition:transform .15s}
.acc-kit .acc-more[open]>summary svg{transform:rotate(180deg)}
.acc-kit .acc-more-panel{position:absolute;right:0;top:calc(100% + .35rem);z-index:40;min-width:13rem;padding:.35rem;background:var(--acc-surface);border:1px solid var(--acc-border);border-radius:.7rem;box-shadow:0 14px 36px rgba(15,23,42,.18)}
.acc-kit .acc-more-panel a,.acc-kit .acc-more-panel button{display:block;width:100%;padding:.5rem .7rem;border:0;border-radius:.45rem;background:transparent;text-align:left;font-size:.85rem;font-weight:500;color:var(--acc-text);text-decoration:none;cursor:pointer}
.acc-kit .acc-more-panel a:hover,.acc-kit .acc-more-panel button:hover{background:var(--acc-hover)}
.acc-kit .acc-more-panel hr{margin:.3rem 0;border:0;border-top:1px solid var(--acc-border)}
.acc-kit .acc-more-panel .is-danger{color:var(--acc-bad)}

/* ---- module cards (dashboard, settings): groups flow into columns, no empty gaps ---- */
.acc-kit .acc-masonry{columns:2 22rem;column-gap:1.5rem}
.acc-kit .acc-masonry>div{break-inside:avoid;margin-bottom:1.5rem}

/* ---- list filters (the bar above every list) ---- */
.acc-kit .acc-lf{display:flex;flex-wrap:wrap;align-items:flex-end;gap:.75rem;padding:.85rem 1rem;background:var(--acc-surface);border:1px solid var(--acc-border);border-radius:var(--acc-radius);box-shadow:var(--acc-shadow)}
.acc-kit .acc-lf .acc-field{flex:1 1 9.5rem;max-width:16rem}
.acc-kit .acc-lf .acc-field--wide{flex-basis:14rem;max-width:22rem}
.acc-kit .acc-lf-more{flex:1 1 100%;order:3}
.acc-kit .acc-lf-more summary{display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.8rem;font-weight:600;color:var(--acc-accent);list-style:none}
.acc-kit .acc-lf-more summary::-webkit-details-marker{display:none}
.acc-kit .acc-lf-more summary::before{content:"+";font-weight:700}
.acc-kit .acc-lf-more[open] summary::before{content:"–"}
.acc-kit .acc-lf-moregrid{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:.7rem;padding-top:.7rem;border-top:1px dashed var(--acc-border)}
.acc-kit .acc-lf-moregrid .acc-field{flex:1 1 9.5rem;max-width:16rem}
.acc-kit .acc-lf-actions{display:flex;gap:.4rem;align-items:center;margin-left:auto}

/* ---- payroll navigation ---- */
.acc-kit .acc-subnav{display:flex;flex-wrap:wrap;gap:.35rem;margin:.9rem 0 .25rem;padding:.3rem;background:var(--acc-hover);border-radius:.85rem}
.acc-kit .acc-subnav a{padding:.38rem .8rem;border-radius:.6rem;font-size:.82rem;font-weight:600;color:var(--acc-muted);text-decoration:none;white-space:nowrap}
.acc-kit .acc-subnav a:hover{color:var(--acc-text);background:var(--acc-surface-2)}
.acc-kit .acc-subnav a[aria-current]{background:var(--acc-surface);color:var(--acc-accent);box-shadow:var(--acc-shadow)}
.acc-kit .acc-subnav2{display:flex;flex-wrap:wrap;gap:.25rem;margin:.35rem 0 .2rem;padding:0 .25rem;border-bottom:1px solid var(--acc-border)}
.acc-kit .acc-subnav2 a{margin-bottom:-1px;padding:.45rem .8rem;border-bottom:2px solid transparent;font-size:.84rem;font-weight:600;color:var(--acc-muted);text-decoration:none}
.acc-kit .acc-subnav2 a:hover{color:var(--acc-text)}
.acc-kit .acc-subnav2 a[aria-current]{color:var(--acc-accent);border-bottom-color:var(--acc-accent)}
.acc-kit .acc-subnav-sep{width:1px;background:var(--acc-border-strong);margin:.3rem .15rem;align-self:stretch}
@media print{.acc-kit .acc-no-print,.acc-kit .acc-head-r,.acc-kit .acc-context,.acc-kit .acc-tabs,.acc-kit .acc-filters,.acc-kit .acc-lf,.acc-kit .acc-subnav,.acc-kit .acc-subnav2,.acc-kit .acc-help-nav{display:none!important}.acc-kit .acc-card{box-shadow:none}}
@media (prefers-reduced-motion:reduce){.acc-kit *{animation:none!important;transition:none!important}}
</style>
@endonce
