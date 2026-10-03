<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Dns;

use Itxshakil\FormShield\Contracts\MxResolver;

final class NativeMxResolver implements MxResolver
{
    public function hasMxRecord(string $domain): bool
    {
        // A domain with no MX still accepts mail at its A record (RFC 5321 §5.1).
        return checkdnsrr($domain.'.', 'MX') || checkdnsrr($domain.'.', 'A');
    }
}
