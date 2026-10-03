<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Itxshakil\FormShield\Concerns\HasSpamVerdict;
use Itxshakil\FormShield\Enums\Reason;
use Itxshakil\FormShield\Enums\SpamSource;
use Itxshakil\FormShield\FormShield;
use Itxshakil\FormShield\Storage\SubmissionRecorder;
use Itxshakil\FormShield\Support\Value;
use Itxshakil\FormShield\Verdict;

/**
 * Precision per signal, measured against human review labels.
 *
 * Every weight and threshold starts as a guess. This command replaces the
 * guesses with counts, and it's the reason to have people label submissions
 * at all.
 *
 * Only rows a person ruled on count as ground truth. Rows the detector
 * flagged that nobody checked are its own output, and scoring against them
 * just confirms what it already believes.
 */
final class ReportCommand extends Command
{
    protected $signature = 'form-shield:report
        {model? : The Eloquent model that stores submissions (uses HasSpamVerdict). Defaults to the built-in log}
        {--days=30 : How far back to measure}
        {--profile=default : Profile whose weights are shown}';

    protected $description = 'Per-signal spam detection precision, measured against human review labels';

    /** @var array<string, int> */
    private array $fired = [];

    /** @var array<string, int> */
    private array $firedOnReviewed = [];

    /** @var array<string, int> */
    private array $firedOnHam = [];

    private int $total = 0;

    private int $quarantined = 0;

    private int $reviewed = 0;

    private int $falsePositives = 0;

    private int $missed = 0;

    public function handle(FormShield $shield): int
    {
        $argument = $this->argument('model');
        $model = is_string($argument) && $argument !== '' ? $argument : app(SubmissionRecorder::class)->modelClass();

        if (! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            $this->error(sprintf('[%s] is not an Eloquent model.', $model));

            return self::FAILURE;
        }

        if (! in_array(HasSpamVerdict::class, class_uses_recursive($model), true)) {
            $this->error(sprintf('[%s] does not use %s.', $model, HasSpamVerdict::class));

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $since = Carbon::now()->subDays($days);
        $profile = $this->option('profile');
        $weights = $shield->profile(is_string($profile) && $profile !== '' ? $profile : 'default')->weights();

        /** @var Model $instance */
        $instance = new $model;

        if (! Schema::connection($instance->getConnectionName())->hasTable($instance->getTable())) {
            $this->error(sprintf(
                'Table [%s] does not exist. Pass the model that stores your submissions, or run `php artisan form-shield:install` to set up the built-in log.',
                $instance->getTable(),
            ));

            return self::FAILURE;
        }

        // Chunked, not get(): every submission writes a row and nothing prunes
        // by default, so the window has no upper bound. One pass fills every tally.
        $model::query()
            ->where($instance->qualifyColumn($instance->getCreatedAtColumn() ?? 'created_at'), '>=', $since)
            ->select([$instance->getKeyName(), 'is_spam', 'spam_reason', 'spam_score', 'spam_signals', 'spam_source', 'reviewed_at'])
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    $this->tally($row);
                }
            });

        if ($this->total === 0) {
            $this->warn(sprintf('No submissions in the last %d days.', $days));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(sprintf('Submissions since %s: <info>%d</info>', $since->toDateString(), $this->total));
        $this->newLine();

        $this->renderSignalTable($weights);
        $this->renderTotals();

        if ($this->reviewed === 0) {
            $this->newLine();
            $this->warn('No reviewed rows yet, so precision cannot be measured. Label some submissions '
                .'with markAsSpam() / markAsHam() first, including some false positives.');
        }

        return self::SUCCESS;
    }

    private function tally(Model $row): void
    {
        $this->total++;

        $source = $row->getAttribute('spam_source');
        $isGroundTruth = $source instanceof SpamSource
            ? $source->isGroundTruth()
            : $source === SpamSource::Reviewer->value;
        $isSpam = (bool) $row->getAttribute('is_spam');
        $isHam = $isGroundTruth && ! $isSpam;
        $flagged = $this->detectorFlagged($row);

        if ($isSpam) {
            $this->quarantined++;
        }

        if ($isGroundTruth) {
            $this->reviewed++;

            if ($isHam && $flagged) {
                $this->falsePositives++;
            }

            if ($isSpam && ! $flagged) {
                $this->missed++;
            }
        }

        foreach ($this->firedSignals($row) as $signal) {
            $this->fired[$signal] = ($this->fired[$signal] ?? 0) + 1;

            if ($isGroundTruth) {
                $this->firedOnReviewed[$signal] = ($this->firedOnReviewed[$signal] ?? 0) + 1;
            }

            if ($isHam) {
                $this->firedOnHam[$signal] = ($this->firedOnHam[$signal] ?? 0) + 1;
            }
        }
    }

    /** @param array<string, int> $weights */
    private function renderSignalTable(array $weights): void
    {
        if ($this->fired === []) {
            $this->warn('No signals fired in this window.');

            return;
        }

        $fired = $this->fired;
        arsort($fired);

        $rows = [];

        foreach ($fired as $signal => $count) {
            $reviewed = $this->firedOnReviewed[$signal] ?? 0;
            $ham = $this->firedOnHam[$signal] ?? 0;

            $rows[] = [
                $signal,
                isset($weights[$signal]) ? (string) $weights[$signal] : 'hard',
                (string) $count,
                (string) $reviewed,
                (string) $ham,
                $this->rate($reviewed - $ham, $reviewed),
            ];
        }

        $this->table(['Signal', 'Weight', 'Fired', 'Reviewed', 'Reviewed as ham', 'Precision'], $rows);
    }

    private function renderTotals(): void
    {
        $this->line(sprintf('Quarantined:      %d', $this->quarantined));
        $this->line(sprintf('Reviewed:         %d', $this->reviewed));
        $this->line(sprintf(
            'False positives:  %d%s',
            $this->falsePositives,
            $this->reviewed > 0 ? sprintf('  (%s of reviewed)', $this->rate($this->falsePositives, $this->reviewed)) : '',
        ));
        $this->line(sprintf('Missed spam:      %d  (marked spam by a reviewer, passed by the detector)', $this->missed));
        $this->newLine();
        $this->line('<comment>Precision only counts reviewed rows.</comment> A soft signal at 100% over a meaningful');
        $this->line('sample is a candidate for a higher weight or a hard signal. One below ~90% wants a');
        $this->line('lower weight, or removal. Note the date in config/form-shield.php when you change one.');
    }

    private function detectorFlagged(Model $row): bool
    {
        $reason = $row->getAttribute('spam_reason');

        return is_string($reason) && Reason::flagsSpam($reason);
    }

    /**
     * Soft signals that carried weight, plus the hard signal that decided the
     * verdict, if any. Zero-weight entries (the known-sender marker) are
     * bookkeeping, not signals.
     *
     * @return list<string>
     */
    private function firedSignals(Model $row): array
    {
        $signals = $row->getAttribute('spam_signals');
        $names = is_array($signals)
            ? array_keys(array_filter($signals, static fn (mixed $weight): bool => Value::int($weight) > 0))
            : [];

        $reason = $row->getAttribute('spam_reason');

        if (is_string($reason) && $this->detectorFlagged($row) && $reason !== Reason::Score->value) {
            $names[] = $reason;
        }

        return array_map(strval(...), $names);
    }

    private function rate(int $part, int $whole): string
    {
        return $whole === 0 ? '-' : sprintf('%.1f%%', ($part / $whole) * 100);
    }
}
