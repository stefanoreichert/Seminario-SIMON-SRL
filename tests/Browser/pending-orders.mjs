import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, readFile, writeFile, rm, access } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import net from 'node:net';
import http from 'node:http';

const root = path.resolve(import.meta.dirname, '../..');
const directory = await mkdtemp(path.join(tmpdir(), 'simon-pending-browser-'));
const database = path.join(directory, 'test.sqlite');
const profile = path.join(directory, 'browser');
const browserPath = process.env.RF008_BROWSER ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const phpPath = process.env.RF008_PHP ?? 'php';
const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
const servers = [];
let proxy;
let browser;
let socket;
const diagnostics = [];
let stage = 'setup';
let inspectPages = async () => [];
let closeBrowser = async () => {};

const listener = net.createServer();
await new Promise((resolve) => listener.listen(0, '127.0.0.1', resolve));
const port = listener.address().port;
await new Promise((resolve) => listener.close(resolve));
const base = `http://127.0.0.1:${port}`;
const environment = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: base, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', SESSION_DRIVER: 'database', SESSION_COOKIE: 'simon_rf008_browser_session', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false', SESSION_ENCRYPT: 'false', CACHE_STORE: 'array' };

async function fixture(action, request) {
    return await new Promise((resolve, reject) => {
        const args = [path.join(root, 'tests/Fixtures/pending-browser.php'), action];
        if (request) args.push(Buffer.from(JSON.stringify(request)).toString('base64'));
        const child = spawn(phpPath, args, { cwd: root, env: environment, windowsHide: true });
        let output = '';
        let errors = '';
        child.stdout.on('data', (chunk) => { output += chunk; });
        child.stderr.on('data', (chunk) => { errors += chunk; });
        child.on('error', reject);
        child.on('exit', (code) => {
            if (code !== 0) return reject(new Error(`Fixture ${action} failed: ${errors || output}`));
            try { resolve(JSON.parse(output)); } catch { reject(new Error(`Invalid fixture response for ${action}: ${errors}`)); }
        });
    });
}

async function until(check, milliseconds = 30000) {
    const end = performance.now() + milliseconds;
    while (performance.now() < end) {
        const result = await check();
        if (result) return result;
        await delay(50);
    }
    throw new Error(`Timed out waiting for the test condition: ${stage}`);
}

try {
    await access(browserPath);
    await writeFile(database, '');
    const data = await fixture('setup');
    const backends = [];
    for (let index = 0; index < 5; index++) {
        const free = net.createServer();
        await new Promise((resolve) => free.listen(0, '127.0.0.1', resolve));
        const backendPort = free.address().port;
        await new Promise((resolve) => free.close(resolve));
        const child = spawn(phpPath, ['-S', `127.0.0.1:${backendPort}`, path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.join(root, 'public'), env: environment, windowsHide: true });
        child.stdout.on('data', () => {});
        child.stderr.on('data', (chunk) => { diagnostics.push(String(chunk)); });
        servers.push(child);
        backends.push({ port: backendPort, active: 0 });
        await until(async () => { try { return (await fetch(`http://127.0.0.1:${backendPort}/up`)).ok; } catch { return false; } });
    }
    proxy = http.createServer((incoming, outgoing) => {
        const backend = [...backends].sort((left, right) => left.active - right.active)[0];
        backend.active++;
        const upstream = http.request({ hostname: '127.0.0.1', port: backend.port, path: incoming.url, method: incoming.method, headers: { ...incoming.headers, connection: 'close' } }, (response) => {
            outgoing.writeHead(response.statusCode, response.headers);
            response.pipe(outgoing);
        });
        let released = false;
        const release = () => { if (!released) { released = true; backend.active--; } };
        upstream.on('error', () => { release(); if (!outgoing.headersSent) outgoing.writeHead(502); outgoing.end(); });
        outgoing.on('close', () => { release(); upstream.destroy(); });
        incoming.pipe(upstream);
    });
    await new Promise((resolve) => proxy.listen(port, '127.0.0.1', resolve));
    await until(async () => { try { return (await fetch(`${base}/up`)).ok; } catch { return false; } });
    browser = spawn(browserPath, ['--headless=new', '--remote-debugging-port=0', `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--disable-background-timer-throttling', '--disable-renderer-backgrounding', '--disable-backgrounding-occluded-windows', 'about:blank'], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    browser.stderr.on('data', () => {});
    const devtools = await until(async () => { try { return (await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).trim().split('\n'); } catch { return null; } });
    socket = new WebSocket(`ws://127.0.0.1:${devtools[0]}${devtools[1]}`);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    let nextId = 0;
    const commands = new Map();
    socket.addEventListener('message', ({ data: message }) => {
        const response = JSON.parse(message);
        if (!commands.has(response.id)) return;
        const command = commands.get(response.id);
        commands.delete(response.id);
        clearTimeout(command.timeout);
        if (response.error) command.reject(new Error(`${command.method}: ${response.error.message}`));
        else command.resolve(response.result);
    });
    const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
        const id = ++nextId;
        const timeout = setTimeout(() => { commands.delete(id); reject(new Error(`DevTools timeout: ${method}`)); }, 10000);
        commands.set(id, { resolve, reject, method, timeout });
        socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    });
    closeBrowser = () => send('Browser.close');
    const evaluate = async (session, expression) => {
        const response = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }, session);
        if (response.exceptionDetails) throw new Error(response.exceptionDetails.text);
        return response.result.value;
    };
    const pages = {};
    inspectPages = async () => await Promise.all(Object.entries(pages).map(async ([actor, session]) => ({ actor, page: await evaluate(session, '({url: location.href, ready: document.readyState, title: document.title, text: document.body?.innerText.slice(0,400), measurements: window.pendingMeasurements?.slice(-2)})') })));
    if (process.argv.includes('--traceability')) {
        stage = 'trazabilidad';
        for (let index = 0; index < 2; index++) {
            await fixture('mutate', { action: 'dispatch', actor_id: data.actors.admin.id, token: randomUUID(), ...data.target });
        }
        const { browserContextId } = await send('Target.createBrowserContext');
        const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        pages.traceability = sessionId;
        await send('Page.enable', {}, sessionId);
        await send('Network.setCookie', { name: data.cookie_name, value: data.actors.admin.cookie, url: base }, sessionId);
        await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);
        await send('Page.navigate', { url: `${base}/admin/trazabilidad?lot=LOTE-ENSAYO` }, sessionId);
        await until(async () => await evaluate(sessionId, 'document.readyState === "complete" && document.querySelectorAll("[data-trace-movement]").length === 2'));
        assert.equal(await evaluate(sessionId, 'document.body.innerText.includes("Cliente sintético 1") && document.body.innerText.includes("NP-000001") && document.body.innerText.includes("ENSAYO-1")'), true);
        assert.equal(await evaluate(sessionId, 'document.body.innerText.includes("Crédito sintético privado")'), false);
        const desktop = await send('Page.captureScreenshot', { format: 'png' }, sessionId);
        await writeFile(path.join(root, 'storage/app/private/rf009-trazabilidad-desktop.png'), Buffer.from(desktop.data, 'base64'));
        await evaluate(sessionId, `document.querySelector('[name=lot]').value = ''; document.querySelector('[name=manufactured_from]').value = '2026-01-01'; document.querySelector('[name=manufactured_to]').value = '2026-01-01'; document.querySelector('form[method=get]').requestSubmit()`);
        await until(async () => await evaluate(sessionId, 'document.readyState === "complete" && location.search.includes("manufactured_from=2026-01-01") && document.querySelectorAll("[data-trace-movement]").length === 2'));
        await evaluate(sessionId, `document.querySelector('[name=lot]').value = 'NO-EXISTE'; document.querySelector('form[method=get]').requestSubmit()`);
        await until(async () => await evaluate(sessionId, 'document.readyState === "complete" && location.search.includes("NO-EXISTE") && document.body.innerText.includes("No se encontraron despachos")'));
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, sessionId);
        await send('Page.navigate', { url: `${base}/admin/trazabilidad?lot=LOTE-ENSAYO` }, sessionId);
        await until(async () => await evaluate(sessionId, 'document.readyState === "complete" && document.querySelectorAll("[data-trace-movement]").length === 2'));
        assert.equal(await evaluate(sessionId, 'document.documentElement.scrollWidth <= innerWidth'), true);
        const mobile = await send('Page.captureScreenshot', { format: 'png' }, sessionId);
        await writeFile(path.join(root, 'storage/app/private/rf009-trazabilidad-mobile.png'), Buffer.from(mobile.data, 'base64'));
        const result = { checks: ['lote exacto', 'cliente/pedido/artículo', 'sin crédito', 'fechas inclusivas', 'sin coincidencias', 'móvil sin desbordamiento'], dispatches: 2, database: 'SQLite temporal', initialOrders: data.orders, note: 'Verificación funcional y visual de RF-009; no acredita el rendimiento RNF-001.' };
        await writeFile(path.join(root, 'storage/logs/rf009-browser.json'), JSON.stringify(result, null, 2));
        console.log(JSON.stringify(result));
    } else {
        for (const [actor, identity] of Object.entries(data.actors)) {
            stage = `cargando ${actor}`;
            const { browserContextId } = await send('Target.createBrowserContext');
            const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
            const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
            pages[actor] = sessionId;
            await send('Page.enable', {}, sessionId);
            await send('Network.setCookie', { name: data.cookie_name, value: identity.cookie, url: base }, sessionId);
            await send('Emulation.setFocusEmulationEnabled', { enabled: true }, sessionId);
            await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);
            await send('Page.navigate', { url: `${base}/admin/${actor === 'production' ? 'produccion' : 'pendientes'}` }, sessionId);
            await until(async () => await evaluate(sessionId, 'document.readyState === "complete" && !!document.querySelector("[data-pending-report]")'));
            assert.equal(await evaluate(sessionId, 'Number(document.querySelector("[data-pending-report]").dataset.pendingPairs)'), data.totals[actor]);
            if (actor === 'production') {
                assert.equal(await evaluate(sessionId, 'document.body.textContent.includes("Cliente sintético")'), false);
                await evaluate(sessionId, 'document.querySelector("input[name=lot]").value = "FORMULARIO-CONSERVADO"');
            }
            await evaluate(sessionId, `(() => {
                window.pendingMeasurements = [];
                new MutationObserver(() => {
                    const element = document.querySelector('[data-pending-report]');
                    if (!element) return;
                    const pairs = Number(element.dataset.pendingPairs);
                    requestAnimationFrame(() => requestAnimationFrame(() => {
                        if (Number(document.querySelector('[data-pending-report]')?.dataset.pendingPairs) === pairs) {
                            window.pendingMeasurements.push({ pairs, visibleAtMs: Date.now() });
                        }
                    }));
                }).observe(document.querySelector('[data-refresh-url]'), { childList: true, subtree: true });
            })()`);
            console.log(`Sesión preparada: ${actor}`);
        }

        const measurements = [];
        const totals = { ...data.totals };
        const measure = async (label, request, delta, origin) => {
            stage = `midiendo ${label}`;
            await Promise.all(Object.values(pages).map((session) => evaluate(session, 'window.pendingMeasurements = []')));
            const { committedAtMs } = await fixture('mutate', request);
            for (const actor of ['admin', 'warehouse', 'production', origin]) totals[actor] += delta;
            const results = await until(async () => {
                const found = await Promise.all(Object.entries(pages).map(async ([actor, session]) => {
                    const observation = await evaluate(session, `window.pendingMeasurements.find((entry) => entry.visibleAtMs >= ${committedAtMs} && entry.pairs === ${totals[actor]})`);
                    return observation ? { actor, milliseconds: observation.visibleAtMs - committedAtMs } : null;
                }));
                return found.every(Boolean) ? found : null;
            }, 5000);
            for (const result of results) {
                assert.ok(result.milliseconds >= 0 && result.milliseconds <= 5000, `${label}/${result.actor} exceeded 5 seconds`);
                measurements.push({ action: label, ...result });
            }
            assert.equal(await evaluate(pages.production, 'document.querySelector("input[name=lot]").value'), 'FORMULARIO-CONSERVADO');
            console.log(`Medición aprobada: ${label}`);
        };
        await measure('alta S', { action: 'create', actor_id: data.actors.S.id, origin: 'S', token: randomUUID(), customer_id: data.customer_id, variant_id: data.variant_id, quantity: 13 }, 13, 'S');
        await measure('alta G', { action: 'create', actor_id: data.actors.G.id, origin: 'G', token: randomUUID(), customer_id: data.customer_id, variant_id: data.variant_id, quantity: 17 }, 17, 'G');
        const dispatch = { action: 'dispatch', actor_id: data.actors.admin.id, token: randomUUID(), ...data.target };
        await measure('despacho parcial', dispatch, -4, 'S');
        await measure('reintento despacho', dispatch, 0, 'S');
        await measure('anulación saldo', { action: 'cancel', actor_id: data.actors.admin.id, token: randomUUID(), ...data.target }, -496, 'S');
        await measure('confirmación borrador', { action: 'confirm', actor_id: data.actors.S.id, token: randomUUID(), ...data.target }, 21, 'S');

        const screenshots = path.join(root, 'storage/app/private');
        const desktop = await send('Page.captureScreenshot', { format: 'png' }, pages.admin);
        await writeFile(path.join(screenshots, 'rf008-pendientes-desktop.png'), Buffer.from(desktop.data, 'base64'));
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, pages.production);
        await evaluate(pages.production, 'localStorage.setItem("theme", "dark")');
        await send('Page.reload', {}, pages.production);
        await until(async () => await evaluate(pages.production, 'document.readyState === "complete" && !!document.querySelector("[data-pending-report]")'));
        assert.equal(await evaluate(pages.production, 'document.documentElement.scrollWidth <= innerWidth'), true);
        const mobile = await send('Page.captureScreenshot', { format: 'png' }, pages.production);
        await writeFile(path.join(screenshots, 'rf008-produccion-mobile.png'), Buffer.from(mobile.data, 'base64'));
        const result = { browser: browserPath, node: process.version, php: phpPath, database: 'SQLite temporal', httpWorkers: 5, initialOrders: data.orders, initialLines: data.lines, actors: 5, measurements, maximumMs: Math.max(...measurements.map((entry) => entry.milliseconds)), limitMs: 5000, note: 'Ensayo local con datos sintéticos y cinco contextos activos de navegador; no acredita red industrial, volumen real ni MySQL productivo.' };
        await writeFile(path.join(root, 'storage/logs/rf008-browser.json'), JSON.stringify(result, null, 2));
        console.log(JSON.stringify({ actors: result.actors, initialOrders: result.initialOrders, initialLines: result.initialLines, observations: measurements.length, maximumMs: result.maximumMs, limitMs: 5000 }));
    }
} catch (error) {
    console.error(error.message);
    try { console.error(JSON.stringify(await inspectPages())); } catch {}
    console.error(diagnostics.slice(-8).join('').slice(-1600));
    process.exitCode = 1;
} finally {
    if (socket?.readyState === WebSocket.OPEN) {
        try { await closeBrowser(); } catch {}
    }
    socket?.close();
    proxy?.closeAllConnections();
    proxy?.close();
    for (const child of [browser, ...servers]) {
        if (child && child.exitCode === null) {
            if (process.platform === 'win32') {
                await new Promise((resolve) => {
                    const cleanup = spawn('taskkill', ['/PID', String(child.pid), '/T', '/F'], { windowsHide: true, stdio: 'ignore' });
                    cleanup.on('exit', resolve);
                });
            } else child.kill();
        }
    }
    await delay(1000);
    assert.equal(path.dirname(path.resolve(directory)), path.resolve(tmpdir()));
    assert.ok(path.basename(directory).startsWith('simon-pending-browser-'));
    await rm(directory, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 });
}
