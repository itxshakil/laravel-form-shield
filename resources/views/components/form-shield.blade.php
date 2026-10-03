{{--
    The honeypot is moved off-screen rather than hidden with display:none, which
    bots know to skip. aria-hidden and tabindex="-1" are load-bearing, not
    cosmetic: a screen-reader user who tabbed in and filled it would be
    quarantined with no feedback, because quarantine is designed to look
    exactly like success.
--}}
<div @if ($honeypotClass) class="{{ $honeypotClass }}" @else style="position:absolute!important;left:-9999px!important;top:auto;width:1px;height:1px;overflow:hidden;" @endif aria-hidden="true">
    <label for="{{ $honeypotId }}">{{ __('form-shield::messages.leave_empty') }}</label>
    <input
        id="{{ $honeypotId }}"
        type="text"
        name="{{ $honeypotField }}"
        value=""
        tabindex="-1"
        autocomplete="off"
        @if ($wire) wire:model="{{ $wireProperty }}.{{ $honeypotField }}" @endif
    />
</div>
@if ($wire)
<input type="hidden" name="{{ $timestampField }}" wire:model="{{ $wireProperty }}.{{ $timestampField }}" data-form-shield-token />
<input type="hidden" name="{{ $jsField }}" wire:model="{{ $wireProperty }}.{{ $jsField }}" data-form-shield-js />
@else
<input type="hidden" name="{{ $timestampField }}" value="{{ $token }}" data-form-shield-token />
<input type="hidden" name="{{ $jsField }}" value="" data-form-shield-js />
@endif
