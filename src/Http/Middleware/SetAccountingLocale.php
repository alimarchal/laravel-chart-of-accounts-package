<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Middleware;

use Alimarchal\LaravelChartOfAccounts\Support\PhraseTranslator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the language of the accounting screens: ?lang=ur (remembered in the session), else the session, else
 * accounting.locale. For the Blade screens the rendered page is translated phrase by phrase and given dir="rtl" for a
 * right-to-left language; for the React screens the dictionary and direction are shared with the page, which translates in the
 * browser. The API is never translated: its messages stay as they are for the integrations that read them.
 */
class SetAccountingLocale
{
    public const SESSION_KEY = 'accounting.locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::resolve($request);
        App::setLocale($locale);

        if (class_exists(Inertia::class)) {
            Inertia::share('accountingI18n', fn (): array => self::shared($locale));
        }

        $response = $next($request);

        if ($locale === (string) config('accounting.locale', 'en') && $locale === 'en') {
            return $response;
        }

        return $this->translate($request, $response, $locale);
    }

    /**
     * @return array<string, string>
     */
    public static function locales(): array
    {
        return (array) config('accounting.locales', ['en' => 'English']);
    }

    public static function resolve(Request $request): string
    {
        $allowed = array_keys(self::locales());
        $asked = $request->query('lang');

        if (is_string($asked) && in_array($asked, $allowed, true) && $request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $asked);

            return $asked;
        }

        $remembered = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (is_string($remembered) && in_array($remembered, $allowed, true)) {
            return $remembered;
        }

        $default = (string) config('accounting.locale', 'en');

        return in_array($default, $allowed, true) ? $default : 'en';
    }

    public static function isRtl(string $locale): bool
    {
        return in_array($locale, (array) config('accounting.rtl_locales', ['ur']), true);
    }

    /**
     * The phrase dictionary of a locale (English phrase => translation): the package's, with the application's own lang/<locale>.json
     * on top, so a host can add or change phrases.
     *
     * @return array<string, string>
     */
    public static function messages(string $locale): array
    {
        if ($locale === 'en') {
            return [];
        }

        Lang::setLocale($locale);
        $loaded = app('translator')->getLoader()->load($locale, '*', '*');

        return array_filter((array) $loaded, fn ($value, $key): bool => is_string($value) && is_string($key), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array{locale: string, dir: string, locales: array<string, string>, messages: array<string, string>, switchUrl: string|null, rtlCss: string}
     */
    public static function shared(string $locale): array
    {
        $route = config('accounting.route_name_prefix', 'accounting').'.locale';

        return [
            'locale' => $locale, 'dir' => self::isRtl($locale) ? 'rtl' : 'ltr', 'locales' => self::locales(), 'messages' => self::messages($locale),
            'switchUrl' => Route::has($route) ? route($route, [], false) : null, 'rtlCss' => self::rtlCss(),
        ];
    }

    private function translate(Request $request, Response $response, string $locale): Response
    {
        $type = (string) $response->headers->get('Content-Type', '');

        if ($request->headers->has('X-Inertia') || $request->headers->has('X-Livewire') || ! str_contains($type, 'text/html') || config('accounting.ui_driver') !== 'blade') {
            return $response;
        }

        $html = (string) $response->getContent();
        $html = PhraseTranslator::html($html, self::messages($locale));

        if (self::isRtl($locale)) {
            $rtl = '<style id="accounting-rtl">'.self::rtlCss().'</style>';
            $html = (string) preg_replace('~<html\b([^>]*)>~i', '<html$1 dir="rtl">', $html, 1);
            $html = (string) preg_replace('~(<html\b[^>]*?)\s+dir="ltr"~i', '$1', $html, 1);
            $html = str_contains($html, '</head>') ? str_replace('</head>', $rtl.'</head>', $html) : $rtl.$html;
        }

        $response->setContent($html);

        return $response;
    }

    /** What flips with the direction: text alignment, the common Tailwind margins and padding, and a font that has Urdu. */
    public static function rtlCss(): string
    {
        return 'html[dir=rtl] body{font-family:"Noto Naskh Arabic","Noto Nastaliq Urdu","Segoe UI",Tahoma,sans-serif;line-height:1.7}'
            .'html[dir=rtl] .text-left{text-align:right}html[dir=rtl] .text-right{text-align:left}'
            .'html[dir=rtl] .ml-1{margin-left:0;margin-right:.25rem}html[dir=rtl] .ml-2{margin-left:0;margin-right:.5rem}html[dir=rtl] .ml-3{margin-left:0;margin-right:.75rem}'
            .'html[dir=rtl] .mr-1{margin-right:0;margin-left:.25rem}html[dir=rtl] .mr-2{margin-right:0;margin-left:.5rem}html[dir=rtl] .mr-3{margin-right:0;margin-left:.75rem}'
            .'html[dir=rtl] .pl-6{padding-left:0;padding-right:1.5rem}html[dir=rtl] .pr-2{padding-right:0;padding-left:.5rem}'
            // The sidebar of the Laravel starter kits is fixed to the left: put it on the right when the page is right to left.
            .'html[dir=rtl] [data-slot=sidebar][data-side=left]>div.fixed,html[dir=rtl] [data-slot=sidebar-container]{left:auto;right:0}'
            .'html[dir=rtl] [data-slot=sidebar][data-side=left][data-state=collapsed][data-collapsible=offcanvas]>div.fixed{left:auto;right:calc(var(--sidebar-width)*-1)}'
            .'html[dir=rtl] input[type=number],html[dir=rtl] input[type=date],html[dir=rtl] .tabular-nums{direction:ltr}';
    }
}
