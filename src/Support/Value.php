<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Support;

/**
 * Typed reads of loosely typed config values. A value of the wrong type
 * falls back to the default instead of being coerced, so a typo in
 * config/form-shield.php can't turn into a surprising verdict.
 *
 * @internal
 */
final class Value
{
    public static function string(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : $default;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && is_numeric($value) ? (int) $value : $default;
    }

    public static function nullableInt(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : null;
    }

    public static function bool(mixed $value, bool $default = false): bool
    {
        return is_bool($value) ? $value : (is_int($value) ? $value !== 0 : $default);
    }

    /** @return list<string> */
    public static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item) || is_int($item)) {
                $strings[] = (string) $item;
            }
        }

        return $strings;
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }
}
