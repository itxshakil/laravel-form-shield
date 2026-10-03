# Livewire

Form Shield works with Livewire 3 without any extra package. Livewire isn't a dependency.

```php
use Itxshakil\FormShield\Livewire\WithFormShield;

class ContactForm extends Component
{
    use WithFormShield;

    public string $name = '';
    public string $email = '';
    public string $message = '';

    public function save(): void
    {
        $this->validate([...]);

        $verdict = $this->inspectFormShield('contact', [
            'name' => $this->name,
            'email' => $this->email,
            'message' => $this->message,
        ]);

        Inquiry::createWithVerdict($this->only('name', 'email', 'message'), $verdict);

        if (! $verdict->isSpam) {
            // side effects
        }

        $this->reset('name', 'email', 'message');
        $this->resetFormShield();   // fresh token for the next message
    }
}
```

```blade
<form wire:submit="save">
    <x-form-shield wire />
    <input wire:model="name"> ...
</form>
```

and `@formShieldScripts` once in your layout.

Call `inspectFormShield()` **after** `validate()`. If the visitor fixes a mistake and submits the same message again, inspecting before validation would score the second attempt as a duplicate of the first.

## How it works

- `mountWithFormShield()` runs automatically on mount and fills the public `$formShield` array with fresh fields.
- `<x-form-shield wire />` renders the inputs bound with `wire:model="formShield.<field>"`.
- The shield script copies the token into the JS marker on first interaction, and fires an `input` event so `wire:model` syncs it.
- `inspectFormShield()` scores the data you pass (or all public properties when you pass none), with the shield fields merged on top. Form data can't overwrite the shield fields.

## Form objects

```php
$verdict = $this->inspectFormShield('contact', $this->form->all());
```

## A different property name

```blade
<x-form-shield wire wire-property="shield" />
```

then declare `public array $shield` yourself and pass it into `FormShield::inspect(Submission::fromArray([...]))`.

## Rate limits and IPs

`inspectFormShield()` reads the IP and user agent from the current Livewire update request, which comes from the same browser.
