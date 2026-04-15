/*!
 * yv-shop mini-cart drawer.
 * - Count badge on [data-yv-shop-cart-count]
 * - Drawer opens on [data-yv-shop-cart-toggle] click
 * - yvShopMiniCart.open() / close() / toggle()
 */
(function (w, d) {
    'use strict';

    var drawer = null;

    function t(key, fallback) {
        return (w.yvShop && w.yvShop.i18n && w.yvShop.i18n[key]) || fallback;
    }

    function fmt(n) {
        return (w.yvShopStore ? w.yvShopStore.formatPrice(n) : ((parseFloat(n) || 0).toFixed(2) + ' EUR'));
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function buildDrawer() {
        if (drawer) return drawer;
        drawer = d.createElement('div');
        drawer.className = 'yv-shop-drawer';
        drawer.setAttribute('aria-hidden', 'true');
        drawer.setAttribute('role', 'dialog');
        drawer.setAttribute('aria-label', t('cart', 'Panier'));
        drawer.innerHTML = '' +
            '<div class="yv-shop-drawer__backdrop" data-yv-shop-drawer-close></div>' +
            '<aside class="yv-shop-drawer__panel">' +
                '<header class="yv-shop-drawer__header">' +
                    '<h2 class="yv-shop-drawer__title">' + esc(t('cart', 'Votre panier')) + '</h2>' +
                    '<button type="button" class="yv-shop-drawer__close" data-yv-shop-drawer-close aria-label="' + esc(t('close', 'Fermer')) + '">' +
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                    '</button>' +
                '</header>' +
                '<div class="yv-shop-drawer__body" data-yv-shop-drawer-body></div>' +
                '<footer class="yv-shop-drawer__footer" data-yv-shop-drawer-footer></footer>' +
            '</aside>';
        d.body.appendChild(drawer);

        drawer.addEventListener('click', function (e) {
            if (e.target.closest('[data-yv-shop-drawer-close]')) {
                e.preventDefault();
                close();
                return;
            }
            var dec = e.target.closest('[data-mc-dec]');
            var inc = e.target.closest('[data-mc-inc]');
            var rm = e.target.closest('[data-mc-remove]');
            if (dec || inc) {
                e.preventDefault();
                var row = (dec || inc).closest('[data-mc-row]');
                if (!row || !w.yvShopStore) return;
                var pid = parseInt(row.dataset.pid, 10);
                var vid = row.dataset.vid ? parseInt(row.dataset.vid, 10) : null;
                var sig = row.dataset.sig || null;
                var curQty = parseInt(row.dataset.qty, 10) || 1;
                var next = inc ? curQty + 1 : curQty - 1;
                w.yvShopStore.setQty(pid, vid, next, sig);
                render();
            }
            if (rm) {
                e.preventDefault();
                var r = rm.closest('[data-mc-row]');
                if (!r || !w.yvShopStore) return;
                w.yvShopStore.remove(parseInt(r.dataset.pid, 10), r.dataset.vid ? parseInt(r.dataset.vid, 10) : null, r.dataset.sig || null);
                render();
            }
        });

        return drawer;
    }

    function renderItem(it) {
        var thumb = it.image ? '<img src="' + esc(it.image) + '" alt="" loading="lazy">' : '<div class="yv-shop-drawer__thumb-placeholder" aria-hidden="true"></div>';
        var configSummary = '';
        if (it.lens_config && typeof it.lens_config === 'object') {
            configSummary = '<div class="yv-shop-drawer__meta">' + esc(t('custom_lens', 'Verres configurés')) + '</div>';
        }
        var lineTotal = (parseFloat(it.price) || 0) * (parseInt(it.qty, 10) || 0);
        return '' +
            '<li class="yv-shop-drawer__item" data-mc-row data-pid="' + esc(it.product_id) + '" data-vid="' + esc(it.variation_id || '') + '" data-sig="' + esc(it.config_sig || '') + '" data-qty="' + esc(it.qty) + '">' +
                '<div class="yv-shop-drawer__thumb">' + thumb + '</div>' +
                '<div class="yv-shop-drawer__info">' +
                    '<a class="yv-shop-drawer__name" href="' + esc(it.permalink || '#') + '">' + esc(it.name) + '</a>' +
                    configSummary +
                    '<div class="yv-shop-drawer__qty-row">' +
                        '<div class="yv-shop-drawer__qty">' +
                            '<button type="button" data-mc-dec aria-label="' + esc(t('decrease', 'Diminuer')) + '">-</button>' +
                            '<span>' + esc(it.qty) + '</span>' +
                            '<button type="button" data-mc-inc aria-label="' + esc(t('increase', 'Augmenter')) + '">+</button>' +
                        '</div>' +
                        '<span class="yv-shop-drawer__price">' + fmt(lineTotal) + '</span>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="yv-shop-drawer__remove" data-mc-remove aria-label="' + esc(t('remove', 'Supprimer')) + '">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>' +
                '</button>' +
            '</li>';
    }

    function render() {
        if (!drawer || !w.yvShopStore) return;
        var cart = w.yvShopStore.get();
        var body = drawer.querySelector('[data-yv-shop-drawer-body]');
        var footer = drawer.querySelector('[data-yv-shop-drawer-footer]');
        var shop = (w.yvShop && w.yvShop.shop_page) || '/';
        var cartUrl = (w.yvShop && w.yvShop.cart_page) || '/';
        var checkoutUrl = (w.yvShop && w.yvShop.checkout_page) || '/';

        if (!cart.items.length) {
            body.innerHTML = '<div class="yv-shop-drawer__empty">' +
                '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>' +
                '<p>' + esc(t('empty_cart', 'Votre panier est vide')) + '</p>' +
                '<a class="yv-shop-btn yv-shop-btn--primary" href="' + esc(shop) + '">' + esc(t('continue', 'Continuer mes achats')) + '</a>' +
                '</div>';
            footer.innerHTML = '';
            return;
        }
        var html = '<ul class="yv-shop-drawer__items">';
        for (var i = 0; i < cart.items.length; i++) html += renderItem(cart.items[i]);
        html += '</ul>';
        body.innerHTML = html;

        var subtotal = w.yvShopStore.subtotal();
        footer.innerHTML = '' +
            '<div class="yv-shop-drawer__subtotal">' +
                '<span>' + esc(t('subtotal', 'Sous-total')) + '</span>' +
                '<strong>' + fmt(subtotal) + '</strong>' +
            '</div>' +
            '<p class="yv-shop-drawer__note">' + esc(t('shipping_note', 'Livraison et taxes calculées au paiement.')) + '</p>' +
            '<a class="yv-shop-btn yv-shop-btn--secondary yv-shop-btn--block" href="' + esc(cartUrl) + '">' + esc(t('view_cart', 'Voir mon panier')) + '</a>' +
            '<a class="yv-shop-btn yv-shop-btn--primary yv-shop-btn--block" href="' + esc(checkoutUrl) + '">' + esc(t('checkout', 'Commander')) + '</a>';
    }

    function open() {
        buildDrawer();
        render();
        drawer.setAttribute('aria-hidden', 'false');
        d.documentElement.classList.add('yv-shop-drawer-open');
    }

    function close() {
        if (!drawer) return;
        drawer.setAttribute('aria-hidden', 'true');
        d.documentElement.classList.remove('yv-shop-drawer-open');
    }

    function toggle() {
        if (!drawer || drawer.getAttribute('aria-hidden') !== 'false') open(); else close();
    }

    function updateCount() {
        if (!w.yvShopStore) return;
        var count = w.yvShopStore.count();
        d.querySelectorAll('[data-yv-shop-cart-count]').forEach(function (el) {
            el.textContent = String(count);
            el.classList.toggle('is-empty', count === 0);
        });
        var triggers = d.querySelectorAll('[data-yv-shop-cart-toggle]');
        triggers.forEach(function (el) {
            el.classList.toggle('is-empty', count === 0);
        });
    }

    function bindGlobal() {
        d.addEventListener('click', function (e) {
            var toggleBtn = e.target.closest('[data-yv-shop-cart-toggle]');
            if (toggleBtn) {
                e.preventDefault();
                open();
            }
        });
        d.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && drawer && drawer.getAttribute('aria-hidden') === 'false') close();
        });
        w.addEventListener(w.YV_SHOP_CART_EVENT, function () {
            updateCount();
            if (drawer && drawer.getAttribute('aria-hidden') === 'false') render();
        });
        w.addEventListener('storage', function (e) {
            if (e.key === 'yv_shop_cart_v1') {
                updateCount();
                if (drawer && drawer.getAttribute('aria-hidden') === 'false') render();
            }
        });
        d.addEventListener('yv-shop:added', function () {
            open();
        });
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', function () { bindGlobal(); updateCount(); });
    } else {
        bindGlobal();
        updateCount();
    }

    w.yvShopMiniCart = { open: open, close: close, toggle: toggle, render: render };
})(window, document);
