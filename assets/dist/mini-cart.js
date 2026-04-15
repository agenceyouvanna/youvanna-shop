/*!
 * yv-shop mini-cart count updater.
 */
(function (w, d) {
    'use strict';

    function updateCount() {
        if (!w.yvShopStore) return;
        var count = w.yvShopStore.count();
        d.querySelectorAll('[data-yv-shop-cart-count]').forEach(function (el) {
            el.textContent = String(count);
            el.classList.toggle('is-empty', count === 0);
        });
    }

    w.addEventListener(w.YV_SHOP_CART_EVENT, updateCount);
    d.addEventListener('DOMContentLoaded', updateCount);
    w.addEventListener('storage', function (e) {
        if (e.key === 'yv_shop_cart_v1') updateCount();
    });
})(window, document);
