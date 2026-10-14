<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The help centre: one short article for every item of the accounting menu, in plain language (what it is, when to use it, how, and what
 * to know), kept as markdown files in resources/help/{locale}/{topic}.md so they can be read, copied and translated like any other text.
 * An application may add or replace articles by putting files of the same name in resources/views/vendor/accounting/help/{locale}/.
 *
 * File format: a front-matter block with `title:` and `summary:`, then markdown. `[[topic]]` links to another article by its title.
 */
final class Help
{
    /** Articles that are not a menu item. */
    public const EXTRA = ['getting-started' => 'Start here', 'glossary' => 'Start here', 'shortcuts' => 'Start here'];

    /** @var array<string, array{slug: string, title: string, summary: string, body: string}|null> */
    private array $cache = [];

    private function directories(string $locale): array
    {
        return array_filter([
            resource_path('views/vendor/accounting/help/'.$locale),
            __DIR__.'/../../resources/help/'.$locale,
        ], 'is_dir');
    }

    private function locale(): string
    {
        return 'en';                // the help is written in English; other languages fall back to it
    }

    /** @return array{slug: string, title: string, summary: string, body: string}|null */
    public function topic(string $slug): ?array
    {
        if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
            return null;
        }

        if (array_key_exists($slug, $this->cache)) {
            return $this->cache[$slug];
        }

        foreach ($this->directories($this->locale()) as $directory) {
            $file = $directory.'/'.$slug.'.md';

            if (is_file($file)) {
                return $this->cache[$slug] = $this->parse($slug, (string) file_get_contents($file));
            }
        }

        return $this->cache[$slug] = null;
    }

    /** @return array{slug: string, title: string, summary: string, body: string} */
    private function parse(string $slug, string $source): array
    {
        $title = Str::headline($slug);
        $summary = '';
        $body = $source;

        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $source, $match) === 1) {
            $body = $match[2];

            foreach (preg_split('/\R/', $match[1]) ?: [] as $line) {
                if (preg_match('/^title:\s*(.*)$/', $line, $pair) === 1) {
                    $title = trim($pair[1]);
                } elseif (preg_match('/^summary:\s*(.*)$/', $line, $pair) === 1) {
                    $summary = trim($pair[1]);
                }
            }
        }

        return ['slug' => $slug, 'title' => $title, 'summary' => $summary, 'body' => trim($body)];
    }

    /** The article as HTML (markdown made by us, never by users; `[[topic]]` becomes a link). */
    public function html(string $slug): string
    {
        $topic = $this->topic($slug);

        if ($topic === null) {
            return '';
        }

        $body = preg_replace_callback('/\[\[([a-z0-9-]+)\]\]/', function (array $match): string {
            $linked = $this->topic($match[1]);

            return $linked === null ? $match[1] : '['.$linked['title'].']('.$this->url($match[1]).')';
        }, $topic['body']) ?? $topic['body'];

        return (string) Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function url(string $slug): string
    {
        $name = trim((string) config('accounting.route_name_prefix', 'accounting'), '.');

        foreach ([$name, 'accounting'] as $prefix) {
            if (Route::has($prefix.'.help.show')) {
                return route($prefix.'.help.show', $slug, false);
            }
        }

        return '/'.trim((string) config('accounting.route_prefix', 'accounting'), '/').'/help/'.$slug;
    }

    /**
     * What the help index shows: the "start here" articles, then one block per section of the menu the person can see, each with its
     * articles (the menu items they may open).
     *
     * @return list<array{key: string, title: string, topics: list<array{slug: string, title: string, summary: string, url: string}>}>
     */
    public function index(?Authenticatable $user): array
    {
        $blocks = [];
        $start = [];

        foreach (array_keys(self::EXTRA) as $slug) {
            if ($topic = $this->topic($slug)) {
                $start[] = $this->card($topic);
            }
        }

        $blocks[] = ['key' => 'start', 'title' => 'Start here', 'topics' => $start];

        foreach (AccountingMenu::for($user, '') as $section) {
            $topics = [];

            foreach ($section['items'] as $item) {
                if ($item['help'] !== 'help' && ($topic = $this->topic($item['help']))) {
                    $topics[] = $this->card($topic);
                }
            }

            if ($topics !== []) {
                $blocks[] = ['key' => $section['key'], 'title' => $section['area'] === 'settings' ? 'Settings · '.$section['title'] : $section['title'], 'topics' => $topics];
            }
        }

        return $blocks;
    }

    /** @param  array{slug: string, title: string, summary: string, body: string}  $topic */
    private function card(array $topic): array
    {
        return ['slug' => $topic['slug'], 'title' => $topic['title'], 'summary' => $topic['summary'], 'url' => $this->url($topic['slug'])];
    }

    /**
     * The article of the screen a person is on: its slug and url, or null when there is none.
     *
     * @return array{slug: string, title: string, url: string}|null
     */
    public function forPath(string $path): ?array
    {
        $slug = AccountingMenu::topicFor($path);

        if ($slug === null || $slug === 'help' || ($topic = $this->topic($slug)) === null) {
            return null;
        }

        return ['slug' => $slug, 'title' => $topic['title'], 'url' => $this->url($slug)];
    }
}
