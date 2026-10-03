<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Enums;

/** What happened when asked to write the spam-columns migration for a table. */
enum ColumnsOutcome
{
    case Written;
    case AlreadyWritten;
    case AlreadyHasColumns;
    case InvalidName;

    public function failed(): bool
    {
        return $this === self::AlreadyHasColumns || $this === self::InvalidName;
    }
}
