<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Testing;

use Closure;
use Itxshakil\FormShield\Contracts\Inspector;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Verdict;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Stands in for the inspector in your app's tests.
 *
 *     $shield = FormShield::fake();               // every submission is clean
 *     $shield = FormShield::fake()->flagAll();    // every submission is spam
 *     $shield->assertInspected('contact');
 */
final class FormShieldFake implements Inspector
{
    /** @var list<array{submission: Submission, profile: string, verdict: Verdict}> */
    private array $inspections = [];

    /** @var (Closure(Submission, string): Verdict)|null */
    private ?Closure $resolver = null;

    public function __construct(private ?Verdict $verdict = null) {}

    public function inspect(Submission $submission, string $profile = 'default'): Verdict
    {
        $verdict = $this->resolver !== null
            ? ($this->resolver)($submission, $profile)
            : $this->verdict ?? Verdict::clean(profile: $profile);

        $this->inspections[] = ['submission' => $submission, 'profile' => $profile, 'verdict' => $verdict];

        return $verdict;
    }

    public function returns(Verdict $verdict): self
    {
        $this->verdict = $verdict;
        $this->resolver = null;

        return $this;
    }

    /** @param Closure(Submission, string): Verdict $resolver */
    public function using(Closure $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    public function flagAll(string $reason = 'fake'): self
    {
        return $this->using(static fn (Submission $s, string $profile): Verdict => Verdict::flagged($reason, profile: $profile));
    }

    public function passAll(): self
    {
        return $this->using(static fn (Submission $s, string $profile): Verdict => Verdict::clean(profile: $profile));
    }

    /** @param (Closure(Submission, string): bool)|null $callback */
    public function assertInspected(?string $profile = null, ?Closure $callback = null): void
    {
        $matches = array_filter($this->inspections, static fn (array $i): bool => ($profile === null || $i['profile'] === $profile)
            && ($callback === null || $callback($i['submission'], $i['profile'])));

        PHPUnit::assertNotEmpty(
            $matches,
            $profile === null ? 'No submission was inspected.' : sprintf('No submission was inspected with profile [%s].', $profile),
        );
    }

    public function assertInspectedTimes(int $times, ?string $profile = null): void
    {
        $count = count(array_filter($this->inspections, static fn (array $i): bool => $profile === null || $i['profile'] === $profile));

        PHPUnit::assertSame($times, $count, sprintf('Expected %d inspection(s), got %d.', $times, $count));
    }

    public function assertNothingInspected(): void
    {
        PHPUnit::assertEmpty($this->inspections, sprintf('%d submission(s) were inspected unexpectedly.', count($this->inspections)));
    }

    /** @return list<array{submission: Submission, profile: string, verdict: Verdict}> */
    public function inspections(): array
    {
        return $this->inspections;
    }
}
