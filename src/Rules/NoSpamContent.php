<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Itxshakil\FormShield\Support\Value;

/**
 * Content signals clear enough to tell the sender about. The bot signals in
 * the inspector quarantine silently; these return a validation error, so a
 * real person can reword and resend.
 *
 * Links are counted, not just detected, because real customers paste their
 * own website all the time.
 */
final class NoSpamContent implements ValidationRule
{
    private ?int $maxLinks = null;

    /** @var list<string>|null */
    private ?array $phrases = null;

    public static function make(): self
    {
        return new self;
    }

    public function maxLinks(int $maxLinks): self
    {
        $this->maxLinks = $maxLinks;

        return $this;
    }

    /** @param list<string> $phrases Replaces the configured phrase list. */
    public function phrases(array $phrases): self
    {
        $this->phrases = $phrases;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $maxLinks = $this->maxLinks ?? Value::int(config('form-shield.content.max_links'), 2);

        if (preg_match_all('~(https?://|www\.)\S+~i', $value) > $maxLinks) {
            $fail('form-shield::messages.too_many_links')->translate(['max' => $maxLinks]);

            return;
        }

        if (preg_match('~(<a\s[^>]*href|\[url[=\]]|\[link[=\]])~i', $value) === 1) {
            $fail('form-shield::messages.link_markup')->translate();

            return;
        }

        $haystack = mb_strtolower($value);

        foreach ($this->phrases ?? Value::strings(config('form-shield.content.phrases')) as $phrase) {
            $phrase = mb_strtolower(trim($phrase));

            if ($phrase !== '' && str_contains($haystack, $phrase)) {
                $fail('form-shield::messages.blocked_phrase')->translate();

                return;
            }
        }
    }
}
