import { initializeForms } from '../../shared/forms';
import '../../shared/theme';

initializeForms();
document.querySelector('[data-password-toggle]')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    const input = document.getElementById(button.getAttribute('aria-controls'));
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
    button.title = show ? 'Ocultar contraseña' : 'Mostrar contraseña';
    button.setAttribute('aria-pressed', String(show));
});
