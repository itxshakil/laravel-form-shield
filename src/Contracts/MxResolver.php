<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Contracts;

interface MxResolver
{
    public function hasMxRecord(string $domain): bool;
}
