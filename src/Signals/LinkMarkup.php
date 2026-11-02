<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Support\Content;

/**
 * HTML anchors or forum link markup in the message. People type plain
 * addresses; markup is what a bot pastes. Off unless `content.signals` is true.
 */
final class LinkMarkup implements Signal
{
    public function name(): string
    {
        return 'link_markup';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        return $profile->bool('content.signals') && Content::hasMarkup($profile->message($submission));
    }
}
