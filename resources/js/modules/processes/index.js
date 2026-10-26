import { initializeManagement } from '../../shared/management';
import { initializeForms } from '../../shared/forms';

initializeManagement();
document.addEventListener('buko:list-updated', () => initializeForms(document));
