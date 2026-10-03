<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Database;

use Itxshakil\FormShield\Enums\ColumnsOutcome;

/** @internal */
final class ColumnsResult
{
    /**
     * @param  list<string>  $existingColumns
     */
    public function __construct(
        public readonly ColumnsOutcome $outcome,
        public readonly string $table,
        public readonly bool $tableExists = false,
        public readonly ?string $path = null,
        public readonly array $existingColumns = [],
    ) {}

    /** The migration can run now: it exists and so does its table. */
    public function canMigrate(): bool
    {
        return ! $this->outcome->failed() && $this->tableExists;
    }
}
