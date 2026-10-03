<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\View\Components;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Itxshakil\FormShield\FormShield as ShieldManager;
use Itxshakil\FormShield\Support\Value;
use Itxshakil\FormShield\Token;

/**
 * Renders the honeypot, the encrypted timestamp and the JS marker.
 *
 *     <x-form-shield />
 *     <x-form-shield wire />           (inside a Livewire component using WithFormShield)
 */
final class FormShield extends Component
{
    public string $honeypotField;

    public string $timestampField;

    public string $jsField;

    public string $token;

    public ?string $honeypotClass;

    public string $honeypotId;

    public function __construct(Token $token, ShieldManager $shield, public bool $wire = false, public string $wireProperty = 'formShield')
    {
        $names = $shield->fieldNames();
        $class = Value::string(config('form-shield.honeypot_class'));

        $this->honeypotField = $names['honeypot'];
        $this->timestampField = $names['timestamp'];
        $this->jsField = $names['js'];
        $this->honeypotClass = $class === '' ? null : $class;
        $this->honeypotId = $this->honeypotField.'-'.Str::lower(Str::random(8));
        $this->token = $token->mint();
    }

    public function render(): View
    {
        return app(ViewFactory::class)->make('form-shield::components.form-shield');
    }
}
