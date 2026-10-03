<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Database;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Itxshakil\FormShield\Enums\ColumnsOutcome;

/**
 * Writes the package's migrations into the app's database/migrations folder.
 *
 * @internal
 */
final class MigrationWriter
{
    public const SUBMISSIONS_TABLE = 'form_shield_submissions';

    public function __construct(private readonly Filesystem $files) {}

    /**
     * Validate the table, then write the migration adding the spam columns.
     * The name is checked before anything touches the database or disk.
     */
    public function spamColumns(string $table, ?string $directory = null): ColumnsResult
    {
        if (! self::isValidTableName($table)) {
            return new ColumnsResult(ColumnsOutcome::InvalidName, $table);
        }

        $exists = Schema::hasTable($table);

        if ($exists) {
            $existing = array_values(array_filter(
                SpamColumns::COLUMNS,
                static fn (string $column): bool => Schema::hasColumn($table, $column),
            ));

            if ($existing !== []) {
                return new ColumnsResult(ColumnsOutcome::AlreadyHasColumns, $table, true, existingColumns: $existing);
            }
        }

        $path = $this->write(
            'add_spam_columns_to_'.$table.'_table',
            __DIR__.'/../../stubs/add_spam_columns.php.stub',
            ['{{ table }}' => $table],
            $directory,
        );

        return $path === null
            ? new ColumnsResult(ColumnsOutcome::AlreadyWritten, $table, $exists)
            : new ColumnsResult(ColumnsOutcome::Written, $table, $exists, $path);
    }

    public static function isValidTableName(string $table): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) === 1;
    }

    /** The built-in submissions table. Returns null when it's already been published. */
    public function submissionsTable(?string $directory = null): ?string
    {
        return $this->write(
            'create_'.self::SUBMISSIONS_TABLE.'_table',
            __DIR__.'/../../database/migrations/create_form_shield_submissions_table.php.stub',
            [],
            $directory,
        );
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function write(string $name, string $stub, array $replacements, ?string $directory): ?string
    {
        $directory ??= database_path('migrations');

        $this->files->ensureDirectoryExists($directory);

        if ($this->files->glob($directory.'/*_'.$name.'.php') !== []) {
            return null;
        }

        $path = $directory.'/'.Carbon::now()->format('Y_m_d_His').'_'.$name.'.php';

        $this->files->put($path, strtr($this->files->get($stub), $replacements));

        return $path;
    }
}
