(function (root) {
    'use strict';

    function coordinates(location) {
        const values = [location.latitude, location.longitude];
        if (values.some(v => v == null || String(v).trim() === '')) return null;
        const [lat, lng] = values.map(Number);
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180 || (lat === 0 && lng === 0)) return null;
        return [lat, lng];
    }

    function matches(location, filters) {
        const acquired = Number(location.is_acquired) === 1;
        if (filters.status === 'collected' && !acquired) return false;
        if (filters.status === 'seeking' && acquired) return false;
        if (filters.state && location.state !== filters.state) return false;
        const text = ['post_office', 'ppc_name', 'district', 'state', 'pin_code', 'linked_item_title']
            .map(key => String(location[key] ?? '')).join(' ').toLowerCase();
        return String(filters.query || '').trim().toLowerCase().split(/\s+/).every(term => text.includes(term));
    }

    function safeImageUrl(value, base) {
        if (!value) return null;
        try {
            const url = new URL(value, base);
            return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
        } catch (_) { return null; }
    }

    function init() {
        const dataElement = document.getElementById('atlas-data');
        if (!dataElement) return;
        const notice = document.getElementById('atlas-notice');
        const summary = document.getElementById('atlas-summary');
        function tell(message) {
            notice.textContent = message;
            notice.hidden = !message;
        }
        if (!root.L || !root.AtlasText) {
            summary.textContent = 'Map unavailable';
            tell('The map could not load. Check your connection and reload this page.');
            return;
        }
        const config = JSON.parse(dataElement.textContent);
        const L = root.L;
        const map = L.map('frontendAtlasMap').setView([20.5937, 78.9629], 5);
        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            referrerPolicy: 'strict-origin-when-cross-origin'
        }).addTo(map);
        tiles.on('tileerror', () => tell('Some map tiles could not load. Check your connection; location search and markers remain available.'));
        L.control.scale({imperial: false}).addTo(map);

        const clustered = typeof L.markerClusterGroup === 'function';
        const markers = clustered ? L.markerClusterGroup({
            maxClusterRadius: 45,
            showCoverageOnHover: false,
            // Keep clustering at max zoom so identical coordinates can spiderfy.
            iconCreateFunction: cluster => L.divIcon({
                html: '<span>' + cluster.getChildCount() + '</span>',
                className: 'atlas-cluster', iconSize: [42, 42]
            })
        }).addTo(map) : L.featureGroup().addTo(map);
        if (!clustered) tell('Marker grouping is unavailable. Individual locations are still shown.');

        const search = document.getElementById('atlas-search-input');
        const clear = document.getElementById('atlas-search-clear');
        const dropdown = document.getElementById('atlas-search-dropdown');
        const state = document.getElementById('atlas-state');
        const status = document.getElementById('atlas-status');
        const showAll = document.getElementById('atlas-show-all');
        const locate = document.getElementById('btn-locate');
        const filters = {query: '', status: 'all', state: ''};
        const entries = config.locations.filter(loc => coordinates(loc)).map(loc => ({location: loc, point: coordinates(loc)}));
        const skipped = config.locations.length - entries.length;
        let visible = [];
        let suggestions = [];
        let activeSuggestion = -1;
        let searchTimer;

        [...new Set(entries.map(entry => entry.location.state).filter(Boolean))].sort((a, b) => a.localeCompare(b)).forEach(name => {
            const option = document.createElement('option');
            option.value = name;
            option.textContent = name;
            state.appendChild(option);
        });

        function element(tag, text, className) {
            const node = document.createElement(tag);
            if (text != null) node.textContent = text;
            if (className) node.className = className;
            return node;
        }

        function popup(location) {
            const box = element('div', null, 'atlas-popup');
            box.appendChild(element('h2', location.post_office || 'Post office'));
            if (location.ppc_name) box.appendChild(element('p', location.ppc_name));
            box.appendChild(element('p', [location.district, location.state, location.pin_code].filter(Boolean).join(' · ')));
            const acquired = Number(location.is_acquired) === 1;
            box.appendChild(element('span', acquired ? 'In collection' : 'Seeking', 'atlas-popup-status' + (acquired ? ' is-collected' : '')));
            const id = root.AtlasText.itemId(location.linked_item_id);
            if (id && location.linked_item_title) {
                const link = element('a', null);
                link.href = config.siteUrl.replace(/\/$/, '') + '/item/' + id;
                const imageUrl = safeImageUrl(location.preview_url, config.siteUrl);
                if (imageUrl) {
                    const image = element('img', null);
                    image.src = imageUrl;
                    image.alt = location.linked_item_title;
                    image.loading = 'lazy';
                    image.addEventListener('error', () => image.remove(), {once: true});
                    link.appendChild(image);
                }
                link.appendChild(element('span', location.linked_item_title + ' →'));
                box.appendChild(link);
            }
            return box;
        }

        entries.forEach(entry => {
            const loc = entry.location;
            const acquired = Number(loc.is_acquired) === 1;
            entry.marker = L.marker(entry.point, {
                title: (loc.post_office || 'Post office') + (acquired ? ' — Collected' : ' — Seeking'),
                icon: L.divIcon({className: 'atlas-pin', html: '<span class="' + (acquired ? 'collected' : 'seeking') + '"><b>' + (acquired ? 'C' : 'S') + '</b></span>', iconSize: [25, 30], iconAnchor: [12, 28], popupAnchor: [0, -27]})
            }).bindPopup(() => popup(loc), {maxWidth: 270})
                .bindTooltip(element('span', loc.ppc_name || loc.post_office || 'Post office'));
        });

        function fitVisible() {
            if (visible.length) map.fitBounds(L.latLngBounds(visible.map(entry => entry.point)).pad(0.12), {maxZoom: 14});
        }

        function hideSuggestions() {
            dropdown.hidden = true;
            search.setAttribute('aria-expanded', 'false');
            search.removeAttribute('aria-activedescendant');
            activeSuggestion = -1;
        }

        function select(entry) {
            // Entries come only from the current filtered set.
            hideSuggestions();
            if (clustered) markers.zoomToShowLayer(entry.marker, () => entry.marker.openPopup());
            else { map.setView(entry.point, 14); entry.marker.openPopup(); }
        }

        function showSuggestions() {
            dropdown.replaceChildren();
            activeSuggestion = -1;
            search.removeAttribute('aria-activedescendant');
            if (!filters.query.trim()) { hideSuggestions(); return; }
            suggestions = visible.slice(0, 8);
            suggestions.forEach((entry, index) => {
                const loc = entry.location;
                const option = element('div', null, 'atlas-result');
                option.id = 'atlas-result-' + index;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.appendChild(element('strong', loc.post_office || 'Post office'));
                option.appendChild(element('small', [loc.ppc_name, loc.district, loc.state, loc.pin_code, Number(loc.is_acquired) === 1 ? 'Collected' : 'Seeking'].filter(Boolean).join(' · ')));
                option.addEventListener('click', () => select(entry));
                dropdown.appendChild(option);
            });
            if (!suggestions.length) dropdown.appendChild(element('p', 'No locations match these filters.', 'atlas-search-note'));
            else if (visible.length > 8) dropdown.appendChild(element('p', 'Showing 8 of ' + visible.length + ' matches. Press Enter to fit all results.', 'atlas-search-note'));
            dropdown.hidden = false;
            search.setAttribute('aria-expanded', 'true');
        }

        function render(fit) {
            filters.query = search.value;
            filters.status = status.value;
            filters.state = state.value;
            map.closePopup();
            markers.clearLayers();
            visible = entries.filter(entry => matches(entry.location, filters));
            // Synchronous replacement avoids stale chunked inserts when filters change quickly.
            if (clustered) markers.addLayers(visible.map(entry => entry.marker));
            else visible.forEach(entry => markers.addLayer(entry.marker));
            const collected = visible.filter(entry => Number(entry.location.is_acquired) === 1).length;
            summary.textContent = visible.length
                ? visible.length + ' of ' + entries.length + ' locations · ' + collected + ' collected · ' + (visible.length - collected) + ' seeking'
                : (entries.length ? 'No locations match your search and filters.' : 'No mapped locations are available yet.');
            if (skipped) summary.textContent += ' · ' + skipped + ' without valid coordinates';
            clear.hidden = !search.value;
            if (fit) fitVisible();
        }

        search.addEventListener('input', () => {
            clearTimeout(searchTimer);
            // Hide old options immediately; they must not select an outdated match.
            hideSuggestions();
            searchTimer = setTimeout(() => { render(false); showSuggestions(); }, 150);
        });
        search.addEventListener('focus', () => { clearTimeout(searchTimer); render(false); showSuggestions(); });
        search.addEventListener('keydown', event => {
            if (event.key === 'Escape') { hideSuggestions(); return; }
            if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
            event.preventDefault();
            clearTimeout(searchTimer);
            if (filters.query !== search.value) { render(false); showSuggestions(); }
            if (event.key === 'Enter') {
                if (!dropdown.hidden && activeSuggestion >= 0 && suggestions[activeSuggestion]) select(suggestions[activeSuggestion]);
                else { fitVisible(); hideSuggestions(); }
                return;
            }
            if (dropdown.hidden) showSuggestions();
            if (!suggestions.length || dropdown.hidden) return;
            activeSuggestion = event.key === 'ArrowDown'
                ? (activeSuggestion + 1) % suggestions.length
                : (activeSuggestion <= 0 ? suggestions.length - 1 : activeSuggestion - 1);
            dropdown.querySelectorAll('[role="option"]').forEach((option, index) => {
                option.setAttribute('aria-selected', String(index === activeSuggestion));
                if (index === activeSuggestion) option.scrollIntoView({block: 'nearest'});
            });
            search.setAttribute('aria-activedescendant', 'atlas-result-' + activeSuggestion);
        });
        [status, state].forEach(control => control.addEventListener('change', () => {
            clearTimeout(searchTimer); render(true); hideSuggestions();
        }));
        clear.addEventListener('click', () => {
            clearTimeout(searchTimer); search.value = ''; render(true); hideSuggestions(); search.focus();
        });
        showAll.addEventListener('click', () => {
            clearTimeout(searchTimer); search.value = ''; state.value = ''; status.value = 'all';
            stopTracking(); render(true); hideSuggestions();
            if (!visible.length) map.setView([20.5937, 78.9629], 5);
        });
        document.addEventListener('click', event => {
            if (!document.getElementById('atlas-search-wrapper').contains(event.target)) hideSuggestions();
        });

        let watchId = null;
        let tracking = false;
        let locationGeneration = 0;
        let userMarker = null;
        let userAccuracy = null;
        function stopTracking() {
            tracking = false;
            locationGeneration++;
            if (watchId !== null) navigator.geolocation.clearWatch(watchId);
            watchId = null;
            if (userMarker) map.removeLayer(userMarker);
            if (userAccuracy) map.removeLayer(userAccuracy);
            userMarker = userAccuracy = null;
            locate.textContent = 'Locate me';
            locate.setAttribute('aria-pressed', 'false');
        }
        locate.addEventListener('click', () => {
            if (tracking) { stopTracking(); return; }
            if (!navigator.geolocation) { tell('Location is not supported in this browser.'); return; }
            if (!root.isSecureContext) { tell('Location access needs HTTPS or localhost. You can still search the map.'); return; }
            tracking = true;
            const generation = ++locationGeneration;
            let firstFix = true;
            locate.textContent = 'Stop tracking';
            locate.setAttribute('aria-pressed', 'true');
            watchId = navigator.geolocation.watchPosition(position => {
                if (!tracking || generation !== locationGeneration) return;
                const point = [position.coords.latitude, position.coords.longitude];
                if (!userMarker) {
                    userMarker = L.circleMarker(point, {radius: 8, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1}).addTo(map).bindPopup('You are here');
                    userAccuracy = L.circle(point, {radius: position.coords.accuracy, color: '#2563eb', fillOpacity: 0.08, weight: 1}).addTo(map);
                } else {
                    userMarker.setLatLng(point);
                    userAccuracy.setLatLng(point).setRadius(position.coords.accuracy);
                }
                if (firstFix) { map.setView(point, Math.max(map.getZoom(), 12)); userMarker.openPopup(); firstFix = false; }
            }, error => {
                if (!tracking || generation !== locationGeneration) return;
                stopTracking();
                tell(({1: 'Location access was denied. Allow it in your browser to use Locate me.', 2: 'Your location is unavailable. You can still search the map.', 3: 'Location request timed out. Try Locate me again.'})[error.code] || 'Could not get your location.');
            }, {enableHighAccuracy: true, timeout: 10000, maximumAge: 30000});
        });
        root.addEventListener('pagehide', stopTracking);
        render(true);
    }

    const api = {coordinates, matches, safeImageUrl, init};
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    root.AtlasPublic = api;
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
        else init();
    }
})(typeof window !== 'undefined' ? window : globalThis);
