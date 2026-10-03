<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Console;

use Illuminate\Console\Command;
use Itxshakil\FormShield\Console\Concerns\InteractsWithSetup;
use Itxshakil\FormShield\Database\MigrationWriter;

/**
 * php artisan form-shield:columns inquiries
 */
final class ColumnsCommand extends Command
{
    use InteractsWithSetup;

    protected $signature = 'form-shield:columns
        {table : The table that stores your form submissions}
        {--migrate : Run the migration straight away (skipped if the table does not exist yet)}';

    protected $description = 'Create a migration that adds the spam verdict columns to an existing table';

    public function handle(MigrationWriter $writer): int
    {
        $result = $writer->spamColumns(trim($this->argument('table')));

        if (! $this->reportColumnsResult($result)) {
            return self::FAILURE;
        }

        if ($this->maybeMigrate($result->canMigrate()) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->printColumnsNextSteps($result->table);

        return self::SUCCESS;
    }
}
