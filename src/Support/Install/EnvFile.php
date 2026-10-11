<?php

namespace Alimarchal\LaravelChartOfAccounts\Support\Install;

/**
 * Reads and writes the application's .env for the installer: a key that is already set (an uncommented line) is changed in place, a
 * commented-out or missing key is appended, and a key written twice ends up with one line. Nothing else in the file is touched.
 */
final class EnvFile
{
    public function __construct(private readonly string $path) {}

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        $value = null;

        foreach ($this->lines() as $line) {
            if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=(.*)$/', $line, $match) === 1) {
                $value = self::unquote($match[1]);
            }
        }

        return $value;
    }

    /**
     * @param  array<string, string|int|bool|null>  $values  key => value (a bool is written as true/false)
     * @return list<string> the keys that were written
     */
    public function set(array $values): array
    {
        if (! $this->exists()) {
            return [];
        }

        $lines = $this->lines();
        $written = [];

        foreach ($values as $key => $value) {
            $text = $key.'='.self::format($value);
            $positions = [];

            foreach ($lines as $index => $line) {
                if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=/', $line) === 1) {
                    $positions[] = $index;
                }
            }

            if ($positions === []) {
                $lines[] = $text;
            } else {
                $lines[$positions[0]] = $text;

                foreach (array_slice($positions, 1) as $duplicate) {
                    unset($lines[$duplicate]);                        // a key written twice: the later line used to win, now there is one
                }
            }

            $written[] = $key;
        }

        file_put_contents($this->path, rtrim(implode("\n", array_values($lines)), "\n")."\n");

        return $written;
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        return explode("\n", rtrim(str_replace("\r\n", "\n", (string) file_get_contents($this->path)), "\n"));
    }

    private static function format(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        return $value === '' || preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value) === 1 ? $value : '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    private static function unquote(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^"(.*)"/', $value, $match) === 1) {
            return str_replace(['\\"', '\\\\', '\\$'], ['"', '\\', '$'], $match[1]);
        }

        if (preg_match("/^'(.*)'/", $value, $match) === 1) {
            return $match[1];
        }

        return trim((string) preg_replace('/\s+#.*$/', '', $value));
    }
}
