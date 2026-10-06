<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

/**
 * Translates a rendered HTML page phrase by phrase: a piece of visible text (or a placeholder, title or aria-label) that equals
 * a key of the dictionary exactly is replaced by its translation; anything else, including text with names or numbers in it, is
 * left alone. Scripts, styles, textareas and code are never touched.
 *
 * This is what lets every existing Blade screen follow the language switch without each view being rewritten, and it is why a
 * phrase that is not in the dictionary simply stays in English.
 */
class PhraseTranslator
{
    /**
     * @param  array<string, string>  $messages  English phrase => translation
     */
    public static function html(string $html, array $messages): string
    {
        if ($messages === [] || $html === '') {
            return $html;
        }

        $kept = [];
        $protected = preg_replace_callback('~<(script|style|textarea|pre|code)\b.*?</\1>~is', function (array $match) use (&$kept): string {
            $kept[] = $match[0];

            return '<!--accounting-keep:'.(count($kept) - 1).'-->';
        }, $html);

        if ($protected === null) {
            return $html;
        }

        $protected = preg_replace_callback('~>([^<>]+)<~', function (array $match) use ($messages): string {
            $text = $match[1];
            $plain = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
            $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

            if ($plain === '' || ! isset($messages[$plain])) {
                return $match[0];
            }

            $lead = substr($text, 0, strlen($text) - strlen(ltrim($text)));
            $trail = substr($text, strlen(rtrim($text)));

            return '>'.$lead.e($messages[$plain]).$trail.'<';
        }, $protected) ?? $protected;

        $protected = preg_replace_callback('~\b(placeholder|title|aria-label)="([^"]*)"~', function (array $match) use ($messages): string {
            $plain = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);

            return isset($messages[$plain]) ? $match[1].'="'.e($messages[$plain]).'"' : $match[0];
        }, $protected) ?? $protected;

        return (string) preg_replace_callback('~<!--accounting-keep:(\d+)-->~', fn (array $match): string => $kept[(int) $match[1]], $protected);
    }
}
