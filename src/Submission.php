<?php

declare(strict_types=1);

namespace Itxshakil\FormShield;

use Illuminate\Http\Request;

/**
 * A read-only, hostile-input-safe view of one form submission.
 *
 * Reads never cast. Posting `email[]=x` gives an array where a string is
 * expected, and this class exists to survive exactly that kind of input, so
 * anything that isn't a string reads as ''.
 */
final class Submission
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(
        private readonly array $data,
        private readonly ?string $ip = null,
        private readonly ?string $userAgent = null,
        private readonly bool $attemptCounted = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self($request->all(), $request->ip(), $request->userAgent());
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, ?string $ip = null, ?string $userAgent = null): self
    {
        return new self($data, $ip, $userAgent);
    }

    /** The trimmed string value of a key, or '' for anything else. */
    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /**
     * The first non-empty string among the given keys.
     *
     * @param  list<string>  $keys
     */
    public function first(array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->string($key);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function filled(string $key): bool
    {
        $value = $this->data[$key] ?? null;

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null && $value !== [] && $value !== false;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(strval(...), array_keys($this->data));
    }

    /** @return array<array-key, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * A copy marked as already counted against the rate caps, e.g. by the
     * middleware, so the inspector doesn't count it a second time.
     */
    public function withAttemptCounted(): self
    {
        return new self($this->data, $this->ip, $this->userAgent, true);
    }

    public function attemptCounted(): bool
    {
        return $this->attemptCounted;
    }

    public function ip(): ?string
    {
        return $this->ip;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }
}
