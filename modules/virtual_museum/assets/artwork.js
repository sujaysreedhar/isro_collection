export function artworkDimensions(ratio, limits = {}) {
    const maxHeight = limits.maxHeight || 2.3;
    const maxWidth = limits.maxWidth || 3.2;
    const safeRatio = Number.isFinite(ratio) && ratio > 0 ? ratio : 1.4;
    let height = maxHeight;
    let width = maxHeight * safeRatio;
    if (width > maxWidth) {
        width = maxWidth;
        height = maxWidth / safeRatio;
    }
    return { w: width, h: height };
}

export function truncatedLabel(text, maxCharacters = 80) {
    const value = String(text || '').trim();
    return value.length > maxCharacters ? value.slice(0, maxCharacters - 1).trimEnd() + '…' : value;
}
