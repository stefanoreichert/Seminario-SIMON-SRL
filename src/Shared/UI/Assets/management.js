let pendingSuccess = '';

document.addEventListener('livewire:navigated', () => {
    if (pendingSuccess && !document.querySelector('[data-server-status]')) {
        const notice = document.querySelector('[data-management-success]');
        if (notice) {
            notice.querySelector('p').textContent = pendingSuccess;
            notice.hidden = false;
        }
    }
    pendingSuccess = '';
});

function fieldsFor(form, submitter) {
    const fields = Object.create(null);
    const entries = new FormData(form);
    if (submitter?.name) entries.set(submitter.name, submitter.value);
    for (const [name, value] of entries) {
        if (value instanceof File) throw new Error('Este formulario requiere una carga de archivos específica.');
        const keys = name.replace(/\[([^\]]*)\]/g, '.$1').split('.');
        if (keys.some(key => ['__proto__', 'prototype', 'constructor'].includes(key))) continue;
        let target = fields;
        keys.forEach((key, index) => {
            if (index === keys.length - 1) {
                if (key === '') target.push(value);
                else target[key] = value;
            } else {
                target[key] ??= keys[index + 1] === '' || /^\d+$/.test(keys[index + 1]) ? [] : Object.create(null);
                target = target[key];
            }
        });
    }
    return fields;
}

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.closest('.simon-content') || event.defaultPrevented || form.hasAttribute('wire:submit')) return;
    const destination = new URL(form.action, location.href);
    if (destination.origin !== location.origin || !destination.pathname.startsWith('/admin/')) return;
    const dispatcher = document.querySelector('[data-management-actions]');
    if (!dispatcher || !window.Livewire) return;
    event.preventDefault();
    if (form.dataset.addressLoading === 'true') {
        const status = form.querySelector('[data-address-status]');
        if (status) status.textContent = 'Esperá a que termine la consulta de direcciones antes de confirmar.';
        return;
    }
    if (form.dataset.livewireBusy === 'true') return;
    const method = (form.querySelector('[name=_method]')?.value || form.method).toUpperCase();
    if (method === 'GET') {
        destination.search = new URLSearchParams(new FormData(form)).toString();
        window.Livewire.navigate(destination.href);
        return;
    }
    const controls = [...form.querySelectorAll('button[type=submit],input[type=submit]')];
    const previouslyDisabled = controls.map(control => control.disabled);
    const fields = fieldsFor(form, event.submitter);
    form.dataset.livewireBusy = 'true';
    controls.forEach(control => { control.disabled = true; });
    try {
        const wire = window.Livewire.find(dispatcher.getAttribute('wire:id'));
        const result = await wire.submit(destination.pathname, method, fields);
        if (result.ok) pendingSuccess = result.message;
        else {
            requestAnimationFrame(() => document.querySelector('[data-management-errors]')?.focus());
        }
    } catch {
        let status = form.querySelector('[data-submit-status]');
        if (!status) {
            status = document.createElement('p');
            status.dataset.submitStatus = '';
            status.setAttribute('role', 'alert');
            form.append(status);
        }
        status.textContent = 'No se pudo completar la operación. Verificá la conexión, la sesión y tus permisos antes de reintentar.';
    } finally {
        delete form.dataset.livewireBusy;
        controls.forEach((control, index) => { control.disabled = previouslyDisabled[index]; });
    }
});
