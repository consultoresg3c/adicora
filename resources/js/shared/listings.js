import { request } from './http';

export function initializeListing(root = document) {
    const form = root.querySelector('[data-list-filters]');
    if (!form) return;
    const feedback = root.querySelector('[data-list-feedback]');
    let pending;

    async function update(url) {
        pending?.abort();
        const controller = new AbortController();
        pending = controller;
        const results = root.querySelector('[data-list-results]');
        results.setAttribute('aria-busy', 'true');
        feedback.hidden = false;
        feedback.textContent = 'Buscando…';
        try {
            const response = await request(url, { signal: controller.signal, headers: { 'X-Buko-Listing': 'true' } });
            if (controller.signal.aborted) return;
            // HTML de los partials Blade del propio listado; sus datos se escapan en el servidor.
            const template = document.createElement('template');
            template.innerHTML = response.data.html;
            const next = template.content.querySelector('[data-list-results]');
            if (!next || template.content.querySelector('script')) throw new Error('No se pudo actualizar el listado. Intenta nuevamente.');
            results.replaceWith(next);
            feedback.textContent = 'Listado actualizado.';
            root.dispatchEvent(new CustomEvent('buko:list-updated'));
        } catch (error) {
            if (!controller.signal.aborted) feedback.textContent = error.message;
        } finally {
            if (pending === controller) root.querySelector('[data-list-results]')?.removeAttribute('aria-busy');
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const url = new URL(form.action, location.href);
        for (const [name, value] of new FormData(form)) {
            if (String(value).trim() !== '') url.searchParams.set(name, value);
        }
        update(url.href);
    });
    root.addEventListener('click', (event) => {
        const link = event.target.closest('.list-pagination a, .list-filter-actions a');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (link.closest('.list-filter-actions')) form.reset();
        update(link.href);
    });
}
