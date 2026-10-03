import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const script = readFileSync(new URL('../../resources/js/form-shield.js', import.meta.url), 'utf8');

function page(body) {
    const dom = new JSDOM(`<!doctype html><body>${body}</body>`, { runScripts: 'outside-only' });
    dom.window.eval(script);

    return dom.window;
}

const form = (token = 'TOKEN') => `
    <form>
        <input name="email">
        <input type="hidden" name="_fs_started" value="${token}" data-form-shield-token>
        <input type="hidden" name="_fs_js" value="" data-form-shield-js>
    </form>`;

test('does nothing until the visitor interacts', () => {
    const window = page(form());

    assert.equal(window.document.querySelector('[data-form-shield-js]').value, '');
});

test('arms on first focus', () => {
    const window = page(form());
    const email = window.document.querySelector('[name=email]');

    email.dispatchEvent(new window.FocusEvent('focusin', { bubbles: true }));

    assert.equal(window.document.querySelector('[data-form-shield-js]').value, 'TOKEN');
});

test('arms each form on the page separately', () => {
    const window = page(form('A') + form('B'));
    const [first, second] = window.document.querySelectorAll('form');

    first.querySelector('[name=email]').dispatchEvent(new window.Event('input', { bubbles: true }));

    assert.equal(first.querySelector('[data-form-shield-js]').value, 'A');
    assert.equal(second.querySelector('[data-form-shield-js]').value, '');

    second.querySelector('[name=email]').dispatchEvent(new window.Event('input', { bubbles: true }));

    assert.equal(second.querySelector('[data-form-shield-js]').value, 'B');
});

test('fires a bubbling input event so wire:model picks the value up', () => {
    const window = page(form());
    const marker = window.document.querySelector('[data-form-shield-js]');
    let seen = null;

    marker.addEventListener('input', (e) => { seen = e.target.value; });
    window.document.querySelector('[name=email]').dispatchEvent(new window.FocusEvent('focusin', { bubbles: true }));

    assert.equal(seen, 'TOKEN');
});

test('arms forms added after load', () => {
    const window = page('');
    window.document.body.insertAdjacentHTML('beforeend', form('LATE'));

    window.document.querySelector('[name=email]').dispatchEvent(new window.Event('input', { bubbles: true }));

    assert.equal(window.document.querySelector('[data-form-shield-js]').value, 'LATE');
});

test('waits for Livewire to fill an empty token', () => {
    const window = page(form(''));
    const email = window.document.querySelector('[name=email]');
    const token = window.document.querySelector('[data-form-shield-token]');

    email.dispatchEvent(new window.Event('input', { bubbles: true }));
    assert.equal(window.document.querySelector('[data-form-shield-js]').value, '');

    token.value = 'FILLED';
    email.dispatchEvent(new window.Event('input', { bubbles: true }));
    assert.equal(window.document.querySelector('[data-form-shield-js]').value, 'FILLED');
});

test('loading the script twice registers listeners once', () => {
    const window = page(form());
    window.eval(script);

    let inputs = 0;
    window.document.querySelector('[data-form-shield-js]').addEventListener('input', () => inputs++);
    window.document.querySelector('[name=email]').dispatchEvent(new window.FocusEvent('focusin', { bubbles: true }));

    assert.equal(inputs, 1);
});
