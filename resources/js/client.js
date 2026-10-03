/**
 * Laravel Form Shield: helpers for forms rendered by JavaScript (Inertia,
 * Vue, React, Svelte) or embedded on another origin.
 *
 *   import { fetchShield, armShield } from '../../vendor/itxshakil/laravel-form-shield/resources/js/client.js';
 *
 *   // 1. Get fields: from an Inertia prop (FormShield::fields()) or the JSON endpoint.
 *   const shield = await fetchShield('/form-shield/fields');
 *
 *   // 2. Merge them into your form state.
 *   const form = useForm({ email: '', message: '', ...shield.fields });
 *
 *   // 3. Arm on the first real interaction (onFocus / onInput of the form).
 *   <form @focusin="armShield(form, shield.names)"> ... </form>
 */

/**
 * @param {string} url The fields endpoint (route name: form-shield.fields).
 * @param {RequestInit} [init]
 * @returns {Promise<{names: {honeypot: string, timestamp: string, js: string}, fields: Record<string, string>}>}
 */
export async function fetchShield(url, init = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        ...init,
    });

    if (!response.ok) {
        throw new Error(`Form Shield: fields endpoint answered ${response.status}`);
    }

    return response.json();
}

/**
 * Copy the token into the JS marker. Call from the form's first focus/input
 * handler. Calling it again does nothing.
 *
 * @param {Record<string, any>} data Reactive form state (Inertia useForm, a ref, a plain object).
 * @param {{timestamp: string, js: string}} names
 * @returns {Record<string, any>} the same object
 */
export function armShield(data, names) {
    if (data && names && data[names.timestamp] && !data[names.js]) {
        data[names.js] = data[names.timestamp];
    }

    return data;
}

/**
 * Field names when the fields came from an Inertia prop rather than the
 * endpoint: `fieldNamesFrom(page.props.formShield)`.
 *
 * Relies on the order FormShield::fields() returns: honeypot, timestamp, js.
 *
 * @param {Record<string, string>} fields
 */
export function fieldNamesFrom(fields) {
    const [honeypot, timestamp, js] = Object.keys(fields);

    return { honeypot, timestamp, js };
}
