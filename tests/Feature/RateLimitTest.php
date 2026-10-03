<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Support\Carbon;
use Itxshakil\FormShield\Facades\FormShield;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Tests\TestCase;
use Itxshakil\FormShield\Verdict;
use PHPUnit\Framework\Attributes\Test;

final class RateLimitTest extends TestCase
{
    private int $n = 0;

    private function submit(string $ip = '198.51.100.7', string $profile = 'default'): Verdict
    {
        $this->n++;

        return FormShield::inspect(
            Submission::fromArray($this->humanPayload(['message' => "Message number {$this->n} about hosting."]), $ip, 'Mozilla/5.0'),
            $profile,
        );
    }

    #[Test]
    public function there_are_no_caps_by_default(): void
    {
        for ($i = 0; $i < 20; $i++) {
            self::assertTrue($this->submit()->passes());
        }
    }

    #[Test]
    public function the_daily_cap_applies_per_ip_and_per_profile(): void
    {
        config()->set('form-shield.rate_limits.per_ip_per_day', 2);
        config()->set('form-shield.profiles.newsletter', []);

        self::assertTrue($this->submit()->passes());
        self::assertTrue($this->submit()->passes());

        $verdict = $this->submit();
        self::assertTrue($verdict->isRateLimited);
        self::assertFalse($verdict->isSpam);
        self::assertGreaterThan(0, $verdict->retryAfter);

        self::assertTrue($this->submit('198.51.100.8')->passes(), 'Another IP has its own allowance.');
        self::assertTrue($this->submit(profile: 'newsletter')->passes(), 'Another profile has its own allowance.');

        Carbon::setTestNow(Carbon::now()->addDay()->addSecond());
        self::assertTrue($this->submit()->passes(), 'The window resets.');
    }

    #[Test]
    public function the_hourly_cap_counts_across_profiles(): void
    {
        config()->set('form-shield.rate_limits.global_per_ip_per_hour', 2);
        config()->set('form-shield.profiles.newsletter', []);

        $this->submit();
        $this->submit(profile: 'newsletter');

        self::assertTrue($this->submit(profile: 'newsletter')->isRateLimited);
        self::assertTrue($this->submit()->isRateLimited);
    }

    #[Test]
    public function allowlisted_ips_and_ranges_skip_the_caps(): void
    {
        config()->set('form-shield.rate_limits.per_ip_per_day', 1);
        config()->set('form-shield.rate_limits.allowlist', ['203.0.113.0/24', '2001:db8::1']);

        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($this->submit('203.0.113.50')->passes());
            self::assertTrue($this->submit('2001:db8::1')->passes());
        }

        $this->submit('192.0.2.1');
        self::assertTrue($this->submit('192.0.2.1')->isRateLimited);
    }

    #[Test]
    public function profiles_can_set_their_own_caps(): void
    {
        config()->set('form-shield.profiles.newsletter', ['rate_limits' => ['per_ip_per_day' => 1]]);

        $this->submit(profile: 'newsletter');

        self::assertTrue($this->submit(profile: 'newsletter')->isRateLimited);
        self::assertTrue($this->submit()->passes());
    }
}
