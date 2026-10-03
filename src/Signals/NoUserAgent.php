<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

final class NoUserAgent implements Signal
{
    public function name(): string
    {
        return 'no_user_agent';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        return trim((string) $submission->userAgent()) === '';
    }
}
