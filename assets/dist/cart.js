/*!
 * yv-shop cart page.
 */
(function (w, d) {
    'use strict';

    function render() {
        if (!w.yvShopStore) return;
        var cart = w.yvShopStore.get();
        var root = d.getElementById('yv-shop-cart-app');
        if (!root) return;
        var empty = root.querySelector('[data-yv-empty]');
        var filled = root.querySelector('[data-yv-filled]');
        var rowsContainer = root.querySelector('[data-yv-cart-rows]');
        var tpl = d.getElementById('yv-shop-cart-row');
        if (!cart.items.length) {
            if (empty) empty.hidden = false;
            if (filled) filled.hidden = true;
            return;
        }
        if (empty) empty.hidden = true;
        if (filled) filled.hidden = false;
        rowsContainer.innerHTML = '';
        cart.items.forEach(function (it) {
            var frag = tpl.content.cloneNode(true);
            var row = frag.querySelector('[data-row]');
            row.dataset.productId = it.product_id;
            row.dataset.variationId = it.variation_id || '';
            var img = row.querySelector('[data-image]');
            if (it.image) img.src = it.image; else img.style.visibility = 'hidden';
            var link = row.querySelector('[data-link]');
            link.textContent = it.name;
            if (it.permalink) link.href = it.permalink;
            row.querySelector('[data-price]').textContent = w.yvShopStore.formatPrice(it.price);
            var qty = row.querySelector('[data-qty]');
            qty.value = it.qty;
            qty.addEventListener('change', function () {
                w.yvShopStore.setQty(it.product_id, it.variation_id || null, parseInt(qty.value, 10));
            });
            row.querySelector('[data-line-total]').textContent = w.yvShopStore.formatPrice(it.price * it.qty);
            row.querySelector('[data-remove]').addEventListener('click', function () {
                w.yvShopStore.remove(it.product_id, it.variation_id || null);
            });
            rowsContainer.appendChild(frag);
        });
        var subtotalEl = root.querySelector('[data-yv-subtotal]');
        if (subtotalEl) subtotalEl.textContent = w.yvShopStore.formatPrice(w.yvShopStore.subtotal());
    }

    w.addEventListener(w.YV_SHOP_CART_EVENT, render);
    d.addEventListener('DOMContentLoaded', render);
})(window, document);
