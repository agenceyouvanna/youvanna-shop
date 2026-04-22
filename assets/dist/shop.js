/*!
 * yv-shop archive page interactions.
 * Handles: legacy add-to-cart on archives, filter collapsible groups, filter drawer on mobile.
 */
(function (w, d) {
    'use strict';

    d.addEventListener('click', function (e) {
        var btn = e.target.closest('.yv-shop-add-to-cart');
        if (!btn) return;
        if (btn.closest('form[data-yv-shop-add-form]')) return;

        e.preventDefault();
        if (!w.yvShopStore) return;

        var original = btn.textContent;
        w.yvShopStore.add({
            product_id: btn.dataset.productId,
            qty: 1,
            name: btn.dataset.productName || '',
            price: btn.dataset.productPrice || 0,
            image: btn.dataset.productImage || '',
            permalink: btn.dataset.productPermalink || ''
        });
        btn.textContent = (w.yvShop && w.yvShop.i18n && w.yvShop.i18n.added) || 'Ajouté';
        btn.classList.add('is-added');
        setTimeout(function () {
            btn.textContent = original;
            btn.classList.remove('is-added');
        }, 1500);
    });

    // Collapsible filter groups
    d.addEventListener('click', function (e) {
        var label = e.target.closest('.yv-shop-filters__group[data-collapsible] > .yv-shop-filters__label');
        if (!label) return;
        var group = label.parentElement;
        var expanded = group.getAttribute('aria-expanded') !== 'false';
        group.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    });

    // Afficher plus / moins sur les listes de filtres longues
    d.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-yv-show-more]');
        if (!btn) return;
        var list = btn.previousElementSibling;
        if (!list || !list.classList.contains('yv-shop-filters__checkboxes')) return;
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        var extras = list.querySelectorAll('li[data-extra="1"]');
        extras.forEach(function (li) {
            if (expanded) li.setAttribute('hidden', '');
            else li.removeAttribute('hidden');
        });
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        var labelEl = btn.querySelector('.yv-shop-filters__more-label');
        if (labelEl) {
            labelEl.textContent = expanded
                ? ((w.yvShop && w.yvShop.i18n && w.yvShop.i18n.showMore) || 'Afficher plus')
                : ((w.yvShop && w.yvShop.i18n && w.yvShop.i18n.showLess) || 'Afficher moins');
        }
    });

    // Filter drawer (mobile)
    var sidebar = d.querySelector('[data-yv-filters]');
    function openFilters() {
        if (!sidebar) return;
        sidebar.setAttribute('data-open', 'true');
        d.documentElement.classList.add('yv-shop-filters-open');
        var trigger = d.querySelector('[data-yv-filters-open]');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
    }
    function closeFilters() {
        if (!sidebar) return;
        sidebar.removeAttribute('data-open');
        d.documentElement.classList.remove('yv-shop-filters-open');
        var trigger = d.querySelector('[data-yv-filters-open]');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }
    d.addEventListener('click', function (e) {
        if (e.target.closest('[data-yv-filters-open]')) {
            e.preventDefault();
            openFilters();
            return;
        }
        if (e.target.closest('[data-yv-filters-close]')) {
            e.preventDefault();
            closeFilters();
            return;
        }
    });
    d.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.getAttribute('data-open') === 'true') {
            closeFilters();
        }
    });

    if (sidebar) {
        sidebar.addEventListener('click', function (e) {
            var link = e.target.closest('.yv-shop-filters__list a');
            if (!link) return;
            if (w.matchMedia('(max-width: 960px)').matches) {
                closeFilters();
            }
        });
    }

    // Wishlist toggle (localStorage - pas encore de backend)
    var WISH_KEY = 'yvShopWishlist';
    function getWish() {
        try { return JSON.parse(localStorage.getItem(WISH_KEY) || '[]'); }
        catch (e) { return []; }
    }
    function saveWish(arr) {
        try { localStorage.setItem(WISH_KEY, JSON.stringify(arr)); } catch (e) {}
    }
    function refreshWishButtons() {
        var list = getWish();
        d.querySelectorAll('[data-yv-wish]').forEach(function (btn) {
            var id = btn.dataset.productId;
            if (!id) return;
            if (list.indexOf(id) !== -1) btn.classList.add('is-active');
            else btn.classList.remove('is-active');
        });
    }
    d.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-yv-wish]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var id = btn.dataset.productId;
        if (!id) return;
        var list = getWish();
        var idx = list.indexOf(id);
        if (idx === -1) list.push(id);
        else list.splice(idx, 1);
        saveWish(list);
        refreshWishButtons();
    });
    refreshWishButtons();
})(window, document);
