// Shared icon button for the compact media selector (schema fields and inspector).
export function mediaIconButton(icon, name, title, callback, className) {
    const node = document.createElement('button'); node.className = `cms-media-field-button ${className}`; node.type = 'button'; node.title = title; node.setAttribute('aria-label', name);
    const glyph = document.createElement('i'); glyph.className = `fa-solid ${icon}`; glyph.setAttribute('aria-hidden', 'true'); node.append(glyph);
    node.addEventListener('click', callback); return node;
}
