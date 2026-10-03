<?php

declare(strict_types=1);

namespace Itxshakil\FormShield;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Itxshakil\FormShield\Contracts\Inspector as InspectorContract;
use Itxshakil\FormShield\Contracts\SenderExemption;
use Itxshakil\FormShield\Enums\Reason;
use Itxshakil\FormShield\Events\SubmissionInspected;
use Itxshakil\FormShield\Events\SubmissionQuarantined;
use Itxshakil\FormShield\RateLimiting\SubmissionLimiter;
use Itxshakil\FormShield\Support\Value;

/**
 * Decides whether a form submission came from a bot, without a CAPTCHA.
 *
 * The order is deliberate:
 *
 *   1. Rate caps. A flood is answered before any work is spent on it.
 *   2. Hard signals: honeypot, missing or forged token, expiry, too fast, then
 *      any custom hard signals. Each one decides on its own.
 *   3. The known-sender exemption. It comes after the hard signals because
 *      customer email addresses are cheap to scrape. If it came first, any
 *      bot with a scraped address would skip every check.
 *   4. Soft signals add their weights. Deferred (networked) signals run only
 *      while the score is still under the threshold.
 *
 * The verdict always carries the full signal map. Store it whether the
 * submission is spam or not, so the report can measure each signal against
 * real traffic.
 */
final class Inspector implements InspectorContract
{
    public function __construct(
        private readonly Config $config,
        private readonly SignalRegistry $signals,
        private readonly Token $token,
        private readonly SubmissionLimiter $limiter,
        private readonly SenderExemption $exemption,
        private readonly Dispatcher $events,
    ) {}

    public function inspect(Submission $submission, string $profile = 'default'): Verdict
    {
        $resolved = Profile::resolve($profile, Value::map($this->config->get('form-shield')));

        $verdict = $this->decide($submission, $resolved);

        if ($this->config->get('form-shield.events', true)) {
            $this->events->dispatch(new SubmissionInspected($verdict, $submission));

            if ($verdict->isSpam) {
                $this->events->dispatch(new SubmissionQuarantined($verdict, $submission));
            }
        }

        return $verdict;
    }

    private function decide(Submission $submission, Profile $profile): Verdict
    {
        $retryAfter = $submission->attemptCounted() ? null : $this->limiter->hit($submission, $profile);

        if ($retryAfter !== null) {
            return Verdict::rateLimited($retryAfter, $profile->name);
        }

        if ($submission->filled($profile->field('honeypot'))) {
            return Verdict::flagged(Reason::Honeypot, profile: $profile->name);
        }

        $startedAt = $this->token->read($submission->string($profile->field('timestamp')));

        if ($startedAt === null) {
            return Verdict::flagged(Reason::NoToken, profile: $profile->name);
        }

        $elapsed = Carbon::now()->getTimestamp() - $startedAt;

        if ($elapsed > $profile->int('max_age_seconds', 43200)) {
            return Verdict::expired($profile->name);
        }

        if ($elapsed < $profile->int('min_seconds', 3)) {
            return Verdict::flagged(Reason::TooFast, profile: $profile->name);
        }

        foreach ($this->signals->hard() as $signal) {
            if ($signal->fires($submission, $profile)) {
                return Verdict::flagged($signal->name(), profile: $profile->name);
            }
        }

        if ($this->exemption->exempts($submission, $profile)) {
            // Weight zero: a bypass marker, kept out of the report's precision table.
            return Verdict::clean([$this->exemption->name() => 0], profile: $profile->name);
        }

        $threshold = $profile->threshold();
        $signals = [];

        foreach ($this->signals->soft() as $signal) {
            $this->evaluate($signal, $submission, $profile, $signals);
        }

        foreach ($this->signals->deferred() as $signal) {
            if (array_sum($signals) >= $threshold) {
                break;
            }

            $this->evaluate($signal, $submission, $profile, $signals);
        }

        $score = array_sum($signals);

        if ($score >= $threshold) {
            return Verdict::flagged(Reason::Score, $signals, $score, $profile->name);
        }

        return Verdict::clean($signals, $score, $profile->name);
    }

    /**
     * @param  array<string, int>  $signals
     */
    private function evaluate(Contracts\Signal $signal, Submission $submission, Profile $profile, array &$signals): void
    {
        $name = $signal->name();

        if (! $profile->runsSignal($name) || ! $signal->fires($submission, $profile)) {
            return;
        }

        $signals[$name] = $profile->weight($name);
    }
}
