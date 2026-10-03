/**
 * Laravel Form Shield: arms each protected form on the visitor's first
 * interaction by copying its encrypted token into the JS marker field.
 *
 * Arming on interaction, not on load, is the point. A headless client has to
 * simulate focus or typing to fill the field, which is why this isn't a
 * DOMContentLoaded sweep.
 *
 * Works with plain forms, forms added later (Turbo, Livewire, Alpine), and
 * Livewire's wire:model, because the input event it fires bubbles.
 */
(function () {
    if (window.__formShieldArmed) {
        return;
    }

    window.__formShieldArmed = true;

    var armed = new WeakSet();

    function arm(form) {
        if (!form || armed.has(form)) {
            return;
        }

        var token = form.querySelector('[data-form-shield-token]');
        var marker = form.querySelector('[data-form-shield-js]');

        if (!token || !marker || !token.value) {
            return;
        }

        armed.add(form);
        marker.value = token.value;
        marker.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function onInteraction(event) {
        var target = event.target;

        // .form exists on every form control, so the common case (every keystroke
        // after the first) costs one property read and one WeakSet lookup.
        arm(target && (target.form || (target.closest && target.closest('form'))));
    }

    document.addEventListener('focusin', onInteraction, true);
    document.addEventListener('input', onInteraction, true);
    document.addEventListener('pointerdown', onInteraction, true);
})();
