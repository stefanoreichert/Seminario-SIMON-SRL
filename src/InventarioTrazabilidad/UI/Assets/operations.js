document.querySelectorAll('[data-order-form]').forEach((form) => {
    form.querySelector('[data-add-line]')?.addEventListener('click', () => {
        const container = form.querySelector('[data-order-lines]');
        const index = container.children.length;
        if (index >= 100) return;
        const row = container.firstElementChild.cloneNode(true);
        row.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
            if (field instanceof HTMLInputElement) field.value = '';
        });
        container.appendChild(row);
    });
});

document.querySelectorAll('[data-address-selector]').forEach((form) => {
    const source = form.querySelector('[data-address-source]');
    const destinations = form.querySelector('[name="delivery_address_id"]');
    const status = form.querySelector('[data-address-status]');
    let pending;

    const loadAddresses = async () => {
        pending?.abort();
        pending = new AbortController();
        const request = pending;
        const selected = source.selectedOptions[0];
        const defaultLabel = selected?.dataset.destinationLabel || form.dataset.emptyAddressLabel;
        destinations.replaceChildren(new Option(defaultLabel, ''));
        destinations.disabled = true;
        status.textContent = 'Cargando direcciones…';

        if (!selected?.dataset.addressUrl) {
            status.textContent = 'Seleccioná primero un cliente o una línea del pedido.';
            return;
        }

        try {
            const response = await fetch(selected.dataset.addressUrl, {
                credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store', signal: request.signal,
            });
            if (!response.ok || response.redirected) throw new Error('No se pudieron consultar las direcciones');
            const addresses = await response.json();
            if (request.signal.aborted) return;
            addresses.forEach((address) => {
                destinations.appendChild(new Option(`${address.label} · ${address.street} · ${address.locality} / ${address.province}`, String(address.id)));
            });
            destinations.disabled = false;
            status.textContent = addresses.length ? 'Elegí el destino que corresponde a esta operación.' : 'El cliente no tiene direcciones activas. Podés registrarlas desde Clientes.';
        } catch (error) {
            if (request.signal.aborted || error.name === 'AbortError') return;
            status.textContent = 'No se pudieron cargar las direcciones. Volvé a seleccionar el cliente o la línea para reintentar.';
        }
    };

    source.addEventListener('change', loadAddresses);
    loadAddresses();
});

const balances = document.querySelector('[data-refresh-url]');
if (balances) {
    let refreshing = false;
    const refresh = async () => {
        if (refreshing) return;
        refreshing = true;
        try {
            const response = await fetch(balances.dataset.refreshUrl, { credentials: 'same-origin', headers: { Accept: 'text/html' }, cache: 'no-store' });
            if (!response.ok || response.redirected) throw new Error('No se pudo actualizar');
            balances.innerHTML = await response.text();
            document.querySelector('#operations-sync').textContent = 'Actualización automática activa';
        } catch {
            document.querySelector('#operations-sync').textContent = 'No se pudo actualizar. Recargá la página antes de confirmar una operación.';
        } finally {
            refreshing = false;
        }
    };
    setInterval(refresh, 2000);
}
