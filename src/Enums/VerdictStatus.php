<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Enums;

/**
 * The four outcomes of an inspection, for `match`:
 *
 *     return match ($verdict->status) {
 *         VerdictStatus::Clean, VerdictStatus::Spam => to_route('contact.thanks'),
 *         VerdictStatus::Expired => back()->withErrors(['form' => __('form-shield::messages.expired')]),
 *         VerdictStatus::RateLimited => abort(429),
 *     };
 */
enum VerdictStatus: string
{
    case Clean = 'clean';
    case Spam = 'spam';
    case Expired = 'expired';
    case RateLimited = 'rate_limited';

    /** Whether the submission should be processed as usual (stored, side effects run). */
    public function passes(): bool
    {
        return $this === self::Clean;
    }
}
