<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use App\View\Components\AppLayout;
use Flux\Flux;
use Illuminate\Support\Facades\View;
use Laravel\Jetstream\Jetstream;

/**
 * Which application shell the Blade screens are rendered in, so they can look native there:
 *
 *   flux      the Livewire starter kit (Flux sidebar / header)
 *   jetstream Laravel Jetstream (Livewire stack)
 *   breeze    Laravel Breeze (Blade)
 *   plain     none of them: the package's own page layout
 *
 * accounting.blade_kit (ACCOUNTING_BLADE_KIT) forces one; "auto" looks at what the application has.
 */
final class BladeKit
{
    public const KITS = ['flux', 'jetstream', 'breeze', 'plain'];

    /** @var array<string, bool> */
    private static array $stacks = [];

    /** The layout component of the host application, or null for the package's own layout. */
    public static function layout(): ?string
    {
        $configured = config('accounting.blade_layout');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (class_exists(AppLayout::class) || View::exists('components.app-layout')) {
            return 'app-layout';
        }

        $livewire = (string) config('livewire.component_layout', '');

        if ($livewire !== '' && view()->exists($livewire)) {
            return str_starts_with($livewire, 'components.') ? substr($livewire, 11) : $livewire;
        }

        return null;
    }

    public static function kit(): string
    {
        $forced = strtolower((string) config('accounting.blade_kit', 'auto'));

        if (in_array($forced, self::KITS, true)) {
            return $forced;
        }

        if (self::layout() === null) {
            return 'plain';
        }

        if (class_exists(Jetstream::class)) {
            return 'jetstream';
        }

        if (class_exists(Flux::class)) {
            return 'flux';
        }

        return self::layout() === 'app-layout' ? 'breeze' : 'plain';
    }

    /** Does the application's own layout already print @stack('name')? Then the package must not print it a second time. */
    public static function hostRenders(string $stack): bool
    {
        $layout = self::layout();

        if ($layout === null) {
            return false;
        }

        return self::$stacks[$layout.'|'.$stack] ??= self::scan($layout, $stack);
    }

    public static function flush(): void
    {
        self::$stacks = [];
    }

    private static function scan(string $layout, string $stack): bool
    {
        $names = ['layouts.app', 'components.layouts.app', 'components.app-layout', $layout, $layout.'.sidebar', $layout.'.header'];

        foreach (array_unique($names) as $name) {
            if (! View::exists($name)) {
                continue;
            }

            $path = View::getFinder()->find($name);
            $source = is_file($path) ? (string) file_get_contents($path) : '';

            if (preg_match('/@stack\(\s*[\'"]'.preg_quote($stack, '/').'[\'"]\s*\)/', $source) === 1) {
                return true;
            }
        }

        return false;
    }
}
