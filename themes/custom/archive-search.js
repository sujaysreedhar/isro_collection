(() => {
    'use strict';
    const form = document.getElementById('archive-filter-form');
    if (!form) return;
    form.closest('.archive-search-layout').dataset.enhanced = 'true';
    form.dataset.autoFilters = 'true';
    const yearStart = form.elements.namedItem('year_start');
    const yearEnd = form.elements.namedItem('year_end');
    const validateYears = () => {
        const inverted = yearStart.value !== '' && yearEnd.value !== ''
            && Number(yearStart.value) > Number(yearEnd.value);
        yearEnd.setCustomValidity(inverted ? 'The end year must be the same as or later than the start year.' : '');
        return !inverted;
    };
    yearStart.addEventListener('input', validateYears);
    yearEnd.addEventListener('input', validateYears);
    form.addEventListener('submit', event => {
        if (!validateYears()) { event.preventDefault(); yearEnd.reportValidity(); }
    });
    form.querySelectorAll('input[type="checkbox"]').forEach(input => {
        input.addEventListener('change', () => { if (validateYears()) form.requestSubmit(); else yearEnd.reportValidity(); });
    });
    document.querySelectorAll('[data-submit-change]').forEach(select => {
        select.addEventListener('change', () => select.form.requestSubmit());
    });

    // Move the same form into a native modal on narrow screens; its state stays intact.
    const dialog = document.getElementById('archive-filter-dialog');
    const sidebar = document.getElementById('archive-filter-panel');
    const home = document.getElementById('archive-filter-home');
    const openButton = document.querySelector('[data-open-filters]');
    const closeButton = document.querySelector('[data-close-filters]');
    let priorOverflow = '';
    openButton.addEventListener('click', () => {
        if (dialog.open) return;
        priorOverflow = document.body.style.overflow;
        dialog.querySelector('[data-filter-dialog-content]').append(sidebar);
        dialog.showModal();
        document.body.style.overflow = 'hidden';
        openButton.setAttribute('aria-expanded', 'true');
        closeButton.focus();
    });
    closeButton.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        home.append(sidebar);
        document.body.style.overflow = priorOverflow;
        openButton.setAttribute('aria-expanded', 'false');
        openButton.focus();
    });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    });
    window.matchMedia('(min-width: 960px)').addEventListener('change', event => {
        if (event.matches && dialog.open) dialog.close();
    });
})();
