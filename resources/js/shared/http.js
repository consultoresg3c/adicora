export class RequestError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

export async function request(url, options = {}) {
    let response;
    try {
        response = await fetch(url, {
            ...options,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                ...options.headers,
            },
        });
    } catch (error) {
        if (error.name === 'AbortError') throw error;
        throw new RequestError(
            'No hay conexión con el servidor. Revisa tu conexión e intenta nuevamente.',
            0,
        );
    }

    let payload;
    try {
        payload = await response.json();
    } catch {
        throw new RequestError(
            'El servidor devolvió una respuesta inesperada. Recarga la página.',
            response.status,
        );
    }

    if (!response.ok || payload.success !== true) {
        throw new RequestError(
            payload.message ?? 'No pudimos completar la operación.',
            response.status,
            payload.errors,
        );
    }

    return payload;
}
