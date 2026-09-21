(function (root) {
    'use strict';
    function escape(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);
    }
    function itemId(value) {
        const text = String(value ?? '');
        if (!/^[1-9][0-9]*$/.test(text)) return null;
        const id = Number(text);
        return Number.isSafeInteger(id) ? id : null;
    }
    const api = { escape, itemId };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    root.AtlasText = api;
})(typeof window !== 'undefined' ? window : globalThis);
