import { animate } from './motion';

const closing = new WeakSet();

export async function closeDialog(dialog, { keyboard = false } = {}) {
    if (!dialog?.open || closing.has(dialog) || dialog.querySelector('[aria-busy="true"]')) return;
    closing.add(dialog);
    if (!keyboard) {
        await animate(dialog, [
            { opacity: 1, transform: 'translateY(0) scale(1)' },
            { opacity: 0, transform: 'translateY(4px) scale(.99)' },
        ], 150);
    }
    dialog.close();
    closing.delete(dialog);
    dialog.dispatchEvent(new CustomEvent('buko:dialog-closed'));
}

export function openDialog(dialog, { keyboard = false } = {}) {
    if (!dialog || dialog.open) return;
    dialog.showModal();
    dialog.querySelector('input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea')?.focus({ preventScroll: true });
    if (!keyboard) {
        animate(dialog, [
            { opacity: 0, transform: 'translateY(10px) scale(.985)' },
            { opacity: 1, transform: 'translateY(0) scale(1)' },
        ], 250);
    }
}

export function initializeDialogs(root = document) {
    function mountTemplate(dialog, templateId) {
        const template = document.getElementById(templateId);
        if (!(template instanceof HTMLTemplateElement)) return false;
        dialog.querySelector('[data-dialog-body]').replaceChildren(template.content.cloneNode(true));
        dialog.querySelector('[data-dialog-title]').textContent = template.dataset.dialogTitle;
        dialog.dispatchEvent(new CustomEvent('buko:dialog-ready', { bubbles: true }));
        return true;
    }

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-dialog-template]');
        if (!trigger || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const dialog = document.getElementById(trigger.dataset.dialogTarget ?? 'record-dialog');
        if (!dialog || !mountTemplate(dialog, trigger.dataset.dialogTemplate)) return;
        event.preventDefault();
        openDialog(dialog, { keyboard: event.detail === 0 });
    });

    root.querySelectorAll('dialog').forEach((dialog) => {
        if (dialog.dataset.dialogInitialized) return;
        dialog.dataset.dialogInitialized = 'true';
        dialog.addEventListener('close', () => {
            if (dialog.open) return;
            dialog.querySelector('[data-dialog-body]').replaceChildren();
            if (!dialog.hasAttribute('data-auto-open')) return;
            const url = new URL(location.href);
            url.searchParams.delete('editar');
            url.searchParams.delete('nuevo');
            history.replaceState(history.state, '', url);
        });
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDialog(dialog, { keyboard: true });
        });
        dialog.addEventListener('click', (event) => {
            if (event.target.closest('[data-close-dialog]')) {
                event.preventDefault();
                closeDialog(dialog, { keyboard: event.detail === 0 });
                return;
            }
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
                closeDialog(dialog);
            }
        });
        if (dialog.hasAttribute('data-auto-open')) {
            dialog.close();
            if (mountTemplate(dialog, dialog.dataset.initialTemplate)) openDialog(dialog);
        }
    });
}
