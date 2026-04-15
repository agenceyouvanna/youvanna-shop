/*!
 * yv-shop checkout page.
 */
(function (w, d) {
    'use strict';

    var app = d.getElementById('yv-shop-checkout-app');
    if (!app) return;

    function renderSummary() {
        var cart = w.yvShopStore.get();
        var form = app.querySelector('[data-yv-checkout-form]');
        var empty = app.querySelector('[data-yv-empty]');
        if (!cart.items.length) {
            if (empty) empty.hidden = false;
            if (form) form.hidden = true;
            return;
        }
        if (empty) empty.hidden = true;
        if (form) form.hidden = false;

        var itemsBox = form.querySelector('[data-yv-order-items]');
        itemsBox.innerHTML = '';
        cart.items.forEach(function (it) {
            var row = d.createElement('div');
            row.className = 'yv-shop-checkout__line';
            row.innerHTML = '<span>' + escapeHtml(it.name) + ' × ' + it.qty + '</span>' +
                '<strong>' + w.yvShopStore.formatPrice(it.price * it.qty) + '</strong>';
            itemsBox.appendChild(row);
        });

        var subtotal = w.yvShopStore.subtotal();
        form.querySelector('[data-yv-subtotal]').textContent = w.yvShopStore.formatPrice(subtotal);
        form.querySelector('[data-yv-shipping]').textContent = '—';
        form.querySelector('[data-yv-tax]').textContent = '—';
        form.querySelector('[data-yv-total]').textContent = w.yvShopStore.formatPrice(subtotal);
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    var form = app.querySelector('[data-yv-checkout-form]');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var errorBox = form.querySelector('[data-yv-error]');
            var submit = form.querySelector('[data-yv-submit]');
            if (errorBox) { errorBox.hidden = true; errorBox.textContent = ''; }
            submit.disabled = true;

            w.yvShopStore.sync().then(function (synced) {
                if (!synced || !synced.cart_hash) throw new Error('Sync failed');

                var data = new FormData(form);
                var billing = {};
                ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city', 'country'].forEach(function (k) {
                    billing[k] = data.get('billing_' + k) || '';
                });

                var body = {
                    cart_hash: synced.cart_hash,
                    customer_email: data.get('customer_email') || '',
                    customer_phone: data.get('customer_phone') || '',
                    customer_note: data.get('customer_note') || '',
                    billing: billing,
                    shipping: billing,
                    payment_method: data.get('payment_method') || 'bank_transfer'
                };

                return fetch(w.yvShop.rest_url + 'checkout/intent', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': w.yvShop.nonce
                    },
                    body: JSON.stringify(body)
                });
            }).then(function (r) {
                if (!r) throw new Error('No response');
                return r.json().then(function (json) { return { r: r, json: json }; });
            }).then(function (res) {
                if (!res.r.ok) {
                    throw new Error(res.json && res.json.message ? res.json.message : 'Erreur lors de la commande');
                }
                w.yvShopStore.clear();
                if (res.json.redirect_url) {
                    location.href = res.json.redirect_url;
                } else if (res.json.order && res.json.order.view_url) {
                    location.href = res.json.order.view_url;
                } else {
                    location.reload();
                }
            }).catch(function (err) {
                if (errorBox) {
                    errorBox.textContent = err && err.message ? err.message : 'Erreur';
                    errorBox.hidden = false;
                }
                submit.disabled = false;
            });
        });
    }

    d.addEventListener('DOMContentLoaded', renderSummary);
    w.addEventListener(w.YV_SHOP_CART_EVENT, renderSummary);
})(window, document);
