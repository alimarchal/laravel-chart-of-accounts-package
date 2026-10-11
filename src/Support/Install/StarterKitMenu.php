<?php

namespace Alimarchal\LaravelChartOfAccounts\Support\Install;

/**
 * Adds the "Accounting" entry to the navigation of Laravel's starter kits, next to Dashboard: the Livewire kit (Flux sidebar or header,
 * Blade) and the React kit (app-sidebar.tsx / app-header.tsx, Inertia). Each file is changed once (a marker keeps it idempotent); a file
 * that does not look like the kit's is left alone and reported, so the installer can print the lines to add by hand.
 */
final class StarterKitMenu
{
    public const MARKER = 'accounting:menu';

    /**
     * @return list<array{file: string, status: 'added'|'present'|'manual', message: string}>
     */
    public function apply(string $driver, string $basePath, string $url, string $routeName): array
    {
        $results = [];

        if ($driver === 'blade') {
            foreach (['resources/views/layouts/app/sidebar.blade.php', 'resources/views/components/layouts/app/sidebar.blade.php', 'resources/views/layouts/app/header.blade.php', 'resources/views/components/layouts/app/header.blade.php', 'resources/views/navigation-menu.blade.php', 'resources/views/layouts/navigation.blade.php'] as $file) {
                if (is_file($basePath.'/'.$file)) {
                    $results[] = $this->livewire($basePath, $file, $routeName);
                }
            }
        }

        if ($driver === 'inertia') {
            foreach (['resources/js/components/app-sidebar.tsx', 'resources/js/components/app-header.tsx'] as $file) {
                if (is_file($basePath.'/'.$file)) {
                    $results[] = $this->react($basePath, $file, $url);
                }
            }
        }

        return $results;
    }

    /**
     * Tailwind 4 only generates the classes it finds in the sources it is told about: add the package's Blade screens to resources/css/app.css.
     *
     * @return 'added'|'present'|'none'
     */
    public function tailwindSource(string $basePath): string
    {
        $path = $basePath.'/resources/css/app.css';

        if (! is_file($path)) {
            return 'none';
        }

        $css = (string) file_get_contents($path);
        $source = "@source '../../vendor/alimarchal/laravel-chart-of-accounts/resources/views';";

        if (str_contains($css, 'alimarchal/laravel-chart-of-accounts')) {
            return 'present';
        }

        if (preg_match('/@import\s+[\'"]tailwindcss[\'"][^;]*;/', $css) !== 1) {
            return $this->tailwindThree($basePath);                 // Tailwind 3 (Jetstream, Breeze): the content list of tailwind.config.js
        }

        $lines = explode("\n", $css);
        $at = null;

        foreach ($lines as $index => $line) {
            if (str_starts_with(trim($line), '@source') || preg_match('/^\s*@import\s+[\'"]tailwindcss[\'"]/', $line) === 1) {
                $at = $index;
            }
        }

        array_splice($lines, ($at ?? 0) + 1, 0, [$source]);
        file_put_contents($path, implode("\n", $lines));

        return 'added';
    }

    /** @return 'added'|'present'|'none' */
    private function tailwindThree(string $basePath): string
    {
        foreach (['tailwind.config.js', 'tailwind.config.mjs', 'tailwind.config.cjs', 'tailwind.config.ts'] as $name) {
            $path = $basePath.'/'.$name;

            if (! is_file($path)) {
                continue;
            }

            $config = (string) file_get_contents($path);

            if (str_contains($config, 'alimarchal/laravel-chart-of-accounts')) {
                return 'present';
            }

            $updated = preg_replace('/(content\s*:\s*\[)(\s*)/', "$1$2'./vendor/alimarchal/laravel-chart-of-accounts/resources/views/**/*.blade.php',$2", $config, 1, $count);

            if ($count !== 1 || $updated === null) {
                return 'none';
            }

            file_put_contents($path, $updated);

            return 'added';
        }

        return 'none';
    }

    /**
     * @return array{file: string, status: 'added'|'present'|'manual', message: string}
     */
    private function livewire(string $basePath, string $file, string $routeName): array
    {
        $path = $basePath.'/'.$file;
        $source = (string) file_get_contents($path);

        if (str_contains($source, self::MARKER) || str_contains($source, $routeName)) {
            return ['file' => $file, 'status' => 'present', 'message' => 'already has the Accounting link'];
        }

        // Every Dashboard item of the kit (the header layout has one in the top bar and one in the mobile menu; Jetstream and Breeze one in the
        // top bar and one in the mobile menu too): <flux:sidebar.item|navlist.item|navbar.item …> or <x-nav-link|x-responsive-nav-link …>
        // with route('dashboard') inside, up to the matching closing tag.
        $pattern = '/^([ \t]*)(<(flux:sidebar\.item|flux:navlist\.item|flux:navbar\.item|x-nav-link|x-responsive-nav-link)\b(?:(?!<\/\3>).)*?route\(\s*[\'"]dashboard[\'"]\s*\)(?:(?!<\/\3>).)*?<\/\3>)/ms';

        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return ['file' => $file, 'status' => 'manual', 'message' => 'no Dashboard item found to copy'];
        }

        $updated = $source;

        foreach (array_reverse($matches) as $match) {
            [$whole, $at] = $match[0];
            $indent = $match[1][0];
            $item = $match[2][0];
            $item = (string) preg_replace('/\s+wire:navigate(?:\.hover)?\b/', '', $item);                   // the accounting pages are ordinary pages
            $item = str_replace(["route('dashboard')", 'route("dashboard")'], "route('{$routeName}')", $item);
            $item = (string) preg_replace('/routeIs\(\s*[\'"]dashboard[\'"]\s*\)/', "routeIs('".preg_replace('/\.dashboard$/', '.*', $routeName)."')", $item);
            $item = (string) preg_replace('/\bicon="[^"]*"/', 'icon="calculator"', $item);
            $item = (string) preg_replace('/__\(\s*[\'"]Dashboard[\'"]\s*\)/', "__('Accounting')", $item);
            $item = (string) preg_replace('/>\s*Dashboard\s*</', '>Accounting<', $item);

            $lines = explode("\n", $item);
            $nested = array_map(fn (string $line, int $index): string => $index === 0 ? $line : ($line === '' ? $line : '    '.$line), $lines, array_keys($lines));

            $block = $indent.'{{-- '.self::MARKER.' --}}'."\n".$indent.'@if (\Illuminate\Support\Facades\Route::has(\''.$routeName.'\'))'."\n"
                .$indent.'    '.ltrim(implode("\n", $nested))."\n".$indent.'@endif';

            $updated = substr_replace($updated, $whole."\n".$block, $at, strlen($whole));
        }

        file_put_contents($path, $updated);

        return ['file' => $file, 'status' => 'added', 'message' => 'Accounting link added next to Dashboard'];
    }

    /**
     * @return array{file: string, status: 'added'|'present'|'manual', message: string}
     */
    private function react(string $basePath, string $file, string $url): array
    {
        $path = $basePath.'/'.$file;
        $source = (string) file_get_contents($path);

        if (str_contains($source, self::MARKER) || preg_match('/title:\s*[\'"]Accounting[\'"]/', $source) === 1) {
            return ['file' => $file, 'status' => 'present', 'message' => 'already has the Accounting link'];
        }

        if (preg_match('/(const\s+mainNavItems(?:\s*:\s*NavItem\[\])?\s*=\s*\[)(.*?)(\n[ \t]*\];)/s', $source, $array) !== 1
            || preg_match('/import\s*\{([^}]*)\}\s*from\s*[\'"]lucide-react[\'"];?/s', $source, $icons) !== 1) {
            return ['file' => $file, 'status' => 'manual', 'message' => 'no mainNavItems list or lucide-react import found'];
        }

        $indent = preg_match('/\n([ \t]+)\{/', $array[2], $found) === 1 ? $found[1] : '    ';
        $body = rtrim($array[2]);
        $body .= ($body !== '' && ! str_ends_with($body, ',') && ! str_ends_with($body, '[') ? ',' : '')."\n".$indent."{ title: 'Accounting', href: '".$url."', icon: Calculator }, // ".self::MARKER;

        $source = str_replace($array[0], $array[1].$body.$array[3], $source);

        $members = array_values(array_filter(array_map('trim', explode(',', $icons[1])), fn (string $name) => $name !== ''));

        if (! in_array('Calculator', $members, true)) {
            $members[] = 'Calculator';
            sort($members);
            $multiline = str_contains($icons[1], "\n");
            $list = $multiline ? "\n    ".implode(",\n    ", $members).",\n" : ' '.implode(', ', $members).' ';
            $source = str_replace($icons[0], 'import {'.$list.'} from \'lucide-react\';', $source);
        }

        file_put_contents($path, $source);

        return ['file' => $file, 'status' => 'added', 'message' => 'Accounting link added to mainNavItems'];
    }
}
