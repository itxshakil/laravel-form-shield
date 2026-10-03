import { test } from 'node:test';
import assert from 'node:assert/strict';
import { armShield, fetchShield, fieldNamesFrom } from '../../resources/js/client.js';

const names = { honeypot: 'fax_number', timestamp: '_fs_started', js: '_fs_js' };

test('armShield copies the token into the js field once', () => {
    const data = { email: '', fax_number: '', _fs_started: 'TOKEN', _fs_js: '' };

    assert.equal(armShield(data, names), data);
    assert.equal(data._fs_js, 'TOKEN');

    data._fs_started = 'NEWER';
    armShield(data, names);
    assert.equal(data._fs_js, 'TOKEN');
});

test('armShield is a no-op without a token', () => {
    const data = { _fs_started: '', _fs_js: '' };

    armShield(data, names);
    assert.equal(data._fs_js, '');
    assert.doesNotThrow(() => armShield(null, names));
});

test('fieldNamesFrom reads FormShield::fields() order', () => {
    assert.deepEqual(fieldNamesFrom({ fax_number: '', _fs_started: 'x', _fs_js: '' }), names);
});

test('fetchShield returns the JSON payload and throws on errors', async () => {
    const payload = { names, fields: { fax_number: '', _fs_started: 'T', _fs_js: '' } };

    globalThis.fetch = async (url, init) => {
        assert.equal(url, '/form-shield/fields');
        assert.equal(init.headers.Accept, 'application/json');

        return { ok: true, json: async () => payload };
    };
    assert.deepEqual(await fetchShield('/form-shield/fields'), payload);

    globalThis.fetch = async () => ({ ok: false, status: 429 });
    await assert.rejects(fetchShield('/form-shield/fields'), /429/);
});
