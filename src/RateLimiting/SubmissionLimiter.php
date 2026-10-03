<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\RateLimiting;

use Illuminate\Cache\RateLimiter;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Support\Value;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Per-profile daily and site-wide hourly caps per IP.
 *
 * Every inspection counts as an attempt, clean or not: a flood is a flood
 * whether or not its messages look like spam.
 */
final class SubmissionLimiter
{
    public function __construct(private readonly RateLimiter $limiter) {}

    /** Seconds until the sender may submit again, or null when under every cap. */
    public function hit(Submission $submission, Profile $profile): ?int
    {
        $ip = $submission->ip();

        if ($ip === null || $ip === '' || $this->isAllowlisted($ip, $profile)) {
            return null;
        }

        $caps = array_filter([
            ['form-shield:day:'.$profile->name.':'.$ip, Value::nullableInt($profile->get('rate_limits.per_ip_per_day')), 86400],
            ['form-shield:hour:'.$ip, Value::nullableInt($profile->get('rate_limits.global_per_ip_per_hour')), 3600],
        ], static fn (array $cap): bool => $cap[1] !== null && $cap[1] > 0);

        foreach ($caps as [$key, $max]) {
            if ($this->limiter->tooManyAttempts($key, (int) $max)) {
                return max(1, $this->limiter->availableIn($key));
            }
        }

        foreach ($caps as [$key, , $decay]) {
            $this->limiter->hit($key, $decay);
        }

        return null;
    }

    public function isAllowlisted(string $ip, Profile $profile): bool
    {
        $allowlist = array_values(array_filter(
            array_map(trim(...), $profile->strings('rate_limits.allowlist')),
            static fn (string $entry): bool => $entry !== '',
        ));

        return $allowlist !== [] && IpUtils::checkIp($ip, $allowlist);
    }

    public function clear(Submission $submission, Profile $profile): void
    {
        $ip = (string) $submission->ip();

        $this->limiter->clear('form-shield:day:'.$profile->name.':'.$ip);
        $this->limiter->clear('form-shield:hour:'.$ip);
    }
}
