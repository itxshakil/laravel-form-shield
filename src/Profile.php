<?php

declare(strict_types=1);

namespace Itxshakil\FormShield;

use InvalidArgumentException;
use Itxshakil\FormShield\Support\Value;

/**
 * The effective settings for one named form profile: the package config with
 * that profile's overrides applied.
 */
final class Profile
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function __construct(
        public readonly string $name,
        private readonly array $config,
    ) {}

    /**
     * @param  array<array-key, mixed>  $config  The full form-shield config array.
     */
    public static function resolve(string $name, array $config): self
    {
        $config = Value::map($config);
        $profiles = Value::map($config['profiles'] ?? []);

        if ($name !== 'default' && ! array_key_exists($name, $profiles)) {
            throw new InvalidArgumentException(sprintf(
                'Form Shield profile [%s] is not defined in config/form-shield.php.',
                $name,
            ));
        }

        $overrides = Value::map($profiles[$name] ?? []);

        foreach (['weights', 'inputs', 'exemption', 'rate_limits'] as $mergeable) {
            if (isset($overrides[$mergeable]) && is_array($overrides[$mergeable])) {
                $overrides[$mergeable] = array_replace(
                    is_array($config[$mergeable] ?? null) ? $config[$mergeable] : [],
                    $overrides[$mergeable],
                );
            }
        }

        unset($config['profiles']);

        return new self($name, array_replace($config, $overrides));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function string(string $key, string $default = ''): string
    {
        return Value::string($this->get($key), $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return Value::int($this->get($key), $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return Value::bool($this->get($key), $default);
    }

    /** @return list<string> */
    public function strings(string $key): array
    {
        return Value::strings($this->get($key));
    }

    public function threshold(): int
    {
        return $this->int('threshold', 4);
    }

    public function weight(string $signal): int
    {
        return $this->int('weights.'.$signal);
    }

    /** @return array<string, int> */
    public function weights(): array
    {
        return array_map(static fn (mixed $weight): int => Value::int($weight), Value::map($this->get('weights')));
    }

    /** Null = every registered soft signal runs. */
    public function runsSignal(string $signal): bool
    {
        $enabled = $this->get('signals');

        return ! is_array($enabled) || in_array($signal, $enabled, true);
    }

    /** @return list<string> */
    public function inputKeys(string $input): array
    {
        $keys = $this->strings('inputs.'.$input);

        return $keys === [] ? [$input] : $keys;
    }

    public function field(string $field): string
    {
        return $this->string('fields.'.$field);
    }

    public function email(Submission $submission): string
    {
        return mb_strtolower($submission->first($this->inputKeys('email')));
    }

    public function name(Submission $submission): string
    {
        return $submission->first($this->inputKeys('name'));
    }

    public function message(Submission $submission): string
    {
        return $submission->first($this->inputKeys('message'));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->config;
    }
}
