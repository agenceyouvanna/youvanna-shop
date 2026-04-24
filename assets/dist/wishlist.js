/*!
 * yv-shop wishlist module.
 * Exposes window.YvWishlist : store 100% localStorage.
 * - Toggle bindings : [data-yv-wish-toggle] (new) + [data-yv-wish] (legacy).
 * - Navbar badge : [data-yv-wishlist-count].
 * - Wishlist page : [data-yv-wishlist-page] rendered via REST /yv-shop/v1/products?ids[]=...
 * - Event : document dispatches "yv:wishlist:change" { detail: { items, count } } on mutation.
 */
(function (w, d) {
    'use strict';

    var STORAGE_KEY = 'yv_wishlist_v1';
    var LEGACY_KEY = 'yvShopWishlist';
    var EVENT_NAME = 'yv:wishlist:change';

    function readRaw(key) {
        try {
            var raw = localStorage.getItem(key);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) return null;
            return parsed;
        } catch (e) { return null; }
    }

    function normalizeItems(arr) {
        if (!Array.isArray(arr)) return [];
        var out = [];
        var seen = {};
        for (var i = 0; i < arr.length; i++) {
            var v = arr[i];
            var n = parseInt(v, 10);
            if (!n || n <= 0) continue;
            if (seen[n]) continue;
            seen[n] = true;
            out.push(n);
        }
        return out;
    }

    // Migration : si yvShopWishlist existe et yv_wishlist_v1 absent, on migre.
    function migrate() {
        try {
            var v1 = localStorage.getItem(STORAGE_KEY);
            if (v1 !== null) return;
            var legacy = readRaw(LEGACY_KEY);
            if (!legacy) return;
            var migrated = normalizeItems(legacy);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(migrated));
        } catch (e) {}
    }

    function read() {
        var raw = readRaw(STORAGE_KEY);
        if (raw === null) return [];
        return normalizeItems(raw);
    }

    function write(items) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        } catch (e) {}
    }

    function dispatch(items) {
        try {
            d.dispatchEvent(new CustomEvent(EVENT_NAME, {
                detail: { items: items.slice(), count: items.length }
            }));
        } catch (e) {
            // IE/legacy fallback
            var ev = d.createEvent('CustomEvent');
            ev.initCustomEvent(EVENT_NAME, false, false, { items: items.slice(), count: items.length });
            d.dispatchEvent(ev);
        }
    }

    var api = {
        items: function () { return read(); },
        has: function (id) {
            var n = parseInt(id, 10);
            if (!n) return false;
            return read().indexOf(n) !== -1;
        },
        add: function (id) {
            var n = parseInt(id, 10);
            if (!n) return false;
            var list = read();
            if (list.indexOf(n) !== -1) return false;
            list.push(n);
            write(list);
            dispatch(list);
            return true;
        },
        remove: function (id) {
            var n = parseInt(id, 10);
            if (!n) return false;
            var list = read();
            var idx = list.indexOf(n);
            if (idx === -1) return false;
            list.splice(idx, 1);
            write(list);
            dispatch(list);
            return true;
        },
        toggle: function (id) {
            var n = parseInt(id, 10);
            if (!n) return false;
            var list = read();
            var idx = list.indexOf(n);
            if (idx === -1) {
                list.push(n);
                write(list);
                dispatch(list);
                return true;
            }
            list.splice(idx, 1);
            write(list);
            dispatch(list);
            return false;
        },
        count: function () { return read().length; },
        clear: function () {
            write([]);
            dispatch([]);
        }
    };

    migrate();
    w.YvWishlist = api;

    // -----------------------------------------------------------------
    // UI bindings
    // -----------------------------------------------------------------

    function selectorId(btn) {
        // Priorité : data-yv-wish-toggle (nouveau), data-product-id (legacy)
        var toggle = btn.getAttribute('data-yv-wish-toggle');
        if (toggle && toggle !== '') return parseInt(toggle, 10);
        if (btn.dataset && btn.dataset.productId) return parseInt(btn.dataset.productId, 10);
        return 0;
    }

    function applyActiveState(btn, isActive) {
        if (isActive) btn.classList.add('is-active');
        else btn.classList.remove('is-active');
        // Swap aria-label + visible label si un <span data-yv-wish-label> existe
        var label = btn.querySelector('[data-yv-wish-label]');
        var activeText = btn.getAttribute('data-label-active') || 'Retirer des coups de coeur';
        var inactiveText = btn.getAttribute('data-label-inactive') || 'Ajouter aux coups de coeur';
        if (label) label.textContent = isActive ? activeText : inactiveText;
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        btn.setAttribute('aria-label', isActive ? activeText : inactiveText);
    }

    function refreshAllToggles() {
        var list = read();
        var buttons = d.querySelectorAll('[data-yv-wish-toggle], [data-yv-wish]');
        for (var i = 0; i < buttons.length; i++) {
            var id = selectorId(buttons[i]);
            applyActiveState(buttons[i], id && list.indexOf(id) !== -1);
        }
    }

    function refreshCounts() {
        var list = read();
        var badges = d.querySelectorAll('[data-yv-wishlist-count]');
        for (var i = 0; i < badges.length; i++) {
            badges[i].textContent = String(list.length);
            if (list.length === 0) badges[i].classList.add('is-empty');
            else badges[i].classList.remove('is-empty');
        }
    }

    d.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-yv-wish-toggle], [data-yv-wish]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var id = selectorId(btn);
        if (!id) return;
        api.toggle(id);
    });

    d.addEventListener(EVENT_NAME, function () {
        refreshAllToggles();
        refreshCounts();
        maybeRenderPage();
    });

    // -----------------------------------------------------------------
    // Page /coups-de-coeur/
    // -----------------------------------------------------------------

    var pageRendering = false;
    var pageLastItems = null;

    function getRestBase() {
        if (w.yvShop && w.yvShop.rest_url) return String(w.yvShop.rest_url).replace(/\/$/, '');
        return '/wp-json/yv-shop/v1';
    }

    function getShopUrl() {
        if (w.yvShop && w.yvShop.shop_page) return w.yvShop.shop_page;
        return '/boutique/';
    }

    function formatPrice(cents, symbol) {
        symbol = symbol || (w.yvShop && w.yvShop.currency_symbol) || 'EUR';
        var n = parseFloat(cents || 0);
        if (!isFinite(n)) n = 0;
        return n.toFixed(2).replace('.', ',') + ' ' + symbol;
    }

    function buildCard(item) {
        var card = d.createElement('article');
        card.className = 'yv-shop-card yv-shop-wishlist-card';
        card.setAttribute('data-product-id', String(item.id));

        var onSale = !!item.on_sale;
        var priceHtml;
        if (onSale) {
            priceHtml = '<del>' + formatPrice(item.price) + '</del><ins>' + formatPrice(item.active_price) + '</ins>';
        } else {
            priceHtml = '<span>' + formatPrice(item.active_price) + '</span>';
        }

        var imgHtml = '';
        if (item.image && item.image.thumb) {
            imgHtml = '<img src="' + item.image.thumb + '" alt="' + (item.image.alt || item.name || '').replace(/"/g, '&quot;') + '" loading="lazy" decoding="async">';
        } else {
            imgHtml = '<div class="yv-shop-card__media-placeholder" aria-hidden="true"></div>';
        }

        var permalink = item.permalink || '#';

        card.innerHTML =
            '<a href="' + permalink + '" class="yv-shop-card__media-link">' +
                '<div class="yv-shop-card__media">' + imgHtml + '</div>' +
            '</a>' +
            '<div class="yv-shop-card__body">' +
                '<h3 class="yv-shop-card__title"><a href="' + permalink + '">' + escapeHtml(item.name || '') + '</a></h3>' +
                '<div class="yv-shop-card__price">' + priceHtml + '<span class="yv-shop-card__tax">TTC</span></div>' +
                '<div class="yv-shop-card__actions">' +
                    '<a href="' + permalink + '" class="yv-shop-card__cta">Voir le produit</a>' +
                    '<button type="button" class="yv-shop-card__wish is-active" data-yv-wish-toggle="' + item.id + '" aria-pressed="true" aria-label="Retirer des coups de coeur" data-label-active="Retirer des coups de coeur" data-label-inactive="Ajouter aux coups de coeur">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>' +
                        '<span data-yv-wish-label>Retirer des coups de coeur</span>' +
                    '</button>' +
                '</div>' +
            '</div>';

        return card;
    }

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderEmpty(container) {
        container.innerHTML =
            '<div class="yv-shop-wishlist-empty">' +
                '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>' +
                '<p>Votre liste de coups de coeur est vide.</p>' +
                '<a href="' + getShopUrl() + '" class="yv-shop-btn yv-shop-btn--primary">Découvrir la boutique</a>' +
            '</div>';
    }

    function renderLoading(container) {
        container.innerHTML = '<div class="yv-shop-wishlist-loading" aria-live="polite">Chargement...</div>';
    }

    function renderError(container, msg) {
        container.innerHTML = '<div class="yv-shop-wishlist-error">' + escapeHtml(msg || 'Erreur lors du chargement.') + '</div>';
    }

    function maybeRenderPage() {
        var container = d.querySelector('[data-yv-wishlist-page]');
        if (!container) return;

        var items = read();

        // Évite de re-fetch si identique à la dernière fois rendue
        var signature = items.join(',');
        if (pageLastItems === signature && !pageRendering) {
            // Juste rafraîchir l'état actif, pas de refetch
            return;
        }

        if (!items.length) {
            renderEmpty(container);
            pageLastItems = signature;
            return;
        }

        if (pageRendering) return;
        pageRendering = true;
        renderLoading(container);

        var url = getRestBase() + '/products?per_page=100';
        for (var i = 0; i < items.length; i++) {
            url += '&ids[]=' + encodeURIComponent(items[i]);
        }

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('http ' + r.status);
                return r.json();
            })
            .then(function (data) {
                var list = (data && data.items) ? data.items : [];
                // Réordonner selon l'ordre du store (premier ajouté en premier)
                var byId = {};
                for (var i = 0; i < list.length; i++) byId[list[i].id] = list[i];
                var ordered = [];
                for (var j = 0; j < items.length; j++) {
                    if (byId[items[j]]) ordered.push(byId[items[j]]);
                }
                if (!ordered.length) {
                    renderEmpty(container);
                } else {
                    var frag = d.createDocumentFragment();
                    var grid = d.createElement('div');
                    grid.className = 'yv-shop-wishlist-grid';
                    for (var k = 0; k < ordered.length; k++) {
                        grid.appendChild(buildCard(ordered[k]));
                    }
                    frag.appendChild(grid);
                    container.innerHTML = '';
                    container.appendChild(frag);
                }
                pageLastItems = signature;
                pageRendering = false;
            })
            .catch(function () {
                renderError(container, 'Impossible de charger votre liste. Réessayez plus tard.');
                pageRendering = false;
            });
    }

    // Initial pass
    function init() {
        refreshAllToggles();
        refreshCounts();
        maybeRenderPage();
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Sync cross-tab
    w.addEventListener('storage', function (e) {
        if (e.key === STORAGE_KEY) {
            dispatch(read());
        }
    });

})(window, document);
