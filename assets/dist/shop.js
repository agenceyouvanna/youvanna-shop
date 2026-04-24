/*!
 * yv-shop archive page interactions.
 * - Legacy add-to-cart on archives
 * - Filter collapsible groups
 * - Filter drawer on mobile
 * - Filter auto-apply (debounced change) + hide Apply button when JS is active
 *
 * Note : la logique wishlist (toggle + badge + page) vit dans wishlist.js,
 * enqueue globalement pour que le badge navbar et les toggles card/single
 * fonctionnent partout, pas juste sur l'archive.
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

    // -----------------------------------------------------------------
    // Filter auto-apply
    // -----------------------------------------------------------------
    (function setupAutoApply() {
        var form = d.querySelector('form.yv-shop-filters');
        if (!form) return;

        // Masque le bouton Appliquer quand JS actif (fallback no-JS conservé)
        var actions = form.querySelector('.yv-shop-filters__actions');
        if (actions) actions.classList.add('is-js-hidden');

        var debounceTimer = null;
        var pending = null;

        function submitForm() {
            // Retire le param paged pour repartir page 1
            var pagedInput = form.querySelector('input[name="paged"]');
            if (pagedInput) pagedInput.parentNode.removeChild(pagedInput);
            // Marque l'état de loading sur la grille (feedback visuel)
            var grid = d.querySelector('.yv-shop-archive__grid, .yv-shop-grid');
            if (grid) grid.classList.add('is-loading');
            form.submit();
        }

        function schedule(delay) {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                debounceTimer = null;
                submitForm();
            }, delay);
        }

        form.addEventListener('change', function (e) {
            var target = e.target;
            if (!target || !target.name) return;

            // Ignore les clics sur les toggles UI internes (aucun input[name] donc déjà filtré)
            var type = (target.type || '').toLowerCase();
            if (type === 'checkbox' || type === 'radio') {
                schedule(150);
            } else if (type === 'number' || type === 'search' || type === 'text') {
                schedule(600);
            } else {
                schedule(300);
            }
        });

        form.addEventListener('input', function (e) {
            var target = e.target;
            if (!target || !target.name) return;
            var type = (target.type || '').toLowerCase();
            if (type === 'number' || type === 'search' || type === 'text') {
                schedule(600);
            }
        });

        // Si l'utilisateur submit manuellement (clic Appliquer en no-JS ou Enter) -> laisser passer
        form.addEventListener('submit', function () {
            if (debounceTimer) { clearTimeout(debounceTimer); debounceTimer = null; }
            var pagedInput = form.querySelector('input[name="paged"]');
            if (pagedInput) pagedInput.parentNode.removeChild(pagedInput);
        });
    })();

})(window, document);
