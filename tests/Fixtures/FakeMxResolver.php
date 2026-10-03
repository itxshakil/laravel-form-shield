<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Fixtures;

use Itxshakil\FormShield\Contracts\MxResolver;

final class FakeMxResolver implements MxResolver
{
    /** @var list<string> */
    public array $lookups = [];

    /** @param list<string> $deadDomains */
    public function __construct(public array $deadDomains = []) {}

    public function hasMxRecord(string $domain): bool
    {
        $this->lookups[] = $domain;

        return ! in_array($domain, $this->deadDomains, true);
    }
}
