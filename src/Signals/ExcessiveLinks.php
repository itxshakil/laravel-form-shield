<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Support\Content;

/**
 * More links than `content.max_links` allows. Off unless `content.signals` is
 * true: it is for forms that quarantine silently, where the `NoSpamContent`
 * rule's visible error is not an option.
 */
final class ExcessiveLinks implements Signal
{
    public function name(): string
    {
        return 'excessive_links';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        return $profile->bool('content.signals')
            && Content::links($profile->message($submission)) > $profile->int('content.max_links', 2);
    }
}
