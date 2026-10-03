<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Events;

use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Verdict;

/** Dispatched after every inspection, whatever the outcome. */
final class SubmissionInspected
{
    public function __construct(
        public readonly Verdict $verdict,
        public readonly Submission $submission,
    ) {}
}
