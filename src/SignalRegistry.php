<?php

declare(strict_types=1);

namespace Itxshakil\FormShield;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Itxshakil\FormShield\Contracts\DeferredSignal;
use Itxshakil\FormShield\Contracts\HardSignal;
use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Signals\ClosureHardSignal;
use Itxshakil\FormShield\Signals\ClosureSignal;

/**
 * Every signal the inspector knows about, built-in and custom. Entries are
 * class names resolved lazily from the container, or ready instances.
 */
final class SignalRegistry
{
    /** @var array<string, class-string<Signal>|Signal> */
    private array $signals = [];

    /** @var array<string, Signal> */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<Signal>|Signal  $signal
     */
    public function register(string $name, string|Signal $signal): void
    {
        $this->signals[$name] = $signal;
        unset($this->resolved[$name]);
    }

    /**
     * @param  class-string<Signal>|Signal|Closure(Submission, Profile): bool  $signal
     */
    public function extend(string $name, string|Signal|Closure $signal, bool $hard = false): void
    {
        if ($signal instanceof Closure) {
            $signal = $hard ? new ClosureHardSignal($name, $signal) : new ClosureSignal($name, $signal);
        }

        $this->register($name, $signal);
    }

    public function forget(string $name): void
    {
        unset($this->signals[$name], $this->resolved[$name]);
    }

    public function has(string $name): bool
    {
        return isset($this->signals[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->signals);
    }

    /** @return list<HardSignal> */
    public function hard(): array
    {
        return array_values(array_filter($this->all(), static fn (Signal $s): bool => $s instanceof HardSignal));
    }

    /** @return list<Signal> Soft, non-deferred signals. */
    public function soft(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Signal $s): bool => ! $s instanceof HardSignal && ! $s instanceof DeferredSignal,
        ));
    }

    /** @return list<DeferredSignal> */
    public function deferred(): array
    {
        return array_values(array_filter($this->all(), static fn (Signal $s): bool => $s instanceof DeferredSignal));
    }

    /** @return array<string, Signal> */
    private function all(): array
    {
        foreach ($this->signals as $name => $signal) {
            $this->resolved[$name] ??= $this->resolve($name, $signal);
        }

        return array_intersect_key($this->resolved, $this->signals);
    }

    /** @param class-string<Signal>|Signal $signal */
    private function resolve(string $name, string|Signal $signal): Signal
    {
        $instance = is_string($signal) ? $this->container->make($signal) : $signal;

        if (! $instance instanceof Signal) {
            throw new InvalidArgumentException(sprintf(
                'Form Shield signal [%s] must implement %s.',
                $name,
                Signal::class,
            ));
        }

        return $instance;
    }
}
