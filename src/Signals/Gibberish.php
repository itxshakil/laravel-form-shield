<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

/**
 * Randomly generated words in the name or message, e.g. "xKqLmPzRw" or
 * "dfghjkls".
 *
 * Only ASCII letter runs of 8+ characters are judged, and a run is gibberish
 * when it has a 6+ consonant cluster, or flips case 4+ times. Real words and
 * names rarely do either: "strengths" has a 5-consonant cluster, "McDonald"
 * flips twice.
 */
final class Gibberish implements Signal
{
    private const MIN_LENGTH = 8;

    private const MAX_CONSONANT_RUN = 5;

    private const MAX_CASE_FLIPS = 3;

    public function name(): string
    {
        return 'gibberish';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        $text = $profile->name($submission).' '.$profile->message($submission);

        if (preg_match_all('/[A-Za-z]{'.self::MIN_LENGTH.',}/', $text, $matches) === false) {
            return false;
        }

        foreach ($matches[0] as $word) {
            if ($this->isGibberish($word)) {
                return true;
            }
        }

        return false;
    }

    public function isGibberish(string $word): bool
    {
        if (preg_match('/[bcdfghjklmnpqrstvwxz]{'.(self::MAX_CONSONANT_RUN + 1).',}/i', $word) === 1) {
            return true;
        }

        $flips = 0;
        $length = strlen($word);

        for ($i = 1; $i < $length; $i++) {
            if (ctype_upper($word[$i]) !== ctype_upper($word[$i - 1])) {
                $flips++;
            }
        }

        return $flips > self::MAX_CASE_FLIPS;
    }
}
