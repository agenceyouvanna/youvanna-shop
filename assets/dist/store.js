/*!
 * yv-shop store - localStorage-backed cart + REST sync.
 */
(function (w) {
    'use strict';

    var KEY = 'yv_shop_cart_v1';
    var EVT_CHANGED = 'yv-shop:cart-changed';

    function read() {
        try {
            var raw = localStorage.getItem(KEY);
            if (!raw) return { items: [] };
            var parsed = JSON.parse(raw);
            if (!parsed || !Array.isArray(parsed.items)) return { items: [] };
            return parsed;
        } catch (e) {
            return { items: [] };
        }
    }

    function write(cart) {
        try {
            localStorage.setItem(KEY, JSON.stringify(cart));
        } catch (e) {}
        w.dispatchEvent(new CustomEvent(EVT_CHANGED, { detail: cart }));
    }

    function findIndex(cart, productId, variationId, configSig) {
        variationId = variationId || null;
        configSig = configSig || null;
        for (var i = 0; i < cart.items.length; i++) {
            if (cart.items[i].product_id === productId
                && (cart.items[i].variation_id || null) === variationId
                && (cart.items[i].config_sig || null) === configSig) {
                return i;
            }
        }
        return -1;
    }

    function configSignature(config) {
        if (!config || typeof config !== 'object') return null;
        try { return JSON.stringify(config); } catch (e) { return null; }
    }

    var Store = {
        get: read,

        count: function () {
            var c = read();
            return c.items.reduce(function (n, it) { return n + (parseInt(it.qty, 10) || 0); }, 0);
        },

        subtotal: function () {
            var c = read();
            return c.items.reduce(function (s, it) {
                return s + ((parseFloat(it.price) || 0) * (parseInt(it.qty, 10) || 0));
            }, 0);
        },

        add: function (item) {
            var cart = read();
            var sig = configSignature(item.lens_config);
            var idx = findIndex(cart, item.product_id, item.variation_id, sig);
            if (idx >= 0) {
                cart.items[idx].qty = (parseInt(cart.items[idx].qty, 10) || 0) + (parseInt(item.qty, 10) || 1);
            } else {
                cart.items.push({
                    product_id: parseInt(item.product_id, 10),
                    variation_id: item.variation_id ? parseInt(item.variation_id, 10) : null,
                    qty: parseInt(item.qty, 10) || 1,
                    name: item.name || '',
                    price: parseFloat(item.price) || 0,
                    image: item.image || '',
                    permalink: item.permalink || '',
                    lens_config: item.lens_config || null,
                    config_sig: sig
                });
            }
            write(cart);
            return cart;
        },

        setQty: function (productId, variationId, qty, configSig) {
            var cart = read();
            var idx = findIndex(cart, productId, variationId, configSig || null);
            if (idx < 0) return cart;
            qty = parseInt(qty, 10);
            if (!qty || qty < 1) {
                cart.items.splice(idx, 1);
            } else {
                cart.items[idx].qty = qty;
            }
            write(cart);
            return cart;
        },

        remove: function (productId, variationId, configSig) {
            var cart = read();
            var idx = findIndex(cart, productId, variationId, configSig || null);
            if (idx >= 0) {
                cart.items.splice(idx, 1);
                write(cart);
            }
            return cart;
        },

        clear: function () {
            write({ items: [] });
        },

        sync: function () {
            if (!w.yvShop || !w.yvShop.rest_url) return Promise.resolve(null);
            var cart = read();
            return fetch(w.yvShop.rest_url + 'cart/sync', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': w.yvShop.nonce
                },
                body: JSON.stringify({ items: cart.items })
            }).then(function (r) {
                if (!r.ok) throw new Error('sync_failed');
                return r.json();
            });
        },

        formatPrice: function (amount) {
            var opts = w.yvShop || {};
            var symbol = opts.currency_symbol || '€';
            return (parseFloat(amount) || 0).toFixed(2).replace('.', ',') + ' ' + symbol;
        }
    };

    w.yvShopStore = Store;
    w.YV_SHOP_CART_EVENT = EVT_CHANGED;
})(window);
