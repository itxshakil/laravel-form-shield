<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Support\Content;

/**
 * Any phrase from `content.phrases` in the message. Off unless
 * `content.signals` is true. Keep the list to multi-word phrases.
 */
final class BlockedPhrase implements Signal
{
    public function name(): string
    {
        return 'blocked_phrase';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        return $profile->bool('content.signals')
            && Content::blockedPhrase($profile->message($submission), $profile->strings('content.phrases')) !== null;
    }
}
