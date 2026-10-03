<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Contracts;

use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

/**
 * One piece of evidence that a submission came from a bot.
 *
 * A soft signal contributes its configured weight (`weights.<name>`) when it
 * fires. Mark a signal HardSignal to have it quarantine on its own, or
 * DeferredSignal to run it last, and only while the score is still under the
 * threshold.
 */
interface Signal
{
    /** The key used in config weights, stored signal maps and the report. */
    public function name(): string;

    public function fires(Submission $submission, Profile $profile): bool;
}
