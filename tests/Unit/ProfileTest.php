<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Unit;

use InvalidArgumentException;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'threshold' => 4,
            'weights' => ['no_js' => 2, 'duplicate' => 3],
            'inputs' => ['email' => ['email'], 'message' => ['message']],
            'rate_limits' => ['per_ip_per_day' => null, 'allowlist' => ['10.0.0.1']],
            'profiles' => [
                'default' => [],
                'booking' => [
                    'threshold' => 6,
                    'weights' => ['no_js' => 1],
                    'inputs' => ['message' => ['remarks']],
                    'rate_limits' => ['per_ip_per_day' => 5],
                    'signals' => ['no_js'],
                ],
            ],
        ];
    }

    #[Test]
    public function the_default_profile_uses_the_top_level_config(): void
    {
        $profile = Profile::resolve('default', $this->config());

        self::assertSame(4, $profile->threshold());
        self::assertSame(['no_js' => 2, 'duplicate' => 3], $profile->weights());
        self::assertTrue($profile->runsSignal('anything'));
    }

    #[Test]
    public function profile_overrides_merge_into_mergeable_keys(): void
    {
        $profile = Profile::resolve('booking', $this->config());

        self::assertSame(6, $profile->threshold());
        self::assertSame(['no_js' => 1, 'duplicate' => 3], $profile->weights());
        self::assertSame(['email'], $profile->inputKeys('email'));
        self::assertSame(['remarks'], $profile->inputKeys('message'));
        self::assertSame(5, $profile->get('rate_limits.per_ip_per_day'));
        self::assertSame(['10.0.0.1'], $profile->get('rate_limits.allowlist'));
        self::assertTrue($profile->runsSignal('no_js'));
        self::assertFalse($profile->runsSignal('duplicate'));
    }

    #[Test]
    public function an_undefined_profile_is_a_loud_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Profile::resolve('typo', $this->config());
    }

    #[Test]
    public function email_is_lowercased_and_message_follows_the_input_map(): void
    {
        $profile = Profile::resolve('booking', $this->config());
        $submission = Submission::fromArray(['email' => 'Jane@Example.COM', 'remarks' => 'Hi']);

        self::assertSame('jane@example.com', $profile->email($submission));
        self::assertSame('Hi', $profile->message($submission));
    }
}
