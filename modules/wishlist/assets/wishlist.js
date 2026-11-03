(() => {
    'use strict';
    const dataElement = document.getElementById('wl-data');
    if (!dataElement) return;
    const data = JSON.parse(dataElement.textContent);
    const byId = new Map(data.items.map(item => [String(item.id), item]));
    const form = document.getElementById('wl-form');
    const dialog = document.getElementById('wl-dialog');
    const offers = document.getElementById('wl-offers');
    const preview = document.getElementById('wl-image-preview');
    let offerIndex = 0;
    let objectUrl = null;
    const today = () => { const date = new Date(); return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; };

    async function request(action, payload) {
        payload.set('action', action);
        payload.set('csrf_token', data.csrf);
        const response = await fetch(data.ajaxUrl, { method: 'POST', body: payload, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        let result;
        try { result = await response.json(); } catch (_) { throw new Error('The server returned an unexpected response. Reload the page and try again.'); }
        if (!response.ok || !result.success) throw new Error(result.message || 'The request could not be completed.');
        return result;
    }

    function showError(element, error) { element.textContent = error.message || String(error); element.hidden = false; element.scrollIntoView({ block: 'nearest' }); }
    function notice(error) { showError(document.getElementById('wl-notice'), error); }
    function setBusy(button, busy, text) { button.disabled = busy; button.textContent = busy ? 'Saving…' : text; }

    function appendOffer(offer = {}) {
        const index = offerIndex++;
        const fieldset = document.createElement('fieldset');
        fieldset.className = 'wl-offer';
        fieldset.innerHTML = `<legend>Seller offer</legend><input type="hidden" data-field="id"><div class="wl-offer-grid">
            <label>Seller name<input data-field="name" maxlength="255" required></label>
            <label>Price<input data-field="amount" type="number" step="0.01" min="0" max="999999999999.99" inputmode="decimal"></label>
            <label>Currency<input data-field="currency" maxlength="3" pattern="[A-Za-z]{3}" placeholder="INR / USD / EUR"></label>
            <label class="wl-offer-link">Seller link<input data-field="url" type="url" maxlength="2000" placeholder="https://…"></label>
            <label>Shipping<input data-field="shipping" type="number" step="0.01" min="0" max="999999999999.99" inputmode="decimal" placeholder="Blank = unknown; 0 = free"></label>
            <label>Availability<select data-field="availability"><option value="unknown">Not confirmed</option><option value="available">Available</option><option value="unavailable">Unavailable</option><option value="sold">Sold</option></select></label>
            <label>Last checked<input data-field="last_checked" type="date"></label>
            <label class="wl-offer-notes">Private seller notes<textarea data-field="notes" rows="2" maxlength="10000"></textarea></label>
            </div><div class="wl-offer-footer"><p data-offer-help></p><div class="wl-actions"><button type="button" class="wl-button" data-checked>Checked today</button><button type="button" class="wl-button wl-danger" data-remove>Remove offer</button></div></div>`;
        const values = { id: offer.id || '', name: offer.store_name || '', url: offer.store_url || '', amount: offer.amount ?? '', currency: offer.currency || '', shipping: offer.shipping ?? '', availability: offer.availability || 'unknown', last_checked: offer.last_checked || '', notes: offer.notes || '' };
        fieldset.querySelectorAll('[data-field]').forEach(input => {
            const key = input.dataset.field;
            input.name = `stores[${index}][${key}]`;
            input.value = values[key];
        });
        const currency = fieldset.querySelector('[data-field="currency"]');
        const amount = fieldset.querySelector('[data-field="amount"]');
        const shipping = fieldset.querySelector('[data-field="shipping"]');
        const requireCurrency = () => { currency.required = amount.value !== '' || shipping.value !== ''; };
        amount.addEventListener('input', requireCurrency); shipping.addEventListener('input', requireCurrency); requireCurrency();
        const checked = fieldset.querySelector('[data-field="last_checked"]'); checked.max = today();
        fieldset.querySelector('[data-checked]').addEventListener('click', () => { checked.value = today(); });
        fieldset.querySelector('[data-remove]').addEventListener('click', () => { fieldset.remove(); });
        fieldset.querySelector('[data-offer-help]').textContent = offer.amount === null && offer.price ? `Legacy price: ${offer.price}. Enter a numeric amount and currency to include it in comparisons.` : `${offer.history?.length || 0} saved observations. Removing an offer retains its history.`;
        offers.appendChild(fieldset);
        return fieldset;
    }

    function openEditor(item = null) {
        form.reset(); offers.replaceChildren(); offerIndex = 0;
        document.getElementById('wl-form-error').hidden = true;
        document.getElementById('wl-dialog-title').textContent = item ? 'Edit wanted item & offers' : 'Add wanted item';
        ['id','name','item_type','catalogue_ref','theme_id','status','priority','target_price','target_currency','description'].forEach(name => {
            if (item && item[name] !== null && item[name] !== undefined) form.elements.namedItem(name).value = item[name];
        });
        if (!item) { form.elements.namedItem('id').value = ''; form.elements.namedItem('item_type').value = 'stamp'; }
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        preview.hidden = !item?.image_url;
        if (item?.image_url) preview.src = `${data.siteUrl}/uploads/originals/${encodeURIComponent(item.image_url.split(/[\\/]/).pop())}`;
        (item?.stores || []).filter(offer => !Number(offer.archived)).forEach(appendOffer);
        form.elements.namedItem('target_currency').required = form.elements.namedItem('target_price').value !== '';
        dialog.showModal();
    }
    document.getElementById('wl-add').addEventListener('click', () => openEditor());
    document.getElementById('wl-offer-add').addEventListener('click', () => { const row = appendOffer(); row.querySelector('[data-field="name"]').focus(); });
    form.elements.namedItem('target_price').addEventListener('input', () => { form.elements.namedItem('target_currency').required = form.elements.namedItem('target_price').value !== ''; });
    document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.close).close()));
    document.getElementById('wl-image').addEventListener('change', event => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = event.target.files[0];
        preview.hidden = !file;
        if (file) { objectUrl = URL.createObjectURL(file); preview.src = objectUrl; }
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        document.getElementById('wl-form-error').hidden = true;
        setBusy(button, true, 'Save item & offers');
        try { await request(form.elements.namedItem('id').value ? 'wishlist_edit' : 'wishlist_add', new FormData(form)); location.reload(); }
        catch (error) { showError(document.getElementById('wl-form-error'), error); }
        finally { setBusy(button, false, 'Save item & offers'); }
    });

    document.getElementById('wl-items').addEventListener('click', async event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.dataset.edit) { openEditor(byId.get(button.dataset.edit)); return; }
        const id = button.dataset.delete || button.dataset.migrate;
        if (!id) return;
        const item = byId.get(id);
        const deleting = Boolean(button.dataset.delete);
        if (!confirm(deleting ? `Delete "${item.name}" and all its seller observations permanently?` : `Create a private draft collection record for "${item.name}" and mark it acquired? Collecting notes remain private in the wantlist. Review the new record before publishing.`)) return;
        button.disabled = true;
        const payload = new FormData(); payload.set('id', id);
        try { const result = await request(deleting ? 'wishlist_delete' : 'wishlist_migrate', payload); if (!deleting && result.redirect) location.assign(result.redirect); else location.reload(); }
        catch (error) { notice(error); button.disabled = false; }
    });

    function filter() {
        const query = document.getElementById('wl-search').value.trim().toLocaleLowerCase();
        const status = document.getElementById('wl-filter-status').value;
        const type = document.getElementById('wl-filter-type').value;
        const theme = document.getElementById('wl-filter-theme').value;
        let visible = 0;
        document.querySelectorAll('.wl-item').forEach(item => {
            item.hidden = Boolean((query && !item.textContent.toLocaleLowerCase().includes(query)) || (status && item.dataset.status !== status) || (type && item.dataset.type !== type) || (theme && item.dataset.theme !== theme));
            if (!item.hidden) visible++;
        });
        document.getElementById('wl-empty').hidden = visible > 0;
    }
    document.getElementById('wl-search').addEventListener('input', filter);
    ['wl-filter-status','wl-filter-type','wl-filter-theme'].forEach(id => document.getElementById(id).addEventListener('change', filter));

    const bulkDialog = document.getElementById('wl-bulk-dialog');
    const bulkForm = document.getElementById('wl-bulk-form');
    document.getElementById('wl-bulk-open').addEventListener('click', () => { document.getElementById('wl-bulk-error').hidden = true; bulkDialog.showModal(); });
    bulkForm.addEventListener('submit', async event => {
        event.preventDefault();
        const errorBox = document.getElementById('wl-bulk-error'); errorBox.hidden = true;
        const names = bulkForm.elements.namedItem('names').value.split(/\r?\n/).map(name => name.trim()).filter(Boolean);
        if (!names.length || names.length > 100) { showError(errorBox, new Error('Enter between 1 and 100 names, one per line.')); return; }
        const payload = new FormData(bulkForm); payload.delete('names'); names.forEach(name => payload.append('items[]', name));
        const button = bulkForm.querySelector('[type="submit"]'); setBusy(button, true, 'Add items');
        try { await request('wishlist_bulk_add', payload); location.reload(); }
        catch (error) { showError(errorBox, error); }
        finally { setBusy(button, false, 'Add items'); }
    });
})();
