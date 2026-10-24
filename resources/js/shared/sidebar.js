import { animate, canAnimate } from './motion';

export function initializeSidebar() {
    const shell = document.querySelector('[data-app-shell]');
    const sidebar = document.getElementById('navigation');
    const trigger = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const content = shell?.querySelector('.app-main');
    if (!shell || !sidebar || !trigger) return;
    const mobile = matchMedia('(max-width: 767px)');
    let desktopCollapsed = false;
    try { desktopCollapsed = localStorage.getItem('buko-sidebar-collapsed') === 'true'; } catch { /* Es opcional. */ }

    const updateTrigger = (expanded) => {
        trigger.setAttribute('aria-expanded', String(expanded));
        trigger.setAttribute('aria-label', expanded ? 'Contraer menú' : 'Abrir menú');
        trigger.title = expanded ? 'Contraer menú' : 'Abrir menú';
    };

    function syncViewport() {
        sidebar.inert = mobile.matches;
        content.inert = false;
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        shell.classList.toggle('sidebar-collapsed', !mobile.matches && desktopCollapsed);
        shell.classList.remove('sidebar-mobile-open');
        backdrop.hidden = true;
        document.body.classList.remove('has-mobile-navigation');
        updateTrigger(!mobile.matches && !desktopCollapsed);
    }

    async function closeMobile(keyboard = false, restoreFocus = true) {
        if (!shell.classList.contains('sidebar-mobile-open')) return;
        if (!keyboard) await animate(sidebar, [{ transform: 'translateX(0)', opacity: 1 }, { transform: 'translateX(-24px)', opacity: 0 }], 180);
        shell.classList.remove('sidebar-mobile-open');
        backdrop.hidden = true;
        sidebar.inert = true;
        content.inert = false;
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        document.body.classList.remove('has-mobile-navigation');
        updateTrigger(false);
        if (restoreFocus) trigger.focus({ preventScroll: true });
    }

    trigger.addEventListener('click', async (event) => {
        if (mobile.matches) {
            if (shell.classList.contains('sidebar-mobile-open')) { closeMobile(event.detail === 0); return; }
            shell.classList.add('sidebar-mobile-open');
            sidebar.inert = false;
            content.inert = true;
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
            backdrop.hidden = false;
            document.body.classList.add('has-mobile-navigation');
            updateTrigger(true);
            if (event.detail !== 0) animate(sidebar, [{ transform: 'translateX(-24px)', opacity: 0 }, { transform: 'translateX(0)', opacity: 1 }], 260);
            sidebar.querySelector('a, button')?.focus({ preventScroll: true });
            return;
        }
        const before = document.querySelector('.app-main').getBoundingClientRect();
        desktopCollapsed = !desktopCollapsed;
        shell.classList.toggle('sidebar-collapsed', desktopCollapsed);
        updateTrigger(!desktopCollapsed);
        try { localStorage.setItem('buko-sidebar-collapsed', String(desktopCollapsed)); } catch { /* Es opcional. */ }
        if (event.detail !== 0) {
            const after = document.querySelector('.app-main').getBoundingClientRect();
            animate(document.querySelector('.app-main'), [{ transform: `translateX(${before.left - after.left}px)` }, { transform: 'translateX(0)' }], 260);
        }
    });

    backdrop?.addEventListener('click', () => closeMobile());
    sidebar.querySelector('[data-sidebar-close]')?.addEventListener('click', (event) => closeMobile(event.detail === 0));
    document.addEventListener('keydown', (event) => {
        if (!mobile.matches || !shell.classList.contains('sidebar-mobile-open')) return;
        if (event.key === 'Escape') { event.preventDefault(); closeMobile(true); }
        if (event.key !== 'Tab') return;
        const controls = [...sidebar.querySelectorAll('a[href], button')].filter((control) => control.getClientRects().length && !control.closest('[hidden]'));
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });

    function initializeDisclosures() {
        sidebar.querySelectorAll('[data-nav-disclosure]').forEach((button) => {
            if (button.dataset.initialized) return;
            button.dataset.initialized = 'true';
            const panel = document.getElementById(button.getAttribute('aria-controls'));
            let operation = 0;
            button.addEventListener('click', async (event) => {
                if (!mobile.matches && desktopCollapsed) {
                    desktopCollapsed = false;
                    shell.classList.remove('sidebar-collapsed');
                    updateTrigger(true);
                    try { localStorage.setItem('buko-sidebar-collapsed', 'false'); } catch { /* Es opcional. */ }
                }
                const ticket = ++operation;
                const open = button.getAttribute('aria-expanded') === 'true';
                const links = [...sidebar.querySelectorAll('.nav-group')];
                const positions = new Map(links.filter((element) => element.getClientRects().length).map((element) => [element, element.getBoundingClientRect().top]));
                button.setAttribute('aria-expanded', String(!open));
                if (open && event.detail !== 0) await animate(panel, [{ opacity: 1, transform: 'translateY(0)' }, { opacity: 0, transform: 'translateY(-4px)' }], 130);
                if (ticket !== operation) return;
                panel.hidden = open;
                if (event.detail !== 0 && canAnimate()) {
                    links.forEach((element) => {
                        if (!element.getClientRects().length) return;
                        const delta = positions.get(element) - element.getBoundingClientRect().top;
                        if (Number.isFinite(delta) && delta) animate(element, [{ transform: `translateY(${delta}px)` }, { transform: 'translateY(0)' }], 230);
                    });
                    if (!open) animate(panel, [{ opacity: 0, transform: 'translateY(-5px)' }, { opacity: 1, transform: 'translateY(0)' }], 230);
                }
            });
        });
    }

    mobile.addEventListener('change', syncViewport);
    initializeDisclosures();
    syncViewport();
}
