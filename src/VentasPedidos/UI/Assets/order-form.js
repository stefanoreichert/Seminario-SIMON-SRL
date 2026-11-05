document.querySelectorAll('[data-order-form]').forEach((form) => {
    const container = form.querySelector('[data-order-lines]');
    const template = form.querySelector('[data-order-line-template]');
    const add = form.querySelector('[data-add-line]');
    const maximum = Number(form.dataset.maxLines);

    const validateDuplicates = () => {
        const fields = [...container.querySelectorAll('select[name$="[variant_id]"]')];
        fields.forEach((field) => {
            const duplicate = field.value !== '' && fields.some((other) => other !== field && other.value === field.value);
            field.setCustomValidity(duplicate ? 'Este artículo está repetido. Combiná sus cantidades en una sola línea.' : '');
        });
    };

    const updateRows = () => {
        const rows = [...container.querySelectorAll('[data-order-line]')];
        rows.forEach((row, index) => {
            row.querySelector('[data-line-number]').textContent = String(index + 1);
            row.querySelector('[data-remove-line]').disabled = rows.length <= 1;
            row.querySelectorAll('[data-line-error]').forEach((error) => {
                error.textContent = error.textContent.replace(/línea \d+/g, `línea ${index + 1}`);
            });
            row.querySelectorAll('[name], [id], [for], [aria-describedby]').forEach((field) => {
                ['name', 'id', 'for', 'aria-describedby'].forEach((attribute) => {
                    const value = field.getAttribute(attribute);
                    if (value === null) return;
                    field.setAttribute(attribute, value.replace(/lines\[(?:\d+|__INDEX__)\]/g, `lines[${index}]`).replace(/order-line-(?:\d+|__INDEX__)-/g, `order-line-${index}-`));
                });
            });
        });
        add.disabled = rows.length >= maximum;
        form.querySelector('[data-line-count]').textContent = `${rows.length} líneas de artículos. Máximo: ${maximum}.`;
        validateDuplicates();
    };

    add.addEventListener('click', () => {
        if (container.querySelectorAll('[data-order-line]').length >= maximum) return;
        container.appendChild(template.content.cloneNode(true));
        updateRows();
        container.lastElementChild.querySelector('select').focus();
    });

    container.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-line]');
        if (!remove || !container.contains(remove) || container.querySelectorAll('[data-order-line]').length <= 1) return;
        remove.closest('[data-order-line]').remove();
        updateRows();
        add.focus();
    });
    container.addEventListener('change', validateDuplicates);
    form.addEventListener('submit', (event) => {
        if (form.dataset.addressLoading === 'true') {
            event.preventDefault();
            form.querySelector('[data-address-status]').textContent = 'Esperá a que termine la consulta de direcciones antes de confirmar.';
        }
    });
    updateRows();
});
