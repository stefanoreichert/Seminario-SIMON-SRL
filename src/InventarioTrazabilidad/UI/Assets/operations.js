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
