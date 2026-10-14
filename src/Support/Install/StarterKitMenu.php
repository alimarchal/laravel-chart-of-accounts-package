<?php

namespace Alimarchal\LaravelChartOfAccounts\Support\Install;

/**
 * Adds the accounting menu to the navigation of Laravel's starter kits, next to Dashboard: the Livewire kit (Flux sidebar or header,
 * Blade), Jetstream and Breeze (top navigation) and the React kit (app-sidebar.tsx / app-header.tsx, Inertia). It is ONE entry that opens
 * into sections (see AccountingMenu), drawn by a component of the package, so the host file holds a single line and new screens never
 * need the host file to change. Each file is changed once; an older install that has the two separate links ("Accounting" and
 * "Accounting settings") is moved to the new menu. A file that does not look like the kit's is left alone and reported.
 */
final class StarterKitMenu
{
    /** The comment lines of the earlier two-link install. */
    public const MARKER = 'accounting:menu';

    public const SETTINGS_MARKER = 'accounting:menu-settings';

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
                    $results[] = $this->react($basePath, $file);
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

        if (str_contains($source, 'accounting::menu.')) {
            return ['file' => $file, 'status' => 'present', 'message' => 'already has the Accounting menu'];
        }

        $migrated = false;
        $cleaned = (string) preg_replace('/^([ \t]*)\{\{-- accounting:menu(?:-settings)? --\}\}\R(?:.*?\R)?\1@endif\R?/ms', '', $source, -1, $removed);

        if ($removed > 0) {
            $source = $cleaned;
            $migrated = true;
        }

        // Every Dashboard item of the kit (the header layout has one in the top bar and one in the mobile menu; Jetstream and Breeze one in the
        // top bar and one in the mobile menu too): <flux:sidebar.item|navlist.item|navbar.item …> or <x-nav-link|x-responsive-nav-link …>
        // with route('dashboard') inside, up to the matching closing tag.
        $pattern = '/^([ \t]*)(<(flux:sidebar\.item|flux:navlist\.item|flux:navbar\.item|x-nav-link|x-responsive-nav-link)\b(?:(?!<\/\3>).)*?route\(\s*[\'"]dashboard[\'"]\s*\)(?:(?!<\/\3>).)*?<\/\3>)/ms';

        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return ['file' => $file, 'status' => 'manual', 'message' => 'no Dashboard item found to put the menu next to'];
        }

        $updated = $source;

        foreach (array_reverse($matches) as $match) {
            [$whole, $at] = $match[0];
            $indent = $match[1][0];
            $tag = $match[3][0];
            $component = match ($tag) {
                'flux:navbar.item' => '<x-accounting::menu.dropdown kit="flux" />',
                'x-nav-link' => '<x-accounting::menu.dropdown kit="jetstream" />',
                'x-responsive-nav-link' => '<x-accounting::menu.responsive />',
                'flux:sidebar.item' => '<x-accounting::menu.sidebar variant="sidebar" />',
                default => '<x-accounting::menu.sidebar />',
            };
            $end = $at + strlen($whole);

            // In a sidebar the menu goes after the group that holds Dashboard (groups cannot be nested), when the group closes right after it.
            if (str_starts_with($component, '<x-accounting::menu.sidebar') && preg_match('/\G(?:(?!<flux:(?:navlist|sidebar)\.group\b).)*?(?:^|\n)([ \t]*)<\/flux:(?:navlist|sidebar)\.group>/s', $updated, $close, 0, $end) === 1) {
                $end += strlen($close[0]);
                $indent = $close[1];
            }

            $block = $indent.'@if (\Illuminate\Support\Facades\Route::has(\''.$routeName.'\'))'."\n".$indent.'    '.$component."\n".$indent.'@endif';
            $updated = substr($updated, 0, $end)."\n".$block.substr($updated, $end);
        }

        file_put_contents($path, $updated);

        return ['file' => $file, 'status' => 'added', 'message' => $migrated ? 'the two Accounting links were replaced by the Accounting menu' : 'Accounting menu added next to Dashboard'];
    }

    /**
     * @return array{file: string, status: 'added'|'present'|'manual', message: string}
     */
    private function react(string $basePath, string $file): array
    {
        $path = $basePath.'/'.$file;
        $source = (string) file_get_contents($path);

        if (str_contains($source, 'AccountingNav') || str_contains($source, 'AccountingHeaderMenu')) {
            return ['file' => $file, 'status' => 'present', 'message' => 'already has the Accounting menu'];
        }

        $migrated = false;
        $without = (string) preg_replace('/^[ \t]*\{[^\n]*title:\s*[\'"]Accounting(?: settings)?[\'"][^\n]*\},?[ \t]*\/\/ accounting:menu(?:-settings)?[ \t]*\R/m', '', $source, -1, $removed);

        if ($removed > 0) {
            $source = $without;
            $migrated = true;

            foreach (['Calculator', 'Settings2'] as $icon) {
                if (preg_match_all('/\b'.$icon.'\b/', $source) === 1) {
                    $source = $this->withoutLucideIcon($source, $icon);
                }
            }
        }

        $import = "import { %s } from '@/components/accounting/accounting-nav';\n";

        if (str_contains($file, 'app-sidebar') && preg_match('/^([ \t]*)<NavMain\b[^>]*\/>/m', $source, $nav) === 1) {
            $source = str_replace($nav[0], $nav[0]."\n".$nav[1].'<AccountingNav />', $source);
            $source = $this->withImport($source, sprintf($import, 'AccountingNav'));
        } elseif (str_contains($file, 'app-header')) {
            $names = [];
            $offset = 0;
            $found = [];

            while (($at = strpos($source, '{mainNavItems.map(', $offset)) !== false) {
                $end = $this->closingBrace($source, $at);

                if ($end === null) {
                    break;
                }

                $found[] = [$at, $end];
                $offset = $end + 1;
            }

            // The first list is the mobile sheet, the second the desktop navigation (a kit with one list gets the desktop menu).
            foreach (array_reverse($found, true) as $index => [$at, $end]) {
                $lineStart = (int) strrpos(substr($source, 0, $at), "\n") + 1;
                $indent = substr($source, $lineStart, $at - $lineStart);
                $isDesktop = $index === count($found) - 1;
                $tag = $isDesktop ? '<AccountingHeaderMenu />' : '<AccountingMobileMenu />';
                $wrapped = $isDesktop ? "\n".$indent.'<NavigationMenuItem className="relative flex h-full items-center">'.$tag.'</NavigationMenuItem>' : "\n".$indent.$tag;
                $source = substr($source, 0, $end + 1).$wrapped.substr($source, $end + 1);
                $names[$isDesktop ? 'AccountingHeaderMenu' : 'AccountingMobileMenu'] = true;
            }

            if ($names === []) {
                return ['file' => $file, 'status' => 'manual', 'message' => 'no mainNavItems list found'];
            }

            $source = $this->withImport($source, sprintf($import, implode(', ', array_keys($names))));
        } else {
            return ['file' => $file, 'status' => 'manual', 'message' => 'no NavMain found in the sidebar'];
        }

        file_put_contents($path, $source);

        return ['file' => $file, 'status' => 'added', 'message' => $migrated ? 'the two Accounting links were replaced by the Accounting menu' : 'Accounting menu added'];
    }

    /** The position of the "}" that closes the JSX expression opened by the "{" at $open. */
    private function closingBrace(string $source, int $open): ?int
    {
        $depth = 0;
        $length = strlen($source);

        for ($i = $open; $i < $length; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /** Add an import line after the last import of a file. */
    private function withImport(string $source, string $line): string
    {
        if (preg_match_all('/^import [^;]*;\R/ms', $source, $imports, PREG_OFFSET_CAPTURE) < 1) {
            return $line.$source;
        }

        [$last, $at] = end($imports[0]);

        return substr($source, 0, $at + strlen($last)).$line.substr($source, $at + strlen($last));
    }

    private function withoutLucideIcon(string $source, string $icon): string
    {
        if (preg_match('/import\s*\{([^}]*)\}\s*from\s*[\'"]lucide-react[\'"];?/s', $source, $icons) !== 1) {
            return $source;
        }

        $members = array_values(array_filter(array_map('trim', explode(',', $icons[1])), fn (string $name) => $name !== '' && $name !== $icon));
        $list = str_contains($icons[1], "\n") ? "\n    ".implode(",\n    ", $members).",\n" : ' '.implode(', ', $members).' ';

        return str_replace($icons[0], 'import {'.$list.'} from \'lucide-react\';', $source);
    }
}
