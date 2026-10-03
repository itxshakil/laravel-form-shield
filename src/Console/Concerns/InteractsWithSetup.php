<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Console\Concerns;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Itxshakil\FormShield\Database\ColumnsResult;
use Itxshakil\FormShield\Enums\ColumnsOutcome;

/**
 * Output shared by form-shield:install and form-shield:columns.
 *
 * Messages that end in a path or a code sample end in their own punctuation
 * on purpose: Laravel's components->info() appends a period otherwise.
 *
 * @mixin Command
 */
trait InteractsWithSetup
{
    /** Print the outcome of writing the columns migration. Returns false when it failed. */
    protected function reportColumnsResult(ColumnsResult $result): bool
    {
        switch ($result->outcome) {
            case ColumnsOutcome::InvalidName:
                $this->components->error(sprintf('[%s] is not a valid table name. Use letters, numbers and underscores.', $result->table));

                return false;

            case ColumnsOutcome::AlreadyHasColumns:
                $this->components->error(sprintf(
                    'Table [%s] already has spam columns (%s). Nothing to do.',
                    $result->table,
                    implode(', ', $result->existingColumns),
                ));

                return false;

            case ColumnsOutcome::AlreadyWritten:
                $this->components->warn(sprintf('A migration adding spam columns to [%s] already exists in database/migrations.', $result->table));
                break;

            case ColumnsOutcome::Written:
                $this->components->info(sprintf('Created migration [%s].', $this->relativePath((string) $result->path)));
                break;
        }

        if (! $result->tableExists) {
            $this->components->warn(sprintf(
                'Table [%s] does not exist yet, so the migration was not run. Run `php artisan migrate` once the migration that creates [%s] is in place.',
                $result->table,
                $result->table,
            ));
        }

        return true;
    }

    /** Ask (or obey --migrate) and run migrations. Returns the migrate exit code, or SUCCESS when skipped. */
    protected function maybeMigrate(bool $canMigrate): int
    {
        if (! $canMigrate) {
            return self::SUCCESS;
        }

        $migrate = (bool) $this->option('migrate')
            || ($this->input->isInteractive() && $this->confirm('Run migrations now?', true));

        return $migrate ? $this->call('migrate') : self::SUCCESS;
    }

    protected function printColumnsNextSteps(string $table): void
    {
        $model = Str::studly(Str::singular($table));

        $this->newLine();
        $this->line(sprintf('  <options=bold>Next:</> add the trait to <comment>App\Models\%s</comment> (or whichever model uses the [%s] table):', $model, $table));
        $this->newLine();
        $this->line('      use Itxshakil\FormShield\Concerns\HasSpamVerdict;');
        $this->newLine();
        $this->line('  Then save each submission with its verdict:');
        $this->newLine();
        $this->line(sprintf('      %s::createWithVerdict($data, $verdict);', $model));
    }

    protected function printLogNextSteps(): void
    {
        $this->newLine();
        $this->line('  <options=bold>Next:</> record each submission after inspecting it:');
        $this->newLine();
        $this->line('      FormShield::record($request, $verdict);');
        $this->newLine();
        $this->line('  or set <comment>FORM_SHIELD_STORE=true</comment> to log every inspection automatically.');
        $this->newLine();
        $this->line('  Prune old unreviewed rows, in routes/console.php:');
        $this->newLine();
        $this->line('      Schedule::command(\'model:prune\', [');
        $this->line('          \'--model\' => [\Itxshakil\FormShield\Models\ShieldSubmission::class],');
        $this->line('      ])->daily();');
    }

    protected function relativePath(string $path): string
    {
        $base = rtrim(base_path(), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
