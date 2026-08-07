document.addEventListener('DOMContentLoaded', () => {
    const dayInfoModal = document.getElementById('dayInfoModal');
    if (dayInfoModal) {
        const modalTitle = document.getElementById('dayInfoModalLabel');
        const modalBody = document.getElementById('dayInfoModalBody');

        dayInfoModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button || !modalBody) return;

            const title = button.getAttribute('data-day-title') || 'Detalle del día';
            const sourceSelector = button.getAttribute('data-day-source');
            const source = sourceSelector ? document.querySelector(sourceSelector) : null;

            if (modalTitle) {
                modalTitle.textContent = title;
            }
            modalBody.innerHTML = source ? source.innerHTML : '<p class="text-muted mb-0">Sin información.</p>';
        });
    }

    const estadoSelect = document.getElementById('estado');
    const fechaPagoGroup = document.getElementById('fecha-pago-group');

    if (estadoSelect && fechaPagoGroup) {
        estadoSelect.addEventListener('change', () => {
            fechaPagoGroup.style.display = estadoSelect.value === 'pagado' ? '' : 'none';
        });
    }

    const tipoSelect = document.getElementById('tipo_pago_id');
    const chipButtons = document.querySelectorAll('.chip-btn');

    chipButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const tipoId = btn.dataset.tipoId;
            if (tipoSelect) {
                tipoSelect.value = tipoId;
            }
            chipButtons.forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });

    if (tipoSelect) {
        const syncChips = () => {
            chipButtons.forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.tipoId === tipoSelect.value);
            });
        };
        tipoSelect.addEventListener('change', syncChips);
        syncChips();
    }

    const selectAll = document.getElementById('select-all');
    const cuentaChecks = document.querySelectorAll('.cuenta-check');
    const totalEl = document.getElementById('total-seleccionado');

    const formatTotal = (amount) => new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(amount);

    const updateTotal = () => {
        if (!totalEl) return;
        let total = 0;
        cuentaChecks.forEach((check) => {
            if (check.checked) {
                total += parseFloat(check.dataset.monto || '0');
            }
        });
        totalEl.textContent = 'Total: ' + formatTotal(total);
    };

    if (selectAll && cuentaChecks.length) {
        selectAll.addEventListener('change', () => {
            cuentaChecks.forEach((check) => {
                check.checked = selectAll.checked;
            });
            updateTotal();
        });

        cuentaChecks.forEach((check) => {
            check.addEventListener('change', () => {
                selectAll.checked = [...cuentaChecks].every((c) => c.checked);
                updateTotal();
            });
        });

        updateTotal();
    }
});
