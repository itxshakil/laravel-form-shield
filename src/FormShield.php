<?php

declare(strict_types=1);

namespace Itxshakil\FormShield;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Itxshakil\FormShield\Contracts\Inspector as InspectorContract;
use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Models\ShieldSubmission;
use Itxshakil\FormShield\RateLimiting\SubmissionLimiter;
use Itxshakil\FormShield\Storage\SubmissionRecorder;
use Itxshakil\FormShield\Support\Value;
use Itxshakil\FormShield\Testing\FormShieldFake;

/**
 * The package's entry point, behind the FormShield facade.
 */
final class FormShield
{
    public const REQUEST_ATTRIBUTE = 'form_shield.verdict';

    public const PROFILE_ATTRIBUTE = 'form_shield.profile';

    public const COUNTED_ATTRIBUTE = 'form_shield.counted';

    public const RECORD_ATTRIBUTE = 'form_shield.record';

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
        private readonly SignalRegistry $signals,
        private readonly Token $token,
        private readonly SubmissionLimiter $limiter,
        private readonly SubmissionRecorder $recorder,
    ) {}

    /**
     * Inspect a request or a prepared submission against a profile.
     *
     * Inspecting the same request twice with the same profile returns the
     * first verdict. A second pass would see its own fingerprint and fire
     * `duplicate`, and would count against the rate caps again.
     */
    public function inspect(Request|Submission $submission, string $profile = 'default'): Verdict
    {
        if ($submission instanceof Request) {
            $request = $submission;
            $cached = $this->cachedVerdict($request);

            if ($cached !== null && $cached->profile === $profile) {
                return $cached;
            }

            $submission = Submission::fromRequest($request);

            if ($request->attributes->get(self::COUNTED_ATTRIBUTE) === true) {
                $submission = $submission->withAttemptCounted();
            }
        }

        $verdict = $this->container->make(InspectorContract::class)->inspect($submission, $profile);

        if (isset($request)) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $verdict);
        }

        if (Value::bool($this->config->get('form-shield.store.auto')) && $this->recorder->shouldRecord($verdict)) {
            $record = $this->recorder->record($submission, $verdict, $this->profile($profile));

            if (isset($request)) {
                $request->attributes->set(self::RECORD_ATTRIBUTE, $record);
            }
        }

        return $verdict;
    }

    /**
     * Save a submission and its verdict to the built-in log
     * (`form_shield_submissions`), optionally linked to the record your app
     * created from it. Use this when the form has no table of its own, or when
     * you want every form's verdicts in one place for the report.
     *
     * With `store.auto` on, inspect() records for you; then use recorded().
     */
    public function record(Request|Submission $submission, Verdict $verdict, ?Model $subject = null): ShieldSubmission
    {
        $request = $submission instanceof Request ? $submission : null;
        $existing = $request !== null ? $this->recorded($request) : null;

        if ($existing !== null) {
            return $subject !== null ? $existing->attachTo($subject) : $existing;
        }

        $record = $this->recorder->record(
            $submission instanceof Request ? Submission::fromRequest($submission) : $submission,
            $verdict,
            $this->profile($verdict->profile),
            $subject,
        );

        $request?->attributes->set(self::RECORD_ATTRIBUTE, $record);

        return $record;
    }

    /** The log row written for this request, if any. */
    public function recorded(Request $request): ?ShieldSubmission
    {
        $record = $request->attributes->get(self::RECORD_ATTRIBUTE);

        return $record instanceof ShieldSubmission ? $record : null;
    }

    /**
     * The request's verdict. On a route behind the `form-shield` middleware,
     * the first call runs the inspection, so call it after validation: a
     * visitor who fixes a typo and resubmits mustn't trip `duplicate`.
     */
    public function verdict(Request $request): ?Verdict
    {
        $cached = $this->cachedVerdict($request);

        if ($cached !== null) {
            return $cached;
        }

        $profile = $request->attributes->get(self::PROFILE_ATTRIBUTE);

        return is_string($profile) ? $this->inspect($request, $profile) : null;
    }

    /**
     * Count the request against the rate caps without inspecting it. Returns
     * the seconds until the sender may retry when over a cap. Used by the
     * middleware, so caps apply before any controller work.
     */
    public function throttle(Request $request, string $profile = 'default'): ?int
    {
        $retryAfter = $this->limiter->hit(Submission::fromRequest($request), $this->profile($profile));

        $request->attributes->set(self::COUNTED_ATTRIBUTE, true);

        if ($retryAfter !== null) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, Verdict::rateLimited($retryAfter, $profile));
        }

        return $retryAfter;
    }

    /** Whether the request carries a valid token older than the profile allows. */
    public function isExpired(Request $request, string $profile = 'default'): bool
    {
        $resolved = $this->profile($profile);
        $startedAt = $this->token->read(Submission::fromRequest($request)->string($resolved->field('timestamp')));

        return $startedAt !== null
            && Carbon::now()->getTimestamp() - $startedAt > $resolved->int('max_age_seconds', 43200);
    }

    private function cachedVerdict(Request $request): ?Verdict
    {
        $verdict = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        return $verdict instanceof Verdict ? $verdict : null;
    }

    /**
     * Freshly minted shield inputs, keyed by field name, for SPAs, Inertia
     * props, Livewire state and JSON clients.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        $names = $this->fieldNames();

        return [
            $names['honeypot'] => '',
            $names['timestamp'] => $this->token->mint(),
            $names['js'] => '',
        ];
    }

    /** @return array{honeypot: string, timestamp: string, js: string} */
    public function fieldNames(): array
    {
        return [
            'honeypot' => Value::string($this->config->get('form-shield.fields.honeypot'), 'fax_number'),
            'timestamp' => Value::string($this->config->get('form-shield.fields.timestamp'), '_fs_started'),
            'js' => Value::string($this->config->get('form-shield.fields.js'), '_fs_js'),
        ];
    }

    /**
     * Register a soft signal. Its weight comes from `weights.<name>` in the
     * config (or a profile), unless you pass one here.
     *
     * @param  class-string<Signal>|Signal|Closure(Submission, Profile): bool  $signal
     */
    public function extend(string $name, string|Signal|Closure $signal, ?int $weight = null): void
    {
        $this->signals->extend($name, $signal);

        if ($weight !== null) {
            $this->config->set('form-shield.weights.'.$name, $weight);
        }
    }

    /**
     * Register a hard signal: it quarantines on its own and runs before the
     * known-sender exemption.
     *
     * @param  class-string<Signal>|Signal|Closure(Submission, Profile): bool  $signal
     */
    public function extendHard(string $name, string|Signal|Closure $signal): void
    {
        $this->signals->extend($name, $signal, hard: true);
    }

    /** Stop running a signal everywhere. To drop it for one form, use the profile's `signals` list. */
    public function forget(string $name): void
    {
        $this->signals->forget($name);
    }

    public function signals(): SignalRegistry
    {
        return $this->signals;
    }

    public function profile(string $name = 'default'): Profile
    {
        return Profile::resolve($name, Value::map($this->config->get('form-shield')));
    }

    /** Swap the inspector for a fake that records inspections and returns a fixed verdict. */
    public function fake(?Verdict $verdict = null): FormShieldFake
    {
        $fake = new FormShieldFake($verdict);

        $this->container->instance(InspectorContract::class, $fake);

        return $fake;
    }
}
