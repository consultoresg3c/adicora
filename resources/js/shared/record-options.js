// Interacciones de formulario compartidas para opciones y detalle impreso.
export function initializeRecordOptions(root = document) {
    const printRoot = document.querySelector('[data-record-print-root]');
    const returnFocus = new WeakMap();

    root.addEventListener('click', (event) => {
        const menu = event.target.closest('[data-record-options]');
        root.querySelectorAll('[data-record-options][open]').forEach((item) => {
            if (item !== menu && !event.target.closest('#record-confirm-dialog')) item.open = false;
        });

        const deletion = event.target.closest('[data-dialog-target="record-confirm-dialog"]');
        if (deletion && menu) {
            returnFocus.set(document.getElementById('record-confirm-dialog'), menu.querySelector('summary'));
        }

        const trigger = event.target.closest('[data-record-print]');
        if (!trigger || !printRoot) return;
        const template = document.getElementById(trigger.dataset.recordPrint);
        if (!(template instanceof HTMLTemplateElement)) return;
        if (menu) {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
        printRoot.replaceChildren(template.content.cloneNode(true));
        document.body.classList.add('record-printing');
        window.print();
    });

    root.addEventListener('keydown', (event) => {
        const menu = event.target.closest('[data-record-options][open]');
        if (event.key !== 'Escape' || !menu) return;
        menu.open = false;
        menu.querySelector('summary').focus();
        event.preventDefault();
    });

    document.getElementById('record-confirm-dialog')?.addEventListener('close', (event) => {
        const summary = returnFocus.get(event.target);
        if (!summary?.isConnected) return;
        summary.closest('details').open = false;
        summary.focus();
        returnFocus.delete(event.target);
    });

    window.addEventListener('afterprint', () => {
        document.body.classList.remove('record-printing');
        printRoot?.replaceChildren();
    });
}
