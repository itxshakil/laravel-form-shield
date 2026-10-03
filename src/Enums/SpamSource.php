<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Enums;

/**
 * Who set a row's effective `is_spam` value.
 *
 * Only Reviewer rows count as ground truth. Detector rows are the inspector's
 * own output, and measuring the inspector against them just confirms what it
 * already believes.
 */
enum SpamSource: string
{
    case Detector = 'detector';
    case Reviewer = 'reviewer';

    public function isGroundTruth(): bool
    {
        return $this === self::Reviewer;
    }
}
