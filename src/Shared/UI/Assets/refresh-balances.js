export function watchBalanceRequest({ request, onSuccess, onError, onFailure, onRedirect, onFinish }, environment = {}) {
    const page = environment.document ?? document;
    const schedule = environment.setTimeout ?? setTimeout;
    const cancel = environment.clearTimeout ?? clearTimeout;
    const containers = [...request.messages].map(message => message.component.el).filter(element => element.matches('[data-livewire-refresh]'));
    if (!containers.length) return;
    const status = page.querySelector('#operations-sync');
    const stale = () => { if (status?.isConnected) status.textContent = 'No se pudo actualizar. Los saldos mostrados pueden estar desactualizados; recargá antes de operar.'; };
    const denied = () => {
        containers.forEach(container => container.replaceChildren());
        if (status?.isConnected) status.textContent = 'Tu sesión o tus permisos cambiaron. Volvé a ingresar antes de consultar los saldos.';
    };
    const deadline = schedule(() => { stale(); request.cancel(); }, 2500);
    onSuccess(() => { if (status?.isConnected) status.textContent = 'Saldos actualizados. Actualización automática activa.'; });
    onFailure(stale);
    onError(({ response, preventDefault }) => {
        preventDefault();
        if ([401, 403, 419].includes(response.status)) denied();
        else stale();
    });
    onRedirect(({ preventDefault }) => { preventDefault(); denied(); });
    onFinish(() => cancel(deadline));
}

if (typeof document !== 'undefined') {
    const install = () => window.Livewire.interceptRequest(watchBalanceRequest);
    if (window.Livewire) install();
    else document.addEventListener('livewire:init', install, { once: true });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) document.querySelectorAll('[data-livewire-refresh]').forEach(container => {
            window.Livewire.find(container.getAttribute('wire:id')).$refresh().catch(() => {});
        });
    });
}
