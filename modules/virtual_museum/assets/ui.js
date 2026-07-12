export function escapeMuseumHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
}

export function setPanelVisibility(panel, open) {
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (open) {
        panel.classList.remove('hidden');
        requestAnimationFrame(() => panel.classList.add('open'));
    } else {
        panel.classList.remove('open');
        setTimeout(() => panel.classList.add('hidden'), 400);
    }
}

export function showFallbackUI() {
    document.getElementById('museum-loader')?.classList.add('hidden');
    document.getElementById('museum-hud')?.classList.add('hidden');
    document.getElementById('museum-welcome')?.classList.add('hidden');
    document.getElementById('webgl-fallback')?.classList.remove('hidden');
}
