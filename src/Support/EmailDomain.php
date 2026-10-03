<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Support;

/** @internal */
final class EmailDomain
{
    public static function of(string $email): ?string
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return null;
        }

        $domain = mb_strtolower(trim(substr($email, $at + 1), " \t\n\r\0\x0B."));

        return $domain === '' || ! str_contains($domain, '.') ? null : $domain;
    }
}
