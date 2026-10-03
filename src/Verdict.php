<?php

declare(strict_types=1);

namespace Itxshakil\FormShield;

use Itxshakil\FormShield\Enums\Reason;
use Itxshakil\FormShield\Enums\SpamSource;
use Itxshakil\FormShield\Enums\VerdictStatus;
use JsonSerializable;

/**
 * What the inspector concluded about one submission.
 *
 * Four outcomes (`$status`): clean, spam, expired and rate limited. Expired
 * and rate limited aren't kinds of spam. The form sat open too long, or the
 * sender went over a cap, and how to tell the visitor is the caller's call,
 * because only the caller knows whether it serves HTML or JSON.
 *
 * The signal map and score are kept even on a clean verdict. Store them
 * either way: flagged rows alone give you no negatives to measure a signal's
 * precision against.
 */
final class Verdict implements JsonSerializable
{
    public readonly bool $isSpam;

    public readonly bool $isExpired;

    public readonly bool $isRateLimited;

    /**
     * @param  string|null  $reason  A Reason value, or the name of the custom hard signal that decided it.
     * @param  array<string, int>  $signals
     */
    private function __construct(
        public readonly VerdictStatus $status,
        public readonly ?string $reason,
        public readonly int $score,
        public readonly array $signals,
        public readonly string $profile,
        public readonly ?int $retryAfter = null,
    ) {
        $this->isSpam = $status === VerdictStatus::Spam;
        $this->isExpired = $status === VerdictStatus::Expired;
        $this->isRateLimited = $status === VerdictStatus::RateLimited;
    }

    /** @param array<string, int> $signals */
    public static function clean(array $signals = [], int $score = 0, string $profile = 'default'): self
    {
        return new self(VerdictStatus::Clean, null, $score, $signals, $profile);
    }

    /** @param array<string, int> $signals */
    public static function flagged(Reason|string $reason, array $signals = [], int $score = 0, string $profile = 'default'): self
    {
        return new self(VerdictStatus::Spam, $reason instanceof Reason ? $reason->value : $reason, $score, $signals, $profile);
    }

    public static function expired(string $profile = 'default'): self
    {
        return new self(VerdictStatus::Expired, Reason::Expired->value, 0, [], $profile);
    }

    public static function rateLimited(int $retryAfter, string $profile = 'default'): self
    {
        return new self(VerdictStatus::RateLimited, Reason::RateLimited->value, 0, [], $profile, $retryAfter);
    }

    /** Safe to process normally: not spam, not expired, not over a cap. */
    public function passes(): bool
    {
        return $this->status->passes();
    }

    public function hasReason(Reason|string $reason): bool
    {
        return $this->reason === ($reason instanceof Reason ? $reason->value : $reason);
    }

    /** The built-in reason, or null when there is none or a custom hard signal decided. */
    public function knownReason(): ?Reason
    {
        return $this->reason === null ? null : Reason::tryFrom($this->reason);
    }

    /** True when a hard signal decided the verdict on its own, rather than the score. */
    public function isHardFlag(): bool
    {
        return $this->isSpam && ! $this->hasReason(Reason::Score);
    }

    /**
     * Column values for a model using HasSpamVerdict.
     *
     * Don't spread these into Model::create(): models with `$fillable` drop
     * them silently. Use `Model::createWithVerdict($data, $verdict)` or
     * `$model->applyVerdict($verdict)`, which bypass mass-assignment.
     *
     * @return array{is_spam: bool, spam_reason: ?string, spam_score: int, spam_signals: array<string, int>, spam_source: string}
     */
    public function toAttributes(): array
    {
        return [
            'is_spam' => $this->isSpam,
            'spam_reason' => $this->reason,
            'spam_score' => $this->score,
            'spam_signals' => $this->signals,
            'spam_source' => SpamSource::Detector->value,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'is_spam' => $this->isSpam,
            'is_expired' => $this->isExpired,
            'is_rate_limited' => $this->isRateLimited,
            'reason' => $this->reason,
            'score' => $this->score,
            'signals' => $this->signals,
            'profile' => $this->profile,
            'retry_after' => $this->retryAfter,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
