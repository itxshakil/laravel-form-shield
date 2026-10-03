<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Unit;

use Itxshakil\FormShield\Enums\Reason;
use Itxshakil\FormShield\Verdict;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VerdictTest extends TestCase
{
    #[Test]
    public function clean_verdicts_pass_and_keep_their_signals(): void
    {
        $verdict = Verdict::clean(['no_js' => 2], 2, 'contact');

        self::assertTrue($verdict->passes());
        self::assertFalse($verdict->isSpam);
        self::assertSame(['no_js' => 2], $verdict->signals);
        self::assertSame('contact', $verdict->profile);
        self::assertNull($verdict->reason);
    }

    #[Test]
    public function flagged_score_verdicts_are_not_hard_flags(): void
    {
        self::assertFalse(Verdict::flagged(Reason::Score, ['no_js' => 2, 'duplicate' => 3], 5)->isHardFlag());
        self::assertTrue(Verdict::flagged('honeypot')->isHardFlag());
    }

    #[Test]
    public function expired_and_rate_limited_are_neither_spam_nor_passing(): void
    {
        $expired = Verdict::expired();
        $limited = Verdict::rateLimited(120);

        self::assertFalse($expired->isSpam);
        self::assertFalse($expired->passes());
        self::assertTrue($expired->isExpired);

        self::assertFalse($limited->isSpam);
        self::assertFalse($limited->passes());
        self::assertSame(120, $limited->retryAfter);
    }

    #[Test]
    public function to_attributes_maps_onto_the_spam_columns(): void
    {
        self::assertSame([
            'is_spam' => true,
            'spam_reason' => 'score',
            'spam_score' => 5,
            'spam_signals' => ['no_js' => 2, 'duplicate' => 3],
            'spam_source' => 'detector',
        ], Verdict::flagged('score', ['no_js' => 2, 'duplicate' => 3], 5)->toAttributes());
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $json = json_encode(Verdict::clean(profile: 'newsletter'), JSON_THROW_ON_ERROR);

        self::assertStringContainsString('"profile":"newsletter"', $json);
        self::assertStringContainsString('"is_spam":false', $json);
    }
}
