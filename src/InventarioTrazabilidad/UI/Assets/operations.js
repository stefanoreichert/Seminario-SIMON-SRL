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
        form.dataset.addressLoading = 'true';
        status.textContent = 'Cargando direcciones…';

        if (!selected?.dataset.addressUrl) {
            destinations.disabled = false;
            delete form.dataset.addressLoading;
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
            destinations.disabled = false;
            status.textContent = 'No se pudieron cargar las direcciones. Volvé a seleccionar el cliente o la línea para reintentar.';
        } finally {
            if (!request.signal.aborted) delete form.dataset.addressLoading;
        }
    };

    source.addEventListener('change', loadAddresses);
    if (destinations.dataset.addressesReady === 'true') {
        status.textContent = 'Se conservaron el cliente y la dirección elegidos. Podés corregir los artículos antes de confirmar.';
    } else {
        loadAddresses();
    }
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
