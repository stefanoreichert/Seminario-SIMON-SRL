const initializedForms = new WeakSet();
const initializeForms = () => document.querySelectorAll('[data-address-selector]').forEach((form) => {
    if (initializedForms.has(form)) return;
    initializedForms.add(form);
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
            const dispatcher = document.querySelector('[data-management-actions]');
            const path = new URL(selected.dataset.addressUrl, location.href).pathname;
            const customerId = Number(path.match(/\/clientes\/(\d+)\/direcciones\/opciones$/)?.[1]);
            if (!dispatcher || !customerId) throw new Error('No se pudieron consultar las direcciones');
            const addresses = await window.Livewire.find(dispatcher.getAttribute('wire:id')).addresses(customerId);
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

document.addEventListener('livewire:navigated', initializeForms);
initializeForms();
