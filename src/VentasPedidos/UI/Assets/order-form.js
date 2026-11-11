const initializedForms = new WeakSet();
const initializeForms = () => document.querySelectorAll('[data-order-form]').forEach((form) => {
    if (initializedForms.has(form)) return;
    initializedForms.add(form);
    const validateDuplicates = () => {
        const fields = [...form.querySelectorAll('[data-order-lines] select[name$="[variant_id]"]')];
        fields.forEach((field) => {
            const duplicate = field.value !== '' && fields.some((other) => other !== field && other.value === field.value);
            field.setCustomValidity(duplicate ? 'Este artículo está repetido. Combiná sus cantidades en una sola línea.' : '');
        });
    };

    form.addEventListener('change', validateDuplicates);
    const lines = form.querySelector('[data-order-lines]');
    if (lines) new MutationObserver(validateDuplicates).observe(lines, { childList: true, subtree: true });
    form.addEventListener('submit', (event) => {
        if (form.querySelector('[data-address-loading]')?.getClientRects().length || form.querySelector('[data-lines-loading]')?.getClientRects().length) {
            event.preventDefault();
            form.querySelector('[data-address-status]').textContent = 'Esperá a que termine la actualización del formulario antes de confirmar.';
        }
    });
    validateDuplicates();
});

document.addEventListener('livewire:navigated', initializeForms);
initializeForms();
