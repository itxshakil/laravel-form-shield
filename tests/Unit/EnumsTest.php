<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Unit;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Enums\BuiltInSignal;
use Itxshakil\FormShield\Enums\Reason;
use Itxshakil\FormShield\Enums\StorageOption;
use Itxshakil\FormShield\Enums\VerdictStatus;
use Itxshakil\FormShield\Verdict;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class EnumsTest extends TestCase
{
    #[Test]
    public function verdicts_carry_a_matching_status(): void
    {
        self::assertSame(VerdictStatus::Clean, Verdict::clean()->status);
        self::assertSame(VerdictStatus::Spam, Verdict::flagged(Reason::Honeypot)->status);
        self::assertSame(VerdictStatus::Expired, Verdict::expired()->status);
        self::assertSame(VerdictStatus::RateLimited, Verdict::rateLimited(10)->status);
        self::assertTrue(VerdictStatus::Clean->passes());
        self::assertFalse(VerdictStatus::Spam->passes());
    }

    #[Test]
    public function reasons_compare_as_enums_or_strings(): void
    {
        $verdict = Verdict::flagged(Reason::TooFast);

        self::assertSame('too_fast', $verdict->reason);
        self::assertSame(Reason::TooFast, $verdict->knownReason());
        self::assertTrue($verdict->hasReason(Reason::TooFast));
        self::assertTrue($verdict->hasReason('too_fast'));
        self::assertTrue($verdict->isHardFlag());

        $custom = Verdict::flagged('banned_word');
        self::assertNull($custom->knownReason());
        self::assertTrue($custom->hasReason('banned_word'));
    }

    #[Test]
    public function flags_spam_treats_custom_reasons_as_spam_and_expiry_as_not(): void
    {
        self::assertTrue(Reason::flagsSpam('honeypot'));
        self::assertTrue(Reason::flagsSpam('score'));
        self::assertTrue(Reason::flagsSpam('banned_word'));
        self::assertFalse(Reason::flagsSpam('expired'));
        self::assertFalse(Reason::flagsSpam('rate_limited'));
        self::assertFalse(Reason::flagsSpam(null));
        self::assertFalse(Reason::flagsSpam(''));
        self::assertTrue(Reason::Honeypot->isHard());
        self::assertFalse(Reason::Score->isHard());
    }

    #[Test]
    public function every_built_in_signal_maps_to_a_signal_class_with_the_same_name(): void
    {
        foreach (BuiltInSignal::cases() as $case) {
            $class = $case->signalClass();
            self::assertTrue(is_subclass_of($class, Signal::class), $class);

            $reflection = new ReflectionClass($class);

            if ($reflection->getConstructor()?->getNumberOfRequiredParameters() === 0 || $reflection->getConstructor() === null) {
                self::assertSame($case->value, (new $class)->name());
            }
        }
    }

    #[Test]
    public function storage_options_round_trip_through_their_labels(): void
    {
        foreach (StorageOption::cases() as $case) {
            self::assertSame($case, StorageOption::fromLabel($case->label()));
        }

        self::assertSame(StorageOption::Skip, StorageOption::fromLabel('nonsense'));
        self::assertCount(3, StorageOption::labels());
    }
}
