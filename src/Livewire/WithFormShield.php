<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Livewire;

use Itxshakil\FormShield\Facades\FormShield;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Verdict;

/**
 * Protects a Livewire form.
 *
 *     class ContactForm extends Component
 *     {
 *         use WithFormShield;
 *
 *         public function save(): void
 *         {
 *             $verdict = $this->inspectFormShield('contact', ['email' => $this->email, 'message' => $this->message]);
 *             // ...
 *         }
 *     }
 *
 * and render `<x-form-shield wire />` inside the component's <form>.
 *
 * @property array<string, string> $formShield
 */
trait WithFormShield
{
    /** @var array<string, string> */
    public array $formShield = [];

    /** Livewire calls mount<TraitName>() automatically. */
    public function mountWithFormShield(): void
    {
        $this->formShield = FormShield::fields();
    }

    /**
     * @param  array<string, mixed>|null  $data  The form values to score. Defaults to the component's public properties.
     */
    protected function inspectFormShield(string $profile = 'default', ?array $data = null): Verdict
    {
        $data ??= $this->formShieldPayload();
        unset($data['formShield']);

        $request = request();

        return FormShield::inspect(
            Submission::fromArray([...$data, ...$this->formShield], $request->ip(), $request->userAgent()),
            $profile,
        );
    }

    /** New token, e.g. after a successful submit when the form stays on screen. */
    protected function resetFormShield(): void
    {
        $this->formShield = FormShield::fields();
    }

    /**
     * Livewire components provide this: the public properties as an array.
     *
     * @return array<string, mixed>
     */
    abstract public function all();

    /** @return array<string, mixed> */
    protected function formShieldPayload(): array
    {
        return (array) $this->all();
    }
}
