import { initializeManagement } from '../../shared/management';

function initializeMenuForm(dialog) {
    const parent = dialog.querySelector('[data-parent-menu]');
    const childFields = dialog.querySelector('[data-child-menu-fields]');
    if (!parent || !childFields) return;
    const syncFields = () => {
        childFields.hidden = parent.checked;
        childFields.querySelectorAll('select').forEach((field) => {
            field.disabled = parent.checked;
            field.required = !parent.checked;
        });
    };
    parent.addEventListener('change', syncFields);
    syncFields();
    const select = dialog.querySelector('[data-icon-select]');
    select?.addEventListener('change', () => {
        const icon = [...dialog.querySelectorAll('[data-icon-template]')].find((template) => template.dataset.iconTemplate === select.value);
        if (icon) dialog.querySelector('[data-icon-preview]').replaceChildren(icon.content.cloneNode(true));
    });
}

document.addEventListener('buko:dialog-ready', (event) => initializeMenuForm(event.target));
initializeManagement();
