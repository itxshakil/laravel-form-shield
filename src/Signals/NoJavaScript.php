<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

/**
 * The shield script copies the token into the JS field on the visitor's first
 * interaction with the form. A client that posts without running scripts, or
 * without focusing or typing, leaves the field empty.
 *
 * It's soft on purpose. Real people browse with scripts blocked.
 */
final class NoJavaScript implements Signal
{
    public function name(): string
    {
        return 'no_js';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        $marker = $submission->string($profile->field('js'));

        return $marker === '' || $marker !== $submission->string($profile->field('timestamp'));
    }
}
