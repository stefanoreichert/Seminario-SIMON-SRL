import { test } from 'node:test';
import assert from 'node:assert/strict';
import { startBalanceRefresh } from '../../src/Shared/UI/Assets/refresh-balances.js';

function harness(fetch) {
    const timers = new Map();
    const events = new Map();
    let sequence = 0;
    const container = { dataset: { refreshUrl: '/admin/pendientes/saldos?origin=S&customer_page=2' }, innerHTML: 'saldo anterior', contains: () => false, querySelectorAll: () => [], replaceChildren() { this.innerHTML = ''; } };
    const status = { textContent: '' };
    const page = { hidden: false, activeElement: null, addEventListener: (name, callback) => events.set(name, callback), removeEventListener: (name) => events.delete(name) };
    const environment = {
        fetch, document: page, window: { addEventListener() {}, removeEventListener() {} }, now: () => 0,
        setTimeout(callback, delay) { timers.set(++sequence, { callback, delay }); return sequence; },
        clearTimeout(id) { timers.delete(id); },
    };
    const controls = startBalanceRefresh(container, status, environment);
    return { timers, events, container, status, page, controls };
}

test('replaces only the balance fragment, preserves the URL scope, and polls every two seconds', async () => {
    let options;
    const state = harness(async (url, request) => {
        assert.equal(url, '/admin/pendientes/saldos?origin=S&customer_page=2');
        options = request;
        return { ok: true, status: 200, text: async () => 'saldo nuevo' };
    });
    assert.equal([...state.timers.values()][0].delay, 2000);
    await state.controls.refresh();
    assert.equal(state.container.innerHTML, 'saldo nuevo');
    assert.equal(options.cache, 'no-store');
    assert.equal(options.credentials, 'same-origin');
    assert.match(state.status.textContent, /Saldos actualizados/);
    assert.equal(state.timers.size, 1);
    assert.equal([...state.timers.values()][0].delay, 2000);
    state.controls.stop();
});

test('does not overlap requests and marks data stale when a request exceeds 2500 ms', async () => {
    let calls = 0;
    const state = harness((_url, request) => new Promise((_resolve, reject) => {
        calls++;
        request.signal.addEventListener('abort', () => reject(new Error('timeout')));
    }));
    const running = state.controls.refresh();
    await state.controls.refresh();
    assert.equal(calls, 1);
    const timeout = [...state.timers.values()].find((timer) => timer.delay === 2500);
    assert.ok(timeout);
    timeout.callback();
    await running;
    assert.equal(state.container.innerHTML, 'saldo anterior');
    assert.match(state.status.textContent, /desactualizados/);
    state.controls.stop();
});

test('removes previously visible data when the server denies access or redirects to login', async () => {
    for (const response of [{ status: 401 }, { status: 403 }, { status: 200, redirected: true }]) {
        const state = harness(async () => response);
        await state.controls.refresh();
        assert.equal(state.container.innerHTML, '');
        assert.match(state.status.textContent, /permisos cambiaron/);
        state.controls.stop();
    }
});

test('a server failure keeps the last data with an explicit stale notice and permits retry', async () => {
    const state = harness(async () => ({ ok: false, status: 500 }));
    await state.controls.refresh();
    assert.equal(state.container.innerHTML, 'saldo anterior');
    assert.match(state.status.textContent, /desactualizados/);
    assert.equal(state.timers.size, 1);
    state.controls.stop();
});

test('resumes immediately on returning to the page and stops cleanly when leaving', async () => {
    let calls = 0;
    const state = harness(async () => { calls++; return { ok: true, status: 200, text: async () => 'nuevo' }; });
    state.page.hidden = true;
    state.events.get('visibilitychange')();
    assert.equal(calls, 0);
    state.page.hidden = false;
    state.events.get('visibilitychange')();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 1);
    state.controls.stop();
    await state.controls.refresh();
    assert.equal(calls, 1);
    assert.equal(state.timers.size, 0);
    assert.equal(state.events.size, 0);
});

test('restores focus to the same pagination link after replacing the fragment', async () => {
    const state = harness(async () => ({ ok: true, status: 200, text: async () => 'nuevo' }));
    let focused = false;
    state.page.activeElement = { getAttribute: () => '/admin/pendientes?article_page=2' };
    state.container.contains = () => true;
    state.container.querySelectorAll = () => [{ getAttribute: () => '/admin/pendientes?article_page=2', focus: () => { focused = true; } }];
    await state.controls.refresh();
    assert.equal(focused, true);
    state.controls.stop();
});
