/*!
 * yv-shop archive page interactions.
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
})(window, document);
