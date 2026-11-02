<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Support;

/**
 * The text checks shared by the `NoSpamContent` rule and the content signals,
 * so the two never disagree about what counts as a link or a blocked phrase.
 *
 * @internal
 */
final class Content
{
    /** How many links the text holds: `http://`, `https://` and `www.` addresses. */
    public static function links(string $text): int
    {
        return (int) preg_match_all('~(https?://|www\.)\S+~i', $text);
    }

    /** Whether the text holds HTML anchors or forum link markup (`<a href`, `[url`, `[link`). */
    public static function hasMarkup(string $text): bool
    {
        return preg_match('~(<a\s[^>]*href|\[url[=\]]|\[link[=\]])~i', $text) === 1;
    }

    /**
     * The first phrase found in the text, case-insensitively, as it was listed.
     *
     * @param  list<string>  $phrases
     */
    public static function blockedPhrase(string $text, array $phrases): ?string
    {
        $haystack = mb_strtolower($text);

        foreach ($phrases as $phrase) {
            $needle = mb_strtolower(trim($phrase));

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return $phrase;
            }
        }

        return null;
    }
}
