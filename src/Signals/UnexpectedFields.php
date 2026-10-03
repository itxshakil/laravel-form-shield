<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

/**
 * The submission carries keys the form never rendered. Form-spamming kits
 * often post a generic field set (url, website, subject, phone...) to every
 * form they find.
 *
 * Only runs when the profile sets `allowed_fields`.
 */
final class UnexpectedFields implements Signal
{
    private const ALWAYS_ALLOWED = ['_token', '_method'];

    public function name(): string
    {
        return 'unexpected_fields';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        if (! is_array($profile->get('allowed_fields'))) {
            return false;
        }

        $allowed = [
            ...$profile->strings('allowed_fields'),
            ...self::ALWAYS_ALLOWED,
            $profile->field('honeypot'),
            $profile->field('timestamp'),
            $profile->field('js'),
        ];

        return array_diff($submission->keys(), $allowed) !== [];
    }
}
