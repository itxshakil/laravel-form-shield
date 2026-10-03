<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Contracts;

use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

/**
 * Lets a trusted sender skip soft scoring. Runs after the hard signals, never
 * before them.
 */
interface SenderExemption
{
    /** A short marker stored in the signal map with weight 0, e.g. "known_sender". */
    public function name(): string;

    public function exempts(Submission $submission, Profile $profile): bool;
}
