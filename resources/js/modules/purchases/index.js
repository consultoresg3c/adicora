import { initializeManagement } from '../../shared/management';

// El transporte compartido envía FormData; la intención debe quedar dentro del formulario.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('[data-purchase-form]')) return;
    form.elements.intent.value = event.submitter?.dataset.intent ?? 'save';
}, true);

initializeManagement();
