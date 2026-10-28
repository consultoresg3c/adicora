import { initializeForms } from './forms';
import { initializeDialogs } from './dialogs';
import { initializeListing } from './listings';
import { initializeRecordOptions } from './record-options';

export function initializeManagement(root = document) {
    initializeForms(root);
    root.addEventListener('buko:dialog-ready', (event) => initializeForms(event.target));
    initializeDialogs(root);
    initializeRecordOptions(root);
    initializeListing(root);
}
