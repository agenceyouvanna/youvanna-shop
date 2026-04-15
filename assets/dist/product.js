/*!
 * yv-shop single product page.
 */
(function (w, d) {
    'use strict';

    d.querySelectorAll('.yv-shop-single__thumb').forEach(function (t) {
        t.addEventListener('click', function () {
            var src = t.dataset.src;
            var main = d.getElementById('yv-shop-main-image');
            if (main && src) main.src = src;
            d.querySelectorAll('.yv-shop-single__thumb').forEach(function (x) { x.classList.remove('is-active'); });
            t.classList.add('is-active');
        });
    });

    // Quantity +/- buttons
    d.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-qty-dec], [data-qty-inc]');
        if (!btn) return;
        var input = btn.parentElement.querySelector('input[type="number"]');
        if (!input) return;
        var min = parseInt(input.min || '1', 10);
        var max = input.max ? parseInt(input.max, 10) : Infinity;
        var cur = parseInt(input.value || String(min), 10) || min;
        var next = btn.hasAttribute('data-qty-inc') ? cur + 1 : cur - 1;
        if (next < min) next = min;
        if (next > max) next = max;
        input.value = next;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    d.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-yv-shop-add-form]');
        if (!form) return;
        e.preventDefault();
        var btn = form.querySelector('.yv-shop-add-to-cart');
        if (!btn || !w.yvShopStore) return;
        var qty = parseInt(form.querySelector('[name="quantity"]').value, 10) || 1;
        w.yvShopStore.add({
            product_id: btn.dataset.productId,
            qty: qty,
            name: btn.dataset.productName || '',
            price: btn.dataset.productPrice || 0,
            image: btn.dataset.productImage || '',
            permalink: location.pathname
        });
        var original = btn.textContent;
        btn.textContent = (w.yvShop && w.yvShop.i18n && w.yvShop.i18n.added) || 'Ajouté';
        btn.classList.add('is-added');
        setTimeout(function () {
            btn.textContent = original;
            btn.classList.remove('is-added');
        }, 1500);
    });
})(window, document);
