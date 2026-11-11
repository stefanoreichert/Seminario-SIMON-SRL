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
    const data = await fixture('setup', ['--management-ui', '--current-accounts', '--order-lines', '--customer-search', '--livewire-forms'].some(flag => process.argv.includes(flag)) ? { orders: 20 } : undefined);
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
        if (response.exceptionDetails) throw new Error(response.exceptionDetails.exception?.description ?? response.exceptionDetails.text);
        return response.result.value;
    };
    const pages = {};
    inspectPages = async () => await Promise.all(Object.entries(pages).map(async ([actor, session]) => ({ actor, page: await evaluate(session, '({url: location.href, ready: document.readyState, title: document.title, text: document.body?.innerText.slice(0,400), measurements: window.pendingMeasurements?.slice(-2)})') })));
    if (process.argv.includes('--livewire-forms')) {
        stage = 'formularios generales con Livewire';
        const { browserContextId } = await send('Target.createBrowserContext');
        const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        pages.forms = sessionId;
        await send('Page.enable', {}, sessionId);
        await send('Network.setCookie', { name: data.cookie_name, value: data.actors.admin.cookie, url: base }, sessionId);
        await send('Page.navigate', { url: `${base}/admin/roles?nuevo=1` }, sessionId);
        const read = expression => evaluate(sessionId, expression).catch(() => null);
        await until(async () => await read('document.readyState === "complete" && !!document.querySelector("[data-management-actions]")'));
        await evaluate(sessionId, `window.livewireFormNavigations = 0; document.addEventListener('livewire:navigated', () => window.livewireFormNavigations++);`);
        const navigate = async (path) => {
            const previous = await read('window.livewireFormNavigations');
            await evaluate(sessionId, `window.Livewire.navigate(${JSON.stringify(base)} + ${JSON.stringify(path)})`);
            await until(async () => await read(`window.livewireFormNavigations > ${previous} && location.pathname === ${JSON.stringify(path.split('?')[0])}`));
        };
        const submit = async (selector, values, expected) => {
            stage = `guardando ${expected}`;
            const previous = await read('window.livewireFormNavigations');
            await evaluate(sessionId, `(() => {
                const form = document.querySelector(${JSON.stringify(selector)});
                for (const [name, value] of Object.entries(${JSON.stringify(values)})) {
                    const controls = [...form.querySelectorAll('[name]')].filter(control => control.name === name);
                    for (const control of controls) {
                        if (control.type === 'checkbox') control.checked = Array.isArray(value) ? value.includes(control.value) : Boolean(value);
                        else control.value = value;
                        control.dispatchEvent(new Event('input', {bubbles:true}));
                        control.dispatchEvent(new Event('change', {bubbles:true}));
                    }
                }
                form.requestSubmit();
            })()`);
            await until(async () => await read(`window.livewireFormNavigations > ${previous} && document.body.innerText.includes(${JSON.stringify(expected)})`));
        };
        await submit('form[action$="/usuarios/roles"]', { name: 'Operador Livewire', 'permissions[]': ['inventory.view'] }, 'Rol y permisos guardados');
        const roleId = await read(`new URL([...document.querySelectorAll('tr')].find(row => row.innerText.includes('Operador Livewire')).querySelector('a').href).searchParams.get('editar')`);
        await navigate('/admin/usuarios?nuevo=1');
        await submit('form[action$="/usuarios"]', { name: 'Cuenta Livewire', email: 'livewire@example.test', password: 'ClaveSintetica12345', password_confirmation: 'ClaveSintetica12345', 'role_ids[]': [roleId] }, 'Usuario y roles guardados');
        await navigate('/admin/clientes/nuevo');
        await submit('form[action$="/clientes"]', { name: 'Comercio Livewire', cuit: '20999999999', phone: '123456', email: 'comercio@example.test', credit_history: 'Observación inicial' }, 'Cliente registrado');
        await navigate('/admin/clientes?q=Comercio%20Livewire');
        const customerPath = await read(`new URL([...document.querySelectorAll('a')].find(link => link.textContent.trim() === 'Ver y editar').href).pathname`);
        await navigate(customerPath);
        const beforeError = await read('window.livewireFormNavigations');
        await evaluate(sessionId, `(() => { const form = document.querySelector('form[action$="${customerPath}"]'); form.querySelector('[name=name]').value = 'Valor conservado'; form.querySelector('[name=cuit]').value = '30000000000'; form.requestSubmit(); })()`);
        await until(async () => await read(`!!document.querySelector('[data-management-errors]')`));
        assert.equal(await read(`document.querySelector('form[action$="${customerPath}"] [name=name]').value`), 'Valor conservado');
        assert.equal(await read('window.livewireFormNavigations'), beforeError);
        await submit(`form[action$="${customerPath}"]`, { name: 'Comercio actualizado', cuit: '20999999999', phone: '654321' }, 'Perfil comercial actualizado');
        await submit('form[action$="/historial"]', { credit_history: 'Observación Livewire conservada' }, 'Observación crediticia guardada');
        await navigate(`${customerPath}/direcciones/nueva`);
        await submit('form[action$="/direcciones"]', { label: 'Destino Livewire', street: 'Calle Livewire 123', locality: 'Posadas', province: 'Misiones' }, 'Dirección guardada');
        await navigate(customerPath);
        assert.equal(await read(`document.body.innerText.includes('Destino Livewire') && document.body.innerText.includes('Observación Livewire conservada')`), true);
        await navigate('/admin/catalogo');
        await submit('form[action$="/variant"]', { code: 'LIVEWIRE-ART', model: 'Botín Livewire', color: 'Negro', size: '40' }, 'Artículo registrado');
        await navigate('/admin/produccion');
        await submit('form[action$="/production"]', { lot: 'LOTE-LIVEWIRE', manufactured_on: '2026-01-01', quantity: '5' }, 'Tanda en proceso registrada');
        await navigate('/admin/stock');
        await submit('form[action$="/receive"]', { quantity: '2' }, 'Alta confirmada');
        await submit('form[action$="/opening"]', { lot: 'INICIAL-LIVEWIRE', manufactured_on: '2026-01-01', quantity: '3' }, 'Existencias iniciales registradas');
        await navigate('/admin/insumos');
        await submit('form[action$="/anticipacion"]', { anticipation_days: '20' }, 'Anticipación de alertas guardada');
        await submit('form[action$="/insumos"]', { name: 'Adhesivo Livewire', lot: 'INSUMO-LIVEWIRE', expires_on: '2027-01-01' }, 'Insumo registrado');
        await navigate('/admin/autorizaciones');
        await evaluate(sessionId, `document.querySelector('a[aria-label^="Revisar autorización del pedido"]').click()`);
        await until(async () => await read(`!!document.querySelector('form[action$="/authorize"]')`));
        await until(async () => await read(`document.activeElement?.id === 'validacion-pedido'`));
        assert.equal(await read(`document.querySelector('form[action$="/authorize"] [name=order_id]').value !== ''`), true);
        assert.ok(await read(`document.querySelector('a[aria-label^="Revisar autorización del pedido"]').getBoundingClientRect().height`) >= 48);
        await submit('form[action$="/authorize"]', { billing: true, credit: true, conditions: 'Autorizado desde Livewire' }, 'Validación administrativa guardada');
        assert.equal(await read(`document.querySelector('form[action$="/authorize"] [name=billing]').checked && document.querySelector('form[action$="/authorize"] [name=credit]').checked && document.querySelector('form[action$="/authorize"] [name=conditions]').value === 'Autorizado desde Livewire'`), true);
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, sessionId);
        assert.equal(await read('document.documentElement.scrollWidth <= innerWidth'), true);
        await send('Emulation.clearDeviceMetricsOverride', {}, sessionId);
        await navigate('/admin/despachos');
        await until(async () => await read(`document.querySelector('[data-address-selector]').dataset.addressLoading !== 'true'`));
        await submit('form[action$="/dispatch"]', { quantity: '1', owner_withdrawal: true }, 'Despacho confirmado');
        const result = { checks: ['roles y permisos', 'usuarios y asignaciones', 'cliente y perfil', 'historial crediticio', 'dirección', 'catálogo', 'producción', 'alta de terminados', 'stock inicial', 'insumos y alertas', 'autorización', 'despacho'], transport: 'acciones y navegación Livewire', database: 'SQLite temporal' };
        await writeFile(path.join(root, 'storage/logs/ui002-forms-browser.json'), JSON.stringify(result, null, 2));
        console.log(JSON.stringify(result));
    } else if (process.argv.includes('--customer-search')) {
        stage = 'búsqueda reactiva de clientes';
        const { browserContextId } = await send('Target.createBrowserContext');
        const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        pages.customers = sessionId;
        await send('Page.enable', {}, sessionId);
        await send('Network.setCookie', { name: data.cookie_name, value: data.actors.admin.cookie, url: base }, sessionId);
        await send('Page.navigate', { url: `${base}/admin/clientes` }, sessionId);
        const readCustomers = expression => evaluate(sessionId, expression).catch(() => null);
        await until(async () => await readCustomers('document.readyState === "complete" && !!document.querySelector("[role=search]")'));
        await evaluate(sessionId, `(() => { window.searchLivewireMarker = true; const input = document.querySelector('[role=search] [name=q]'); input.value = 'Cliente sintético 1'; input.dispatchEvent(new Event('input', {bubbles:true})); input.form.requestSubmit(); })()`);
        await until(async () => await readCustomers(`new URL(location.href).searchParams.get('q') === 'Cliente sintético 1' && document.querySelector('tbody').innerText.includes('Cliente sintético 1') && !document.querySelector('tbody').innerText.includes('Cliente sintético 25')`));
        assert.equal(await readCustomers('window.searchLivewireMarker'), true);
        await evaluate(sessionId, `document.querySelector('[role=search] a').click()`);
        await until(async () => await readCustomers(`!new URL(location.href).searchParams.has('q') && document.querySelector('tbody').innerText.includes('Cliente sintético 2')`));
        assert.equal(await readCustomers('window.searchLivewireMarker'), true);
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, sessionId);
        assert.equal(await readCustomers('document.documentElement.scrollWidth <= innerWidth'), true);
        const result = { checks: ['buscar y limpiar sin recargar', 'URL conserva el filtro'], database: 'SQLite temporal' };
        await writeFile(path.join(root, 'storage/logs/ui002-customers-browser.json'), JSON.stringify(result, null, 2));
        console.log(JSON.stringify(result));
    } else if (process.argv.includes('--order-lines')) {
        stage = 'edición reactiva de líneas de pedido';
        const { address_id: addressId } = await fixture('mutate', { action: 'prepare-ui', actor_id: data.actors.admin.id, customer_id: data.customer_id });
        const { browserContextId } = await send('Target.createBrowserContext');
        const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        pages.orders = sessionId;
        await send('Page.enable', {}, sessionId);
        await send('Network.setCookie', { name: data.cookie_name, value: data.actors.admin.cookie, url: base }, sessionId);
        await send('Page.navigate', { url: `${base}/admin/pedidos` }, sessionId);
        const readOrders = expression => evaluate(sessionId, expression).catch(() => null);
        await until(async () => await readOrders('document.readyState === "complete" && !!document.querySelector("[data-add-line]")'));
        await evaluate(sessionId, `(() => { const customer = document.querySelector('[data-order-form] [name=customer_id]'); customer.value = ${JSON.stringify(String(data.customer_id))}; customer.dispatchEvent(new Event('change', {bubbles:true})); })()`);
        await until(async () => await readOrders(`!!document.querySelector('[name=delivery_address_id] option[value="${addressId}"]')`));
        await evaluate(sessionId, `(() => { const address = document.querySelector('[name=delivery_address_id]'); address.value = ${JSON.stringify(String(addressId))}; address.dispatchEvent(new Event('change', {bubbles:true})); })()`);
        await evaluate(sessionId, `(() => {
            window.orderLivewireMarker = true;
            const form = document.querySelector('[data-order-form]');
            const customer = form.querySelector('[name=customer_id]'); customer.value = customer.options[customer.options.length - 1].value; customer.dispatchEvent(new Event('change', {bubbles:true}));
            const origin = form.querySelector('[name=origin]'); origin.value = 'F'; origin.dispatchEvent(new Event('change', {bubbles:true}));
            const variant = form.querySelector('[name="lines[0][variant_id]"]'); variant.value = variant.options[1].value; variant.dispatchEvent(new Event('change', {bubbles:true}));
            const quantity = form.querySelector('[name="lines[0][quantity]"]'); quantity.value = '7'; quantity.dispatchEvent(new Event('input', {bubbles:true}));
            form.querySelector('[data-add-line]').click();
        })()`);
        await until(async () => await readOrders('document.querySelectorAll("[data-order-line]").length === 2'));
        await until(async () => await readOrders(`document.activeElement?.name === 'lines[1][variant_id]'`));
        await until(async () => await readOrders(`!document.querySelector('[name=delivery_address_id] option[value="${addressId}"]')`));
        assert.deepEqual(await readOrders(`({marker: window.orderLivewireMarker, origin: document.querySelector('[data-order-form] [name=origin]').value, customer: document.querySelector('[name=customer_id]').value !== '', quantity: document.querySelector('[name="lines[0][quantity]"]').value})`), { marker: true, origin: 'F', customer: true, quantity: '7' });
        await evaluate(sessionId, `(() => {
            const form = document.querySelector('[data-order-form]');
            const variant = form.querySelector('[name="lines[1][variant_id]"]'); variant.value = variant.options[2].value; variant.dispatchEvent(new Event('change', {bubbles:true}));
            const quantity = form.querySelector('[name="lines[1][quantity]"]'); quantity.value = '9'; quantity.dispatchEvent(new Event('input', {bubbles:true}));
            form.querySelector('[data-remove-line]').click();
        })()`);
        await until(async () => await readOrders('document.querySelectorAll("[data-order-line]").length === 1'));
        await until(async () => await readOrders('document.activeElement?.hasAttribute("data-add-line")'));
        assert.equal(await readOrders(`document.querySelector('[name="lines[0][quantity]"]').value`), '9');
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, sessionId);
        assert.equal(await readOrders('document.documentElement.scrollWidth <= innerWidth'), true);
        await until(async () => await readOrders('!document.querySelector("[data-address-loading]").getClientRects().length'));
        const originalOrderToken = await readOrders('document.querySelector("[data-order-form] [name=operation_id]").value');
        await evaluate(sessionId, 'document.querySelector("button[name=intent][value=draft]").click()');
        await until(async () => await readOrders(`document.body.innerText.includes('Borrador guardado') && document.querySelector('[data-order-form] [name=operation_id]')?.value !== ${JSON.stringify(originalOrderToken)}`));
        const result = { checks: ['cambiar cliente y actualizar direcciones', 'agregar y quitar líneas sin recargar', 'conservar cliente, origen y cantidades', 'guardar borrador con datos sintéticos'], database: 'SQLite temporal' };
        await writeFile(path.join(root, 'storage/logs/ui002-orders-browser.json'), JSON.stringify(result, null, 2));
        console.log(JSON.stringify(result));
    } else if (process.argv.includes('--current-accounts')) {
        stage = 'cuenta corriente ARS';
        const { browserContextId } = await send('Target.createBrowserContext');
        const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        pages.finance = sessionId;
        await send('Page.enable', {}, sessionId);
        await send('Network.setCookie', { name: data.cookie_name, value: data.actors.admin.cookie, url: base }, sessionId);
        await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);
        await send('Page.navigate', { url: `${base}/admin/cuenta-corriente?customer_id=${data.customer_id}` }, sessionId);
        const readFinance = expression => evaluate(sessionId, expression).catch(() => null);
        await until(async () => await readFinance('document.readyState === "complete" && !!document.querySelector("[data-account-uninitialized]")'));
        const post = async (kind, amount, source, note, expectedBalance) => {
            stage = `registrando ${kind}`;
            const token = await readFinance('document.querySelector("[name=operation_id]").value');
            await evaluate(sessionId, `for (const [name,value] of Object.entries(${JSON.stringify({kind,amount,source,note})})) document.querySelector('[name='+name+']').value=value; document.querySelector('[name=amount]').closest('form').requestSubmit()`);
            await until(async () => await readFinance(`document.readyState === 'complete' && document.querySelector('[name=operation_id]')?.value !== ${JSON.stringify(token)} && Number(document.querySelector('[data-account-balance]')?.dataset.accountBalance) === ${expectedBalance}`));
        };
        await post('opening', '1000,50', 'Planilla inicial UI', '', 100050);
        await post('payment', '100,25', 'Recibo UI', '', 90025);
        await post('adjustment', '-0,25', 'Conciliación UI', 'Diferencia documentada', 90000);
        await post('reconciliation', '900', 'Extracto UI', '', 90000);
        assert.equal(await readFinance('document.body.innerText.includes("Sin cambio de saldo")'), true);
        await evaluate(sessionId, 'window.dispatchEvent(new CustomEvent("theme-changed", {detail:"light"})); document.querySelector("[data-account-balance]").closest(".fi-section").scrollIntoView({block:"start"}); window.scrollBy(0,-80)');
        await until(async () => await readFinance('!document.documentElement.classList.contains("dark")'));
        const desktop = await send('Page.captureScreenshot', { format: 'png' }, sessionId);
        await writeFile(path.join(root, 'storage/app/private/ui002-cuenta-desktop.png'), Buffer.from(desktop.data, 'base64'));
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, sessionId);
        await evaluate(sessionId, 'window.dispatchEvent(new CustomEvent("theme-changed", {detail:"dark"})); document.querySelector("[data-account-balance]").closest(".fi-section").scrollIntoView({block:"start"}); window.scrollBy(0,-80)');
        await until(async () => await readFinance('document.documentElement.classList.contains("dark")'));
        assert.equal(await readFinance('document.documentElement.scrollWidth <= innerWidth'), true);
        const mobile = await send('Page.captureScreenshot', { format: 'png' }, sessionId);
        await writeFile(path.join(root, 'storage/app/private/ui002-cuenta-mobile.png'), Buffer.from(mobile.data, 'base64'));
        const result = { checks: ['saldo inicial ausente distinguido de cero', 'apertura explícita ARS', 'cobro', 'ajuste con motivo', 'conciliación sin duplicar saldo', 'fuentes e historial', 'panel escritorio claro/móvil oscuro'], endingMinor: 90000, currency: 'ARS', database: 'SQLite temporal', note: 'Datos sintéticos; no representa saldos reales ni habilita otra moneda.' };
        await writeFile(path.join(root, 'storage/logs/ui002-finance-browser.json'), JSON.stringify(result, null, 2));
        console.log(JSON.stringify(result));
    } else if (process.argv.includes('--management-ui')) {
        stage = 'revisión general Filament';
        const { address_id: addressId } = await fixture('mutate', { action: 'prepare-ui', actor_id: data.actors.admin.id, customer_id: data.customer_id });
        const { browserContextId } = await send('Target.createBrowserContext');
        const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        pages.management = sessionId;
        await send('Page.enable', {}, sessionId);
        await send('Network.setCookie', { name: data.cookie_name, value: data.actors.admin.cookie, url: base }, sessionId);
        const readUI = (expression) => evaluate(sessionId, expression).catch(() => null);
        const navigate = async (url, marker) => {
            await send('Page.navigate', { url }, sessionId);
            await until(async () => await readUI(`document.readyState === 'complete' && location.pathname === new URL(${JSON.stringify(url)}).pathname && [...new URL(${JSON.stringify(url)}).searchParams].every(([key,value]) => new URL(location.href).searchParams.get(key) === value) && !!document.querySelector('.fi-panel-admin') && ${marker ?? 'true'}`));
        };
        const routes = [
            ['inicio', '/admin'], ['usuarios', '/admin/usuarios'], ['roles', '/admin/roles'], ['clientes', '/admin/clientes'],
            ['cliente-nuevo', '/admin/clientes/nuevo'], ['cliente-perfil', `/admin/clientes/${data.customer_id}`],
            ['direccion-nueva', `/admin/clientes/${data.customer_id}/direcciones/nueva`], ['direccion-editar', `/admin/clientes/${data.customer_id}/direcciones/${addressId}/editar`],
            ['pedidos', '/admin/pedidos'], ['pedido-detalle', `/admin/pedidos/${data.target.order_id}`], ['pendientes', '/admin/pendientes'],
            ['catalogo', '/admin/catalogo'], ['produccion', '/admin/produccion'], ['stock', '/admin/stock'], ['despachos', '/admin/despachos'],
            ['autorizaciones', '/admin/autorizaciones'], ['auditoria', '/admin/auditoria'], ['trazabilidad', '/admin/trazabilidad?lot=LOTE-ENSAYO'],
            ['picking', '/admin/picking'], ['insumos', '/admin/insumos'],
        ];
        const checks = [];
        for (const [name, route] of routes) {
            stage = `revisando ${name}`;
            await navigate(`${base}${route}`);
            for (const [size, width, height] of [['desktop', 1365, 900], ['mobile', 390, 844]]) {
                await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: size === 'mobile' }, sessionId);
                await until(async () => await readUI('!!window.Alpine?.store("sidebar")'));
                await evaluate(sessionId, `window.Alpine.store('sidebar').${size === 'desktop' ? 'open' : 'close'}()`);
                for (const theme of ['light', 'dark']) {
                    await until(async () => await readUI('!!window.Alpine?.store("theme")'));
                    await evaluate(sessionId, `window.dispatchEvent(new CustomEvent('theme-changed', { detail: ${JSON.stringify(theme)} })); window.scrollTo(0, 0)`);
                    await until(async () => await readUI(`document.documentElement.classList.contains('dark') === ${theme === 'dark'}`));
                    await evaluate(sessionId, 'new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
                    await delay(200);
                    const metrics = await evaluate(sessionId, `({ overflow: document.documentElement.scrollWidth > innerWidth, sidebar: !!document.querySelector('.fi-main-sidebar'), sidebarOpen: window.Alpine.store('sidebar').isOpen, inputs: document.querySelectorAll('.fi-input').length, buttons: document.querySelectorAll('.fi-btn').length, hasError: document.body.innerText.includes('Server Error') || document.body.innerText.includes('Whoops') })`);
                    assert.equal(metrics.overflow, false, `${name}/${size}/${theme}: overflow`);
                    assert.equal(metrics.sidebar, true, `${name}: missing sidebar`);
                    assert.equal(metrics.sidebarOpen, size === 'desktop', `${name}: sidebar state`);
                    assert.equal(metrics.hasError, false, `${name}: error page`);
                    const screenshot = await send('Page.captureScreenshot', { format: 'png' }, sessionId);
                    await writeFile(path.join(root, `storage/app/private/ui002-${name}-${size}-${theme}.png`), Buffer.from(screenshot.data, 'base64'));
                    checks.push({ screen: name, size, theme, ...metrics });
                }
            }
            console.log(`UI verificada: ${name}`);
        }
        stage = 'búsqueda de clientes y enlaces previos';
        await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);
        await navigate(`${base}/admin/clientes`);
        await evaluate(sessionId, `(() => { const input = document.querySelector('[name=q]'); input.value = 'Cliente sintético 1'; input.dispatchEvent(new Event('input', {bubbles:true})); input.closest('form').requestSubmit(); })()`);
        await until(async () => await readUI(`document.readyState === 'complete' && new URL(location.href).searchParams.get('q') === 'Cliente sintético 1' && document.body.innerText.includes('Cliente sintético 1')`));
        await send('Page.navigate', { url: `${base}/gestion/clientes` }, sessionId);
        await until(async () => await readUI('document.readyState === "complete" && location.pathname === "/admin/clientes"'));
        const result = { screens: routes.length, variants: checks.length, checks, interactions: ['búsqueda de clientes', 'redirección heredada'], initialOrders: data.orders, initialLines: data.lines, database: 'SQLite temporal', note: 'Revisión general UI-001; las pruebas HTTP completan altas/ediciones de clientes y direcciones, permisos y métodos heredados. No acredita ergonomía industrial RNF-002.' };
        await writeFile(path.join(root, 'storage/logs/ui002-management-browser.json'), JSON.stringify(result, null, 2));
        console.log(JSON.stringify({ screens: result.screens, variants: result.variants, interactions: result.interactions }));
    } else if (process.argv.includes('--picking')) {
        stage = 'identificación y picking';
        const { codes } = await fixture('mutate', { action: 'prepare-picking', actor_id: data.actors.admin.id, token: randomUUID(), batch_id: data.target.batch_id });
        const { browserContextId } = await send('Target.createBrowserContext');
        const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        pages.picking = sessionId;
        await send('Page.enable', {}, sessionId);
        await send('Network.setCookie', { name: data.cookie_name, value: data.actors.admin.cookie, url: base }, sessionId);
        await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);
        await send('Page.navigate', { url: `${base}/admin/picking?line_id=${data.target.line_id}&batch_id=${data.target.batch_id}` }, sessionId);
        await until(async () => await evaluate(sessionId, 'document.readyState === "complete" && !!document.querySelector("[data-pair-code]")'));
        await evaluate(sessionId, 'window.dispatchEvent(new CustomEvent("theme-changed", {detail:"light"}))');
        const readPicking = (expression) => evaluate(sessionId, expression).catch(() => null);
        await evaluate(sessionId, 'window.pickingLivewireMarker = true');
        await evaluate(sessionId, `(() => { const form = document.querySelector('[data-picking-filter]'); for (const field of form.querySelectorAll('input')) { field.value = ''; field.dispatchEvent(new Event('input', {bubbles:true})); } form.requestSubmit(); })()`);
        await until(async () => await readPicking(`!document.querySelector('[data-pair-code]') && !new URL(location.href).searchParams.has('line_id')`));
        await evaluate(sessionId, `(() => { const form = document.querySelector('[data-picking-filter]'); for (const [name, value] of Object.entries(${JSON.stringify({line_id:data.target.line_id,batch_id:data.target.batch_id})})) { const field = form.querySelector('[name=' + name + ']'); field.value = value; field.dispatchEvent(new Event('input', {bubbles:true})); } form.requestSubmit(); })()`);
        await until(async () => await readPicking(`!!document.querySelector('[data-pair-code]') && new URL(location.href).searchParams.get('line_id') === ${JSON.stringify(String(data.target.line_id))}`));
        assert.equal(await readPicking('window.pickingLivewireMarker'), true);
        const scan = async (code, expected) => {
            stage = `leyendo par ${code}`;
            await until(async () => await readPicking('document.readyState === "complete" && !!document.querySelector("[data-pair-code]")'));
            await evaluate(sessionId, `(() => { const field = document.querySelector('[data-pair-code]'); field.value = ${JSON.stringify(code)}; field.dispatchEvent(new Event('input', {bubbles:true})); document.querySelector('[data-pair-scan-form]').requestSubmit(); })()`);
            await until(async () => await readPicking(`document.querySelector('[data-pair-scan-form]').innerText.includes(${JSON.stringify(expected)})`));
            assert.equal(await readPicking('window.pickingLivewireMarker'), true);
        };
        const selected = () => readPicking('document.readyState === "complete" ? document.querySelectorAll("[data-picked-code]").length : null');
        await scan(codes[0], 'Par leído y seleccionado');
        await until(async () => await selected() === 1);
        assert.equal(await evaluate(sessionId, 'document.activeElement?.name'), 'code');
        await scan(codes[0], 'El código ya fue leído');
        await until(async () => await evaluate(sessionId, 'document.body.innerText.includes("El código ya fue leído")'));
        assert.equal(await selected(), 1);
        await scan('DESCONOCIDO-RF011', 'Código desconocido');
        await until(async () => await evaluate(sessionId, 'document.body.innerText.includes("Código desconocido")'));
        assert.equal(await selected(), 1);
        await scan(codes[1], 'Par leído y seleccionado');
        await until(async () => await selected() === 2);
        await evaluate(sessionId, 'document.querySelector("[data-picked-code]").parentElement.querySelector("button").click()');
        await until(async () => await selected() === 1);
        await scan(codes[0], 'Par leído y seleccionado');
        await until(async () => await selected() === 2);
        await evaluate(sessionId, 'document.querySelector("[data-pair-scan-form]").scrollIntoView({block:"center"})');
        const desktop = await send('Page.captureScreenshot', { format: 'png' }, sessionId);
        await writeFile(path.join(root, 'storage/app/private/ui002-picking-desktop.png'), Buffer.from(desktop.data, 'base64'));
        await evaluate(sessionId, 'const form = document.querySelector("form[action$=despachar]"); form.querySelector("[name=owner_withdrawal]").checked = true; form.requestSubmit()');
        await until(async () => await selected() === 0 && await evaluate(sessionId, 'document.body.innerText.includes("Salida confirmada con los códigos individuales")'));
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, sessionId);
        await evaluate(sessionId, 'window.dispatchEvent(new CustomEvent("theme-changed", {detail:"dark"})); document.querySelector("[data-pair-scan-form]").scrollIntoView({block:"center"})');
        assert.equal(await evaluate(sessionId, 'document.documentElement.scrollWidth <= innerWidth'), true);
        const mobile = await send('Page.captureScreenshot', { format: 'png' }, sessionId);
        await writeFile(path.join(root, 'storage/app/private/ui002-picking-mobile.png'), Buffer.from(mobile.data, 'base64'));
        await send('Page.navigate', { url: `${base}/admin/insumos` }, sessionId);
        await until(async () => await evaluate(sessionId, 'document.readyState === "complete" && document.body.innerText.includes("INSUMO-PROXIMO")'));
        assert.equal(await evaluate(sessionId, 'document.body.innerText.includes("Próximo a vencer") && document.body.innerText.includes("Vencido")'), true);
        assert.equal(await evaluate(sessionId, 'document.documentElement.scrollWidth <= innerWidth'), true);
        const result = { checks: ['identificación por par', 'lectura con foco recuperado', 'código repetido y desconocido', 'quitar y volver a seleccionar', 'despacho con códigos', 'avisos antes y después del vencimiento', 'panel escritorio/móvil'], database: 'SQLite temporal', input: 'texto y Enter compatible con lector teclado; no se dispuso de hardware físico', note: 'RF-011 no acredita operación industrial de RNF-002.' };
        await writeFile(path.join(root, 'storage/logs/ui002-picking-browser.json'), JSON.stringify(result, null, 2));
        console.log(JSON.stringify(result));
    } else if (process.argv.includes('--production-needs')) {
        stage = 'necesidades de producción';
        const { browserContextId } = await send('Target.createBrowserContext');
        const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        pages.production = sessionId;
        await send('Page.enable', {}, sessionId);
        await send('Emulation.setFocusEmulationEnabled', { enabled: true }, sessionId);
        await send('Network.setCookie', { name: data.cookie_name, value: data.actors.production.cookie, url: base }, sessionId);
        await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);
        await send('Page.navigate', { url: `${base}/admin/produccion` }, sessionId);
        const selector = `[data-production-variant-id="${data.variant_id}"]`;
        const row = () => evaluate(sessionId, `(() => { const row = document.querySelector(${JSON.stringify(selector)}); return row ? { balance: Number(row.dataset.projectedBalance), injection: Number(row.dataset.injectionNeeded), available: Number(row.cells[4].textContent), inProcess: Number(row.cells[5].textContent), pending: Number(row.cells[6].textContent) } : null; })()`);
        const initialPending = data.totals.production / 5;
        await until(async () => (await row())?.balance === 10000 - initialPending);
        await until(async () => await evaluate(sessionId, '!!window.Alpine?.store("theme")'));
        await evaluate(sessionId, 'window.dispatchEvent(new CustomEvent("theme-changed", { detail: "light" }))');
        await until(async () => await evaluate(sessionId, '!document.documentElement.classList.contains("dark")'));
        assert.equal(await evaluate(sessionId, 'document.documentElement.classList.contains("dark")'), false);
        assert.equal(await evaluate(sessionId, 'document.body.textContent.includes("Cliente sintético") || document.body.textContent.includes("Crédito sintético privado")'), false);
        await evaluate(sessionId, 'document.querySelector("input[name=lot]").value = "FORMULARIO-CONSERVADO"; document.querySelector("input[name=lot]").focus()');
        await fixture('mutate', { action: 'create', actor_id: data.actors.S.id, origin: 'S', token: randomUUID(), customer_id: data.customer_id, variant_id: data.variant_id, quantity: 7 });
        await until(async () => (await row())?.balance === 10000 - initialPending - 7);
        assert.equal(await evaluate(sessionId, 'document.querySelector("input[name=lot]").value'), 'FORMULARIO-CONSERVADO');
        assert.equal(await evaluate(sessionId, 'document.activeElement?.name'), 'lot');
        const production = await fixture('mutate', { action: 'production', actor_id: data.actors.admin.id, token: randomUUID(), variant_id: data.variant_id, quantity: 4 });
        await until(async () => (await row())?.inProcess === 4);
        const receipt = { action: 'receive', actor_id: data.actors.admin.id, token: randomUUID(), production_id: production.production_id, quantity: 2 };
        await fixture('mutate', receipt);
        await fixture('mutate', receipt);
        await until(async () => { const current = await row(); return current?.inProcess === 2 && current.available === 10002; });
        assert.equal((await row()).balance, 10004 - initialPending - 7);
        await fixture('mutate', { action: 'dispatch', actor_id: data.actors.admin.id, token: randomUUID(), ...data.target });
        await until(async () => (await row())?.available === 9998);
        const current = await row();
        assert.equal(current.balance, 10004 - initialPending - 7);
        assert.equal(current.pending, initialPending + 3);
        assert.equal(current.injection, -current.balance);
        await evaluate(sessionId, 'document.querySelector("[data-production-needs]").scrollIntoView({block:"start"}); window.scrollBy(0, -80)');
        const desktop = await send('Page.captureScreenshot', { format: 'png' }, sessionId);
        await writeFile(path.join(root, 'storage/app/private/rf010-necesidades-desktop.png'), Buffer.from(desktop.data, 'base64'));
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, sessionId);
        await evaluate(sessionId, 'window.dispatchEvent(new CustomEvent("theme-changed", { detail: "dark" }))');
        await until(async () => await evaluate(sessionId, 'document.documentElement.classList.contains("dark")'));
        assert.equal(await evaluate(sessionId, 'document.documentElement.scrollWidth <= innerWidth'), true);
        assert.equal(await evaluate(sessionId, 'document.documentElement.classList.contains("dark")'), true);
        assert.equal(await evaluate(sessionId, 'getComputedStyle(document.querySelector("[data-production-mobile-id]").parentElement).display !== "none"'), true);
        await evaluate(sessionId, 'document.querySelector("[data-production-mobile-id]").scrollIntoView({block:"start"}); window.scrollBy(0, -80)');
        const mobile = await send('Page.captureScreenshot', { format: 'png' }, sessionId);
        await writeFile(path.join(root, 'storage/app/private/rf010-necesidades-mobile.png'), Buffer.from(mobile.data, 'base64'));
        await fixture('mutate', { action: 'revoke', actor_id: data.actors.production.id });
        await until(async () => await evaluate(sessionId, '!document.querySelector("[data-production-variant-id]") && document.querySelector("#operations-sync").textContent.includes("permisos cambiaron")'));
        const result = { checks: ['proyección por artículo', 'actualización tras pedido/tanda/alta/despacho', 'reintento sin duplicar', 'formulario y foco conservados', 'sin datos de clientes/crédito', 'escritorio claro', 'móvil oscuro sin desbordamiento', 'retiro de datos tras revocación'], database: 'SQLite temporal', initialOrders: data.orders, initialLines: data.lines, note: 'Verificación funcional y visual de RF-010; no acredita concurrencia ni rendimiento productivos.' };
        await writeFile(path.join(root, 'storage/logs/rf010-browser.json'), JSON.stringify(result, null, 2));
        console.log(JSON.stringify(result));
    } else if (process.argv.includes('--traceability')) {
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
        await writeFile(path.join(root, 'storage/app/private/ui002-trazabilidad-desktop.png'), Buffer.from(desktop.data, 'base64'));
        await evaluate(sessionId, `document.querySelector('[name=lot]').value = ''; document.querySelector('[name=manufactured_from]').value = '2026-01-01'; document.querySelector('[name=manufactured_to]').value = '2026-01-01'; document.querySelector('form[method=get]').requestSubmit()`);
        await until(async () => await evaluate(sessionId, 'document.readyState === "complete" && location.search.includes("manufactured_from=2026-01-01") && document.querySelectorAll("[data-trace-movement]").length === 2'));
        await evaluate(sessionId, `document.querySelector('[name=lot]').value = 'NO-EXISTE'; document.querySelector('form[method=get]').requestSubmit()`);
        await until(async () => await evaluate(sessionId, 'document.readyState === "complete" && location.search.includes("NO-EXISTE") && document.body.innerText.includes("No se encontraron despachos")'));
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, sessionId);
        await send('Page.navigate', { url: `${base}/admin/trazabilidad?lot=LOTE-ENSAYO` }, sessionId);
        await until(async () => await evaluate(sessionId, 'document.readyState === "complete" && document.querySelectorAll("[data-trace-movement]").length === 2'));
        assert.equal(await evaluate(sessionId, 'document.documentElement.scrollWidth <= innerWidth'), true);
        const mobile = await send('Page.captureScreenshot', { format: 'png' }, sessionId);
        await writeFile(path.join(root, 'storage/app/private/ui002-trazabilidad-mobile.png'), Buffer.from(mobile.data, 'base64'));
        const result = { checks: ['lote exacto', 'cliente/pedido/artículo', 'sin crédito', 'fechas inclusivas', 'sin coincidencias', 'móvil sin desbordamiento'], dispatches: 2, database: 'SQLite temporal', initialOrders: data.orders, note: 'Verificación funcional y visual de RF-009; no acredita el rendimiento RNF-001.' };
        await writeFile(path.join(root, 'storage/logs/ui002-traceability-browser.json'), JSON.stringify(result, null, 2));
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
                }).observe(document.querySelector('[data-refresh-url]'), { childList: true, characterData: true, attributes: true, subtree: true });
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
