/*!
 * yv-shop single product page.
 * - Gallery thumbnails switcher.
 * - Qty +/- buttons.
 * - Add-to-cart form.
 * - Image lightbox on main image click.
 * Note : la logique wishlist (toggle + badge + page) vit dans wishlist.js.
 */
(function (w, d) {
    'use strict';

    // Gallery thumbnails
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

    // Add to cart submit
    d.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-yv-shop-add-form]');
        if (!form) return;
        e.preventDefault();
        var btn = form.querySelector('.yv-shop-add-to-cart');
        if (!btn || !w.yvShopStore) return;
        var qtyInput = form.querySelector('[name="quantity"]');
        var qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
        w.yvShopStore.add({
            product_id: btn.dataset.productId,
            qty: qty,
            name: btn.dataset.productName || '',
            price: btn.dataset.productPrice || 0,
            image: btn.dataset.productImage || '',
            permalink: location.pathname
        });
        var original = btn.textContent;
        btn.textContent = (w.yvShop && w.yvShop.i18n && w.yvShop.i18n.added) || 'Ajoute';
        btn.classList.add('is-added');
        setTimeout(function () {
            btn.textContent = original;
            btn.classList.remove('is-added');
        }, 1500);
    });

    // Image lightbox
    var mainImg = d.getElementById('yv-shop-main-image');
    var zoomBtn = d.querySelector('[data-yv-zoom-btn]');

    function openLightbox(src) {
        var overlay = d.createElement('div');
        overlay.className = 'yv-shop-lightbox';
        overlay.innerHTML = '<button type="button" class="yv-shop-lightbox__close" aria-label="Fermer">' +
            '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button>' +
            '<div class="yv-shop-lightbox__inner"><img src="' + src + '" alt=""></div>';
        d.body.appendChild(overlay);
        d.documentElement.classList.add('yv-shop-lightbox-open');

        function close() {
            overlay.remove();
            d.documentElement.classList.remove('yv-shop-lightbox-open');
            d.removeEventListener('keydown', onKey);
        }
        function onKey(e) { if (e.key === 'Escape') close(); }
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.closest('.yv-shop-lightbox__close')) close();
        });
        d.addEventListener('keydown', onKey);
    }

    if (mainImg) {
        mainImg.addEventListener('click', function () { openLightbox(mainImg.src); });
    }
    if (zoomBtn && mainImg) {
        zoomBtn.addEventListener('click', function (e) { e.stopPropagation(); openLightbox(mainImg.src); });
    }

})(window, document);
