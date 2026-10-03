<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Closure;
use Itxshakil\FormShield\Contracts\HardSignal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

/** @internal Wraps a closure registered through FormShield::extendHard(). */
final class ClosureHardSignal implements HardSignal
{
    /** @param Closure(Submission, Profile): bool $callback */
    public function __construct(
        private readonly string $name,
        private readonly Closure $callback,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        return (bool) ($this->callback)($submission, $profile);
    }
}
