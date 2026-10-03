<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Contracts;

use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Verdict;

interface Inspector
{
    public function inspect(Submission $submission, string $profile = 'default'): Verdict;
}
