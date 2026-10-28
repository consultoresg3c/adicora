import { request } from './http';
import { closeDialog } from './dialogs';

export function initializeForms(root = document) {
    root.querySelectorAll('[data-async-form]').forEach((form) => {
        if (form.dataset.initialized) return;
        form.dataset.initialized = 'true';
        const fields = [...form.querySelectorAll('input:not([type="hidden"]), select, textarea')];
        const touched = new Set();
        const feedback = form.querySelector('[data-form-feedback]');
        form.addEventListener('reset', () => {
            touched.clear();
            form.querySelectorAll('[data-error]').forEach((node) => { node.textContent = ''; });
            fields.forEach((field) => field.removeAttribute('aria-invalid'));
            feedback.textContent = '';
        });

        const fieldKey = (name) => name.replace(/\[([^\]]*)\]/g, (_, key) => key ? `.${key}` : '');
        const errorNode = (field) =>
            [...form.querySelectorAll('[data-error]')].find(
                (node) => node.dataset.error === fieldKey(field.name),
            );
        const showError = (field, message) => {
            if (errorNode(field)) errorNode(field).textContent = message;
            if (message) field.setAttribute('aria-invalid', 'true');
            else field.removeAttribute('aria-invalid');
        };
        const validate = (field) => {
            if (field.type === 'checkbox' || field.disabled) return true;
            let message = '';
            if (field.validity.valueMissing) message = 'Completa este campo.';
            else if (field.validity.typeMismatch) message = 'Escribe un correo válido.';
            else if (field.validity.tooShort) message = `Escribe al menos ${field.minLength} caracteres.`;
            else if (field.validity.tooLong) message = 'El valor supera el máximo permitido.';
            else if (field.validity.badInput) message = 'Escribe un número válido.';
            else if (field.validity.rangeUnderflow) message = `El valor mínimo es ${field.min}.`;
            else if (field.validity.rangeOverflow) message = `El valor máximo es ${field.max}.`;
            showError(field, message);
            return !message;
        };

        fields.forEach((field) => {
            field.addEventListener('blur', () => {
                touched.add(field);
                validate(field);
            });
            field.addEventListener('input', () => {
                if (touched.has(field) || field.hasAttribute('aria-invalid')) validate(field);
            });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (form.dataset.busy) return;
            form.querySelectorAll('[data-error]').forEach((node) => {
                node.textContent = '';
            });
            fields.forEach((field) => field.removeAttribute('aria-invalid'));
            feedback.textContent = '';
            const valid = fields
                .map((field) => {
                    touched.add(field);
                    return validate(field);
                })
                .every(Boolean);
            if (!valid) {
                form.querySelector('[aria-invalid="true"]')?.focus();
                return;
            }

            const button = form.querySelector('[data-submit]');
            const labelNode = button.querySelector('[data-submit-label]') ?? button;
            const label = labelNode.textContent;
            labelNode.textContent = 'Procesando…';
            button.disabled = true;
            form.dataset.busy = 'true';
            form.setAttribute('aria-busy', 'true');
            try {
                const result = await request(form.action, { method: 'POST', body: new FormData(form) });
                if (!form.isConnected) return;
                feedback.textContent = result.message;
                form.removeAttribute('aria-busy');
                if (result.data.redirect) {
                    await closeDialog(form.closest('dialog'));
                    location.assign(result.data.redirect);
                }
            } catch (error) {
                if (!form.isConnected) return;
                feedback.textContent = error.message;
                for (const [name, messages] of Object.entries(error.errors ?? {})) {
                    const field = fields.find(
                        (item) => fieldKey(item.name) === name || (item.name.endsWith('[]') && fieldKey(item.name) === name.split('.')[0]),
                    );
                    if (field) showError(field, messages[0]);
                    else {
                        const node = [...form.querySelectorAll('[data-error]')].find(
                            (item) => item.dataset.error === name,
                        );
                        if (node) node.textContent = messages[0];
                        else feedback.textContent = `${error.message} ${messages[0]}`;
                    }
                }
                (form.querySelector('[aria-invalid="true"]') ?? feedback).focus();
            } finally {
                labelNode.textContent = label;
                button.disabled = false;
                delete form.dataset.busy;
                form.removeAttribute('aria-busy');
            }
        });
    });
}
