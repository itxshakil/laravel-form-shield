<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

/** Nobody's name is a link. SEO spam puts one there to be shown in admin emails. */
final class UrlInName implements Signal
{
    public function name(): string
    {
        return 'url_in_name';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        return preg_match('~(https?://|www\.)~i', $profile->name($submission)) === 1;
    }
}
