const root = document.documentElement;
const toggles = document.querySelectorAll('[data-theme-toggle]');

function updateLabels() {
    toggles.forEach((button) => {
        const label = root.dataset.theme === 'dark' ? 'Usar tema claro' : 'Usar tema oscuro';
        button.setAttribute('aria-label', label);
        button.title = label;
        if (!button.querySelector('.icon')) button.textContent = label;
    });
}

toggles.forEach((button) => {
    button.addEventListener('click', () => {
        root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem('buko-theme', root.dataset.theme);
        } catch {
            /* El tema sigue disponible sin persistencia. */
        }
        updateLabels();
    });
});

matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
    try {
        if (localStorage.getItem('buko-theme')) return;
    } catch {
        /* Respetar el sistema sin almacenamiento. */
    }
    root.dataset.theme = event.matches ? 'dark' : 'light';
    updateLabels();
});

updateLabels();
