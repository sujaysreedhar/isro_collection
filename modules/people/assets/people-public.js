(() => {
    'use strict';
    const root = document.querySelector('.bio-profile');
    if (!root || !('IntersectionObserver' in window)) return;
    const links = Array.from(root.querySelectorAll('.bio-page-nav a[href^="#"]'));
    const visible = new Map();
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) visible.set(entry.target.id, entry.boundingClientRect.top);
            else visible.delete(entry.target.id);
        });
        if (!visible.size) return;
        const current = Array.from(visible).sort((a, b) => Math.abs(a[1]) - Math.abs(b[1]))[0][0];
        links.forEach(link => {
            if (link.hash === '#' + current) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    }, {rootMargin: '-15% 0px -45% 0px', threshold: 0});
    root.querySelectorAll('[data-bio-section]').forEach(section => observer.observe(section));
    links.forEach(link => link.addEventListener('click', () => {
        links.forEach(other => other.removeAttribute('aria-current'));
        link.setAttribute('aria-current', 'location');
    }));
})();
