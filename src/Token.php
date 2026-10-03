<?php

declare(strict_types=1);

namespace Itxshakil\FormShield;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Support\Carbon;

/**
 * The encrypted "form started at" timestamp.
 *
 * It's encrypted, not sent in the clear, because a plain hidden timestamp is
 * trivial to forge, and then the timing checks would protect nothing.
 */
final class Token
{
    public function __construct(private readonly StringEncrypter $encrypter) {}

    public function mint(?int $timestamp = null): string
    {
        return $this->encrypter->encryptString((string) ($timestamp ?? Carbon::now()->getTimestamp()));
    }

    /** The timestamp a token was minted at, or null when it's missing or forged. */
    public function read(string $token): ?int
    {
        if ($token === '') {
            return null;
        }

        try {
            $value = $this->encrypter->decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($value) ? (int) $value : null;
    }
}
