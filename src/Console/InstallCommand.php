<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Console;

use Illuminate\Console\Command;
use Itxshakil\FormShield\Console\Concerns\InteractsWithSetup;
use Itxshakil\FormShield\Database\ColumnsResult;
use Itxshakil\FormShield\Database\MigrationWriter;
use Itxshakil\FormShield\Enums\StorageOption;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * php artisan form-shield:install
 *
 * Publishes the config, then sets up storage for verdicts in one of two ways:
 * columns on the app's own submissions table, or the package's built-in log.
 */
final class InstallCommand extends Command
{
    use InteractsWithSetup;

    public const STORAGE_QUESTION = 'Where should verdicts be stored? (needed for review and the precision report)';

    protected $signature = 'form-shield:install
        {--table= : Add the spam columns to this existing table}
        {--log : Publish the built-in submissions table}
        {--migrate : Run migrations at the end (skipped if a target table does not exist yet)}
        {--force : Overwrite an already-published config file}';

    protected $description = 'Publish the Form Shield config and set up where verdicts are stored';

    public function handle(MigrationWriter $writer): int
    {
        $this->publishConfig();

        [$table, $log] = $this->resolveStorage();

        $columns = null;

        if ($table !== null) {
            $columns = $writer->spamColumns($table);

            if (! $this->reportColumnsResult($columns)) {
                return self::FAILURE;
            }
        }

        $wroteLog = false;

        if ($log) {
            $path = $writer->submissionsTable();
            $wroteLog = true;

            if ($path === null) {
                $this->components->warn('The form_shield_submissions migration is already in database/migrations.');
            } else {
                $this->components->info(sprintf('Created migration [%s].', $this->relativePath($path)));
            }
        }

        if ($this->maybeMigrate($this->canMigrate($columns, $wroteLog)) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($columns instanceof ColumnsResult) {
            $this->printColumnsNextSteps($columns->table);
        }

        if ($log) {
            $this->printLogNextSteps();
        }

        $this->newLine();
        $this->components->info('Form Shield is installed.');
        $this->line('  1. Add <comment>'.OutputFormatter::escape('<x-form-shield />').'</comment> inside each public form, after @csrf.');
        $this->line('  2. Add <comment>@formShieldScripts</comment> once, at the end of your layout.');
        $this->line("  3. Inspect after validation: <comment>\$verdict = FormShield::inspect(\$request, 'contact');</comment>");
        $this->line('  Docs: https://github.com/itxshakil/laravel-form-shield/tree/main/docs');

        return self::SUCCESS;
    }

    private function publishConfig(): void
    {
        $exists = is_file(config_path('form-shield.php'));

        if ($exists && ! $this->option('force')) {
            $this->components->warn('Config [config/form-shield.php] already exists, skipped. Use --force to overwrite it.');

            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'form-shield-config', '--force' => true]);
        $this->components->info(sprintf('%s config [config/form-shield.php].', $exists ? 'Overwrote' : 'Published'));
    }

    /** @return array{0: string|null, 1: bool} */
    private function resolveStorage(): array
    {
        $table = $this->option('table');
        $table = is_string($table) && trim($table) !== '' ? trim($table) : null;
        $log = (bool) $this->option('log');

        if ($table !== null || $log) {
            return [$table, $log];
        }

        if (! $this->input->isInteractive()) {
            $this->components->warn('Verdict storage was not set up. Re-run with --table=<your_table> or --log.');

            return [null, false];
        }

        $answer = $this->choice(self::STORAGE_QUESTION, StorageOption::labels(), 0);
        $choice = StorageOption::fromLabel(is_string($answer) ? $answer : '');

        return match ($choice) {
            StorageOption::Columns => [$this->askForTable(), false],
            StorageOption::Log => [null, true],
            StorageOption::Skip => [null, false],
        };
    }

    private function askForTable(): string
    {
        $answer = $this->ask('Which table stores the submissions?', 'inquiries');

        return is_string($answer) ? trim($answer) : '';
    }

    /**
     * `migrate` runs every pending migration, so it must not run while the
     * columns migration targets a table that doesn't exist yet: it would fail,
     * and keep failing every later migrate until that table exists.
     */
    private function canMigrate(?ColumnsResult $columns, bool $wroteLog): bool
    {
        if ($columns instanceof ColumnsResult && ! $columns->tableExists) {
            return false;
        }

        return $columns instanceof ColumnsResult || $wroteLog;
    }
}
