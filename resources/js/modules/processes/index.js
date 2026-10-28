import { initializeManagement } from '../../shared/management';
import { initializeForms } from '../../shared/forms';

function updateStartOptions(root) {
    const start = root.querySelector('[data-process-start]');
    const status = root.querySelector('[data-start-status]');
    if (!start || !status) return;
    status.hidden = !start.checked;
}

function updateFieldOptions(root) {
    const form = root.matches?.('[data-stage-form]') ? root : root.querySelector('[data-stage-form]');
    if (!form) return;
    const review = form.querySelector('[data-review-only]').checked;
    for (const row of form.querySelectorAll('[data-field-rule]')) {
        const edit = row.querySelector('input[type="radio"][value="edit"]');
        const read = row.querySelector('input[type="radio"][value="read"]');
        const required = row.querySelector('[data-field-required]');
        if (review && edit.checked) read.checked = true;
        edit.disabled = review;
        required.disabled = review || !edit.checked;
        if (required.disabled) required.checked = false;
    }
    const type = form.querySelector('[data-new-field-type]').value;
    form.querySelector('[data-text-length]').hidden = type !== 'text';
    form.querySelector('[data-decimal-places]').hidden = type !== 'numeric';
}

initializeManagement();
document.addEventListener('buko:list-updated', () => initializeForms(document));
document.addEventListener('buko:dialog-ready', (event) => {
    updateStartOptions(event.target);
    updateFieldOptions(event.target);
    event.target.querySelector('[data-field-confirm] [data-close-dialog]')?.focus();
});
document.addEventListener('change', (event) => {
    if (event.target.matches('[data-process-start]')) updateStartOptions(event.target.form);
    if (event.target.matches('[data-review-only], [data-new-field-type], [data-field-rule] input')) updateFieldOptions(event.target.form);
});
