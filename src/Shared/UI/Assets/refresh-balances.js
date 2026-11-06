export function startBalanceRefresh(container, status, environment = {}) {
    const request = environment.fetch ?? globalThis.fetch.bind(globalThis);
    const schedule = environment.setTimeout ?? globalThis.setTimeout.bind(globalThis);
    const cancel = environment.clearTimeout ?? globalThis.clearTimeout.bind(globalThis);
    const now = environment.now ?? (() => performance.now());
    const page = environment.document ?? globalThis.document;
    const window = environment.window ?? globalThis.window;
    let timer;
    let deadline;
    let controller;
    let refreshing = false;
    let stopped = false;

    const refresh = async () => {
        if (stopped || refreshing) return;
        cancel(timer);
        refreshing = true;
        const started = now();
        controller = new AbortController();
        deadline = schedule(() => controller.abort(), 2500);
        try {
            const response = await request(container.dataset.refreshUrl, {
                credentials: 'same-origin', headers: { Accept: 'text/html' }, cache: 'no-store', signal: controller.signal,
            });
            if (response.redirected || [401, 403].includes(response.status)) {
                container.replaceChildren();
                status.textContent = 'Tu sesión o tus permisos cambiaron. Volvé a ingresar antes de consultar los saldos.';
                return;
            }
            if (!response.ok) throw new Error('No se pudo actualizar');
            const html = await response.text();
            if (controller.signal.aborted || stopped) return;
            const focusedLink = container.contains(page.activeElement) ? page.activeElement?.getAttribute('href') : null;
            container.innerHTML = html;
            if (focusedLink !== null) {
                const replacement = [...container.querySelectorAll('a[href]')].find((link) => link.getAttribute('href') === focusedLink);
                (replacement ?? container).focus({ preventScroll: true });
            }
            status.textContent = 'Saldos actualizados. Actualización automática activa.';
        } catch {
            if (!stopped) status.textContent = 'No se pudo actualizar. Los saldos mostrados pueden estar desactualizados; recargá antes de operar.';
        } finally {
            cancel(deadline);
            refreshing = false;
            if (!stopped) timer = schedule(refresh, Math.max(0, 2000 - (now() - started)));
        }
    };

    const resume = () => {
        if (!page.hidden) void refresh();
    };
    const stop = () => {
        stopped = true;
        cancel(timer);
        cancel(deadline);
        controller?.abort();
        page.removeEventListener('visibilitychange', resume);
        window?.removeEventListener('pagehide', stop);
    };
    page.addEventListener('visibilitychange', resume);
    window?.addEventListener('pagehide', stop, { once: true });
    timer = schedule(refresh, 2000);

    return { refresh, stop };
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-refresh-url]').forEach((container) => {
        const status = document.querySelector('#operations-sync');
        if (status) startBalanceRefresh(container, status);
    });
}
