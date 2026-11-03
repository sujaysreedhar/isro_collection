/* Browser-only conveniences for the reference archive layouts. */
(() => {
    'use strict';
    const base = document.body.dataset.archiveBase;
    if (!base) return;
    const storagePrefix = `collection:${base}:`;
    const readStored = (key, fallback) => {
        try { return JSON.parse(localStorage.getItem(storagePrefix + key)) ?? fallback; }
        catch { return fallback; }
    };
    const writeStored = (key, value) => {
        try { localStorage.setItem(storagePrefix + key, JSON.stringify(value)); return true; }
        catch { return false; }
    };
    const readFavorites = () => {
        const stored = readStored('favorites', []);
        return Array.isArray(stored) ? stored.filter(id => Number.isSafeInteger(id) && id > 0) : [];
    };
    const resultBookmarks = document.querySelectorAll('[data-archive-favorite-id]');
    const renderBookmarks = () => {
        const favorites = new Set(readFavorites());
        resultBookmarks.forEach(button => {
            const saved = favorites.has(Number(button.dataset.archiveFavoriteId));
            button.setAttribute('aria-pressed', String(saved));
            button.setAttribute('aria-label', saved ? `Remove ${button.dataset.itemTitle} from Favorites` : `Save ${button.dataset.itemTitle} to Favorites`);
        });
    };
    renderBookmarks();
    resultBookmarks.forEach(button => button.addEventListener('click', () => {
        const id = Number(button.dataset.archiveFavoriteId);
        if (!Number.isSafeInteger(id) || id < 1) return;
        const favorites = new Set(readFavorites());
        const wasSaved = favorites.has(id);
        if (wasSaved) favorites.delete(id); else favorites.add(id);
        const saved = writeStored('favorites', [...favorites]);
        renderBookmarks();
        const status = document.querySelector('[data-search-favorite-status]');
        if (status) status.textContent = saved
            ? `${button.dataset.itemTitle} ${wasSaved ? 'removed from' : 'saved to'} favorites on this browser.`
            : 'Your browser could not save this favorite. Check whether local storage is enabled.';
    }));
    window.addEventListener('storage', event => {
        if (event.key === storagePrefix + 'favorites') renderBookmarks();
    });

    const continueCard = document.querySelector('[data-archive-continue]');
    const recent = readStored('recent', null);
    if (continueCard && recent && Number.isSafeInteger(recent.id) && recent.id > 0
        && typeof recent.title === 'string' && typeof recent.reg === 'string') {
        continueCard.href = `${base}/item/${recent.id}`;
        continueCard.querySelector('[data-continue-heading]').textContent = 'Continue Exploring';
        continueCard.querySelector('[data-continue-title]').textContent = recent.title;
        continueCard.querySelector('[data-continue-reg]').textContent = recent.reg;
        const image = continueCard.querySelector('[data-continue-image]');
        if (image && typeof recent.image === 'string') {
            try {
                const url = new URL(recent.image, location.href);
                if (['http:', 'https:'].includes(url.protocol)) image.src = url.href;
            } catch { /* Leave the server-rendered image in place. */ }
        }
    }

    const dataElement = document.getElementById('archive-item-data');
    if (!dataElement) return;
    let item;
    try { item = JSON.parse(dataElement.textContent); } catch { return; }
    const images = Array.isArray(item.images) ? item.images : [];
    const status = document.querySelector('[data-archive-status]');
    const say = message => { if (status) status.textContent = message; };
    if (item.remember) writeStored('recent', { id: item.id, title: item.title, reg: item.reg, image: item.image });

    const favoriteButton = document.querySelector('[data-archive-favorite]');
    const renderFavorite = () => {
        if (!favoriteButton) return;
        const saved = readFavorites().includes(item.id);
        favoriteButton.setAttribute('aria-pressed', String(saved));
        favoriteButton.querySelector('[data-favorite-label]').textContent = saved ? 'Added to Favorites' : 'Add to Favorites';
    };
    renderFavorite();
    favoriteButton?.addEventListener('click', () => {
        const favorites = new Set(readFavorites());
        const wasSaved = favorites.has(item.id);
        if (wasSaved) favorites.delete(item.id); else favorites.add(item.id);
        if (writeStored('favorites', [...favorites])) {
            renderFavorite();
            say(wasSaved ? 'Removed from favorites on this browser.' : 'Saved to favorites on this browser.');
        } else say('Your browser could not save this favorite. Check whether local storage is enabled.');
    });
    window.addEventListener('storage', event => {
        if (event.key === storagePrefix + 'favorites') renderFavorite();
    });

    const shareUrl = `${base}/item/${item.id}`;
    document.querySelector('[data-archive-share]')?.addEventListener('click', async () => {
        // Touch devices use the system sheet; desktop users get copy feedback.
        if (navigator.share && window.matchMedia('(pointer: coarse)').matches) {
            try { await navigator.share({ title: item.title, url: shareUrl }); return; }
            catch (error) { if (error.name === 'AbortError') return; }
        }
        try {
            await navigator.clipboard.writeText(shareUrl);
            say('Item link copied to clipboard.');
        } catch {
            const fallback = document.querySelector('.archive-share-fallback');
            if (fallback) {
                fallback.hidden = false;
                const input = fallback.querySelector('input');
                input.focus(); input.select();
                say('Copy the selected link to share this item.');
            }
        }
    });

    const mainImage = document.getElementById('main-viewer');
    const caption = document.getElementById('image-caption');
    const license = document.getElementById('archive-image-license');
    const download = document.getElementById('archive-download');
    const dialog = document.getElementById('archive-lightbox');
    const dialogImage = document.getElementById('archive-lightbox-image');
    let current = 0;
    let previousFocus = null;
    let previousOverflow = '';
    window.__mvCurrent = 0;

    const resetZoom = () => {
        dialogImage?.classList.remove('is-zoomed');
        if (dialogImage) dialogImage.style.transformOrigin = 'center';
    };
    const renderViewer = () => {
        const image = images[current];
        if (!image || !dialogImage) return;
        resetZoom();
        dialogImage.src = image.full || image.src;
        dialogImage.alt = `${item.title} - View ${current + 1}`;
        document.getElementById('archive-lightbox-caption').textContent =
            `${current + 1} / ${images.length}${image.caption ? ' · ' + image.caption : ''}`;
    };
    window.switchImage = element => {
        const index = Number(element.dataset.mvIndex);
        if (!Number.isInteger(index) || !images[index]) return;
        current = index;
        window.__mvCurrent = index;
        const image = images[index];
        document.getElementById('panorama-viewer')?.classList.add('hidden');
        if (mainImage) {
            mainImage.src = image.src;
            mainImage.style.display = 'block';
            mainImage.alt = `${item.title} - View ${index + 1}`;
            mainImage.dataset.caption = image.caption;
        }
        if (caption) { caption.textContent = image.caption; caption.hidden = !image.caption; }
        if (license) license.textContent = image.license || 'Not specified';
        if (download) { download.href = image.download || ''; download.hidden = !image.download; }
        document.querySelectorAll('.gallery-thumbnail').forEach(thumbnail => {
            const active = thumbnail === element;
            thumbnail.classList.toggle('active', active);
            thumbnail.closest('button')?.setAttribute('aria-pressed', String(active));
        });
        document.querySelectorAll('.pano-thumbnail img').forEach(image => image.classList.remove('border-blue-500'));
        if (dialog?.open) renderViewer();
    };
    if (!dialog || !images.length) return;

    const openViewer = () => {
        if (dialog.open) return;
        current = window.__mvCurrent || 0;
        if (!images[current]) current = 0;
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        renderViewer();
        dialog.showModal();
        document.body.style.overflow = 'hidden';
        dialog.querySelector('[data-viewer-close]').focus();
    };
    const closeViewer = () => dialog.close();
    const step = delta => {
        current = (current + delta + images.length) % images.length;
        const thumbnail = document.querySelector(`.gallery-thumbnail[data-mv-index="${current}"]`);
        if (thumbnail) window.switchImage(thumbnail); else renderViewer();
    };
    document.querySelectorAll('[data-open-viewer]').forEach(button => button.addEventListener('click', openViewer));
    mainImage?.addEventListener('click', openViewer);
    mainImage?.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openViewer(); }
    });
    dialog.querySelector('[data-viewer-close]').addEventListener('click', closeViewer);
    dialog.querySelectorAll('[data-viewer-step]').forEach(button => {
        button.hidden = images.length < 2;
        button.addEventListener('click', () => step(Number(button.dataset.viewerStep)));
    });
    dialog.addEventListener('close', () => {
        document.body.style.overflow = previousOverflow;
        resetZoom();
        previousFocus?.focus();
    });
    dialog.addEventListener('click', event => {
        if (event.target === dialog || event.target.classList.contains('archive-lightbox-stage')) closeViewer();
    });
    dialog.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft') { event.preventDefault(); step(-1); }
        if (event.key === 'ArrowRight') { event.preventDefault(); step(1); }
    });
    dialogImage.addEventListener('click', () => dialogImage.classList.toggle('is-zoomed'));
    dialogImage.addEventListener('pointermove', event => {
        if (!dialogImage.classList.contains('is-zoomed')) return;
        const bounds = dialogImage.parentElement.getBoundingClientRect();
        const x = Math.max(0, Math.min(100, (event.clientX - bounds.left) / bounds.width * 100));
        const y = Math.max(0, Math.min(100, (event.clientY - bounds.top) / bounds.height * 100));
        dialogImage.style.transformOrigin = `${x}% ${y}%`;
    });
})();
