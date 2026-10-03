<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Itxshakil\FormShield\Enums\Reason;
use Itxshakil\FormShield\Enums\SpamSource;
use Itxshakil\FormShield\Events\SubmissionReviewed;
use Itxshakil\FormShield\Verdict;

/**
 * For models that store form submissions. Add the columns with
 * `$table->spamColumns()` in a migration.
 *
 * Two different facts live on the row, and they're kept apart on purpose:
 *
 *   - What the detector thought: spam_reason, spam_score and spam_signals.
 *     Written once and never overwritten.
 *   - What is currently true: is_spam, plus spam_source, reviewed_at and
 *     reviewed_by when a person decided.
 *
 * With one boolean you couldn't tell a correct prediction from a corrected
 * one, and the labels would be useless for tuning.
 *
 * @property bool $is_spam
 * @property string|null $spam_reason
 * @property int $spam_score
 * @property array<string, int>|null $spam_signals
 * @property SpamSource $spam_source
 * @property Carbon|null $reviewed_at
 * @property string|null $reviewed_by
 *
 * @mixin Model
 */
trait HasSpamVerdict
{
    public function initializeHasSpamVerdict(): void
    {
        $this->mergeCasts([
            'is_spam' => 'boolean',
            'spam_score' => 'integer',
            'spam_signals' => 'array',
            'spam_source' => SpamSource::class,
            'reviewed_at' => 'datetime',
        ]);
    }

    /**
     * Create and save a row from your validated data plus a verdict.
     *
     * `$attributes` go through normal mass-assignment (your `$fillable`
     * applies). The verdict columns are force-filled, so they're saved even
     * when they aren't fillable, which is the usual case.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithVerdict(array $attributes, Verdict $verdict): static
    {
        $model = static::query()->make($attributes);
        $model->applyVerdict($verdict)->save();

        return $model;
    }

    /**
     * Copy a verdict onto the model (unsaved). Bypasses `$fillable` for the
     * verdict columns only.
     */
    public function applyVerdict(Verdict $verdict): static
    {
        $this->forceFill($verdict->toAttributes());

        return $this;
    }

    /** @param Builder<static> $query */
    public function scopeSpam(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_spam'), true);
    }

    /** @param Builder<static> $query */
    public function scopeNotSpam(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_spam'), false);
    }

    /** @param Builder<static> $query */
    public function scopeReviewed(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('reviewed_at'));
    }

    /** @param Builder<static> $query */
    public function scopeUnreviewed(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('reviewed_at'));
    }

    public function markAsSpam(Authenticatable|int|string|null $by = null): static
    {
        return $this->recordReview(true, $by);
    }

    public function markAsHam(Authenticatable|int|string|null $by = null): static
    {
        return $this->recordReview(false, $by);
    }

    /** What the detector concluded, whatever a reviewer did later. */
    public function detectorFlagged(): bool
    {
        return Reason::flagsSpam($this->spam_reason);
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    /** A person ruled this legitimate after the detector flagged it. */
    public function isFalsePositive(): bool
    {
        return $this->isReviewed() && ! $this->is_spam && $this->detectorFlagged();
    }

    /** A person ruled this spam after the detector let it through. */
    public function isMissedSpam(): bool
    {
        return $this->isReviewed() && $this->is_spam && ! $this->detectorFlagged();
    }

    private function recordReview(bool $isSpam, Authenticatable|int|string|null $by): static
    {
        $wasSpam = (bool) $this->is_spam;
        $reviewer = match (true) {
            $by instanceof Authenticatable => is_scalar($id = $by->getAuthIdentifier()) ? (string) $id : null,
            $by === null => null,
            default => (string) $by,
        };

        $this->forceFill([
            'is_spam' => $isSpam,
            'spam_source' => SpamSource::Reviewer,
            'reviewed_at' => Carbon::now(),
            'reviewed_by' => $reviewer,
        ])->save();

        if (config('form-shield.events', true)) {
            event(new SubmissionReviewed($this, $isSpam, $wasSpam, $reviewer));
        }

        return $this;
    }
}
