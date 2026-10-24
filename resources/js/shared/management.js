import { initializeForms } from './forms';
import { initializeDialogs } from './dialogs';
import { initializeListing } from './listings';

export function initializeManagement(root = document) {
    initializeForms(root);
    root.addEventListener('buko:dialog-ready', (event) => initializeForms(event.target));
    initializeDialogs(root);
    initializeListing(root);
}
