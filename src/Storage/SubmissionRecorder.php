<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Storage;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Itxshakil\FormShield\Models\ShieldSubmission;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Support\Value;
use Itxshakil\FormShield\Verdict;

/**
 * Writes inspected submissions to the built-in log.
 *
 * @internal Use FormShield::record().
 */
final class SubmissionRecorder
{
    private const ALWAYS_REDACTED = ['_token', '_method'];

    public function __construct(private readonly Config $config) {}

    public function record(Submission $submission, Verdict $verdict, Profile $profile, ?Model $subject = null): ShieldSubmission
    {
        /** @var class-string<ShieldSubmission> $model */
        $model = $this->modelClass();

        $record = new $model;
        $record->forceFill([
            'profile' => $verdict->profile,
            'email' => $profile->email($submission) ?: null,
            'payload' => $this->payload($submission, $profile),
            'ip' => $submission->ip(),
            'user_agent' => mb_substr((string) $submission->userAgent(), 0, 512) ?: null,
            ...$verdict->toAttributes(),
        ]);

        if ($subject !== null) {
            $record->subject()->associate($subject);
        }

        $record->save();

        return $record;
    }

    /** Whether a verdict of this kind belongs in the log at all. */
    public function shouldRecord(Verdict $verdict): bool
    {
        // A flood (rate limited) or a stale form (expired) isn't a submission
        // anyone needs to review, and logging floods would let a bot fill the table.
        return ! $verdict->isRateLimited && ! $verdict->isExpired;
    }

    /** @return class-string<ShieldSubmission> */
    public function modelClass(): string
    {
        $model = Value::string($this->config->get('form-shield.store.model'), ShieldSubmission::class);

        return is_a($model, ShieldSubmission::class, true) ? $model : ShieldSubmission::class;
    }

    /** @return array<string, mixed> */
    private function payload(Submission $submission, Profile $profile): array
    {
        $redact = array_map('mb_strtolower', [
            ...self::ALWAYS_REDACTED,
            ...Value::strings($this->config->get('form-shield.store.redact')),
            $profile->field('honeypot'),
            $profile->field('timestamp'),
            $profile->field('js'),
        ]);

        $payload = [];

        foreach ($submission->all() as $key => $value) {
            $key = (string) $key;

            if (in_array(mb_strtolower($key), $redact, true)) {
                continue;
            }

            $payload[$key] = $this->clean($value);
        }

        return $payload;
    }

    /** Scalars and nested arrays only, so uploaded files and objects never reach the log. */
    private function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->clean(...), $value);
        }

        if (is_string($value)) {
            return mb_substr($value, 0, 10000);
        }

        return is_scalar($value) || $value === null ? $value : '['.get_debug_type($value).']';
    }
}
