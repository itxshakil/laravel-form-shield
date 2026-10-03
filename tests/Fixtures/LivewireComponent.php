<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Fixtures;

use Itxshakil\FormShield\Livewire\WithFormShield;
use Itxshakil\FormShield\Verdict;

/** A stand-in Livewire component (Livewire isn't a dependency). */
final class LivewireComponent
{
    use WithFormShield;

    public string $email = '';

    public string $message = '';

    /** @return array<string, mixed> */
    public function all(): array
    {
        return ['email' => $this->email, 'message' => $this->message];
    }

    public function save(): Verdict
    {
        return $this->inspectFormShield('default');
    }
}
