const preference = matchMedia('(prefers-reduced-motion: reduce)');
const easing = 'cubic-bezier(.22, 1, .36, 1)';

export function canAnimate() {
    return !preference.matches && typeof Element.prototype.animate === 'function';
}

export async function animate(element, keyframes, duration = 240) {
    if (!element || !canAnimate()) return;
    const animation = element.animate(keyframes, { duration, easing });
    try { await animation.finished; } catch { /* Una interacción posterior puede cancelar la anterior. */ }
}

