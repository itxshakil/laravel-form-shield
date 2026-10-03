<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Enums;

/**
 * The built-in values of `Verdict::$reason` and the stored `spam_reason`
 * column. A custom hard signal adds its own name as a reason, so the stored
 * value stays a string; use Reason::tryFrom() or $verdict->hasReason() to
 * compare against these.
 */
enum Reason: string
{
    /** The hidden honeypot field was filled. */
    case Honeypot = 'honeypot';

    /** The encrypted start timestamp was missing or forged. */
    case NoToken = 'no_token';

    /** Submitted faster than `min_seconds`. */
    case TooFast = 'too_fast';

    /** Soft signals reached the threshold. */
    case Score = 'score';

    /** Open longer than `max_age_seconds`. Not spam. */
    case Expired = 'expired';

    /** Over a rate cap. Not spam. */
    case RateLimited = 'rate_limited';

    /** True for the reasons that mean "the detector called this spam". */
    public function isSpam(): bool
    {
        return $this !== self::Expired && $this !== self::RateLimited;
    }

    /** True when a single hard signal decided the verdict on its own. */
    public function isHard(): bool
    {
        return $this === self::Honeypot || $this === self::NoToken || $this === self::TooFast;
    }

    /**
     * Whether a stored reason string means the detector flagged the row. Any
     * reason that isn't a built-in one is a custom hard signal, so it counts.
     */
    public static function flagsSpam(?string $reason): bool
    {
        if ($reason === null || $reason === '') {
            return false;
        }

        return self::tryFrom($reason)?->isSpam() ?? true;
    }
}
