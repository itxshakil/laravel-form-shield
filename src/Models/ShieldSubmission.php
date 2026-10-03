<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Itxshakil\FormShield\Concerns\HasSpamVerdict;
use Itxshakil\FormShield\Support\Value;

/**
 * One inspected submission in the built-in log (`form_shield_submissions`).
 *
 * The payload is the posted input minus the shield fields and the keys in
 * `store.redact`. It's encrypted at rest by default, because it's personal
 * data you're keeping mainly so a person can review it.
 *
 * @property int $id
 * @property string $profile
 * @property string|null $email
 * @property array<string, mixed>|null $payload
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $subject_type
 * @property int|string|null $subject_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ShieldSubmission extends Model
{
    use HasSpamVerdict;
    use MassPrunable;

    protected $table = 'form_shield_submissions';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => Value::bool(config('form-shield.store.encrypt_payload'), true) ? 'encrypted:array' : 'array',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Link this log row to the record your app created from the submission. */
    public function attachTo(Model $subject): static
    {
        $this->subject()->associate($subject)->save();

        return $this;
    }

    /**
     * Unreviewed rows past `store.retention_days`. Reviewed rows are kept:
     * they're the ground truth the report measures against.
     *
     * Schedule: Schedule::command('model:prune', ['--model' => [ShieldSubmission::class]])->daily();
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = Value::int(config('form-shield.store.retention_days'), 90);

        return static::query()
            ->whereNull('reviewed_at')
            ->where('created_at', '<', Carbon::now()->subDays(max(1, $days)));
    }
}
