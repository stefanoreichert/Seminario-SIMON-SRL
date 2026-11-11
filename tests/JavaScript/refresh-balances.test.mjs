import test from 'node:test';
import assert from 'node:assert/strict';
import { watchBalanceRequest } from '../../src/Shared/UI/Assets/refresh-balances.js';

function requestFixture() {
    const status = { isConnected: true, textContent: '' };
    const container = { matches: () => true, content: 'saldos anteriores', replaceChildren() { this.content = ''; } };
    const callbacks = {};
    let timeout;
    let cancelled = false;
    let cleared = false;
    const request = { messages: [{ component: { el: container } }], cancel() { cancelled = true; } };
    watchBalanceRequest({ request, ...Object.fromEntries(['onSuccess', 'onError', 'onFailure', 'onRedirect', 'onFinish'].map(name => [name, callback => { callbacks[name] = callback; }])) }, {
        document: { querySelector: () => status },
        setTimeout(callback, milliseconds) { assert.equal(milliseconds, 2500); timeout = callback; return 1; },
        clearTimeout(id) { assert.equal(id, 1); cleared = true; },
    });
    return { callbacks, status, container, timeout: () => timeout(), cancelled: () => cancelled, cleared: () => cleared };
}

test('a slow Livewire refresh is cancelled and explicitly marks retained balances stale', () => {
    const fixture = requestFixture();
    fixture.timeout();
    assert.equal(fixture.cancelled(), true);
    assert.equal(fixture.container.content, 'saldos anteriores');
    assert.match(fixture.status.textContent, /desactualizados/);
});

test('Livewire authorization and expired-session errors remove previously visible balances', () => {
    for (const status of [401, 403, 419]) {
        const fixture = requestFixture();
        let prevented = false;
        fixture.callbacks.onError({ response: { status }, preventDefault() { prevented = true; } });
        assert.equal(prevented, true);
        assert.equal(fixture.container.content, '');
        assert.match(fixture.status.textContent, /permisos cambiaron/);
    }
});

test('redirects to login remove visible balances and avoid rendering the login response into them', () => {
    const fixture = requestFixture();
    let prevented = false;
    fixture.callbacks.onRedirect({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(fixture.container.content, '');
});

test('network and server failures retain balances with an explicit stale notice', () => {
    for (const failure of ['network', 'server']) {
        const fixture = requestFixture();
        if (failure === 'network') fixture.callbacks.onFailure();
        else fixture.callbacks.onError({ response: { status: 503 }, preventDefault() {} });
        assert.equal(fixture.container.content, 'saldos anteriores');
        assert.match(fixture.status.textContent, /desactualizados/);
    }
});

test('a completed Livewire refresh clears its deadline and reports successful recovery', () => {
    const fixture = requestFixture();
    fixture.callbacks.onFailure();
    fixture.callbacks.onSuccess();
    fixture.callbacks.onFinish();
    assert.equal(fixture.cleared(), true);
    assert.match(fixture.status.textContent, /Saldos actualizados/);
});
