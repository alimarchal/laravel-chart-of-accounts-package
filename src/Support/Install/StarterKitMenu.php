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
            foreach (['resources/views/layouts/app/sidebar.blade.php', 'resources/views/components/layouts/app/sidebar.blade.php', 'resources/views/layouts/app/header.blade.php', 'resources/views/components/layouts/app/header.blade.php'] as $file) {
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
            return 'none';                                          // Tailwind 3 (tailwind.config.js content) or no Tailwind: nothing to add here
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

        // The Dashboard item of the kit: <flux:sidebar.item|navlist.item|navbar.item … route('dashboard') …>…</flux:…>
        if (preg_match('/^([ \t]*)(<flux:(sidebar\.item|navlist\.item|navbar\.item)\b(?:(?!<\/flux:\3>).)*?route\(\s*[\'"]dashboard[\'"]\s*\)(?:(?!<\/flux:\3>).)*?<\/flux:\3>)/ms', $source, $match) !== 1) {
            return ['file' => $file, 'status' => 'manual', 'message' => 'no Dashboard item found to copy'];
        }

        $indent = $match[1];
        $item = $match[2];
        $item = (string) preg_replace('/\s+wire:navigate(?:\.hover)?\b/', '', $item);                       // the accounting pages are ordinary pages
        $item = str_replace(["route('dashboard')", 'route("dashboard")'], "route('{$routeName}')", $item);
        $item = (string) preg_replace('/routeIs\(\s*[\'"]dashboard[\'"]\s*\)/', "routeIs('".preg_replace('/\.dashboard$/', '.*', $routeName)."')", $item);
        $item = (string) preg_replace('/\bicon="[^"]*"/', 'icon="calculator"', $item);
        $item = (string) preg_replace('/__\(\s*[\'"]Dashboard[\'"]\s*\)/', "__('Accounting')", $item);
        $item = (string) preg_replace('/>\s*Dashboard\s*</', '>Accounting<', $item);

        $block = $indent.'{{-- '.self::MARKER.' --}}'."\n".$indent.'@if (\Illuminate\Support\Facades\Route::has(\''.$routeName.'\'))'."\n"
            .$indent.'    '.ltrim($item)."\n".$indent.'@endif';

        $at = strpos($source, $match[0]);

        if ($at === false) {
            return ['file' => $file, 'status' => 'manual', 'message' => 'could not be changed'];
        }

        $updated = substr_replace($source, $match[0]."\n".$block, $at, strlen($match[0]));

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
