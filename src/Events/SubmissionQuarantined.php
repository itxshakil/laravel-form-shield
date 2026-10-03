<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Events;

use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Verdict;

/** Dispatched when a submission is judged spam. */
final class SubmissionQuarantined
{
    public function __construct(
        public readonly Verdict $verdict,
        public readonly Submission $submission,
    ) {}
}
