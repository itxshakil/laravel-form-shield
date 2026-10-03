<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched when a person labels a stored submission as spam or ham through
 * HasSpamVerdict::markAsSpam() / markAsHam().
 */
final class SubmissionReviewed
{
    public function __construct(
        public readonly Model $submission,
        public readonly bool $isSpam,
        public readonly bool $wasSpam,
        public readonly ?string $reviewedBy,
    ) {}

    /** The label overturned the effective verdict that was stored before. */
    public function changedVerdict(): bool
    {
        return $this->isSpam !== $this->wasSpam;
    }
}
