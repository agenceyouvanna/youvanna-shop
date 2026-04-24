<?php
/**
 * Shortcode [yv_shop_wishlist] : page "coups de coeur".
 * Rendu client-side via wishlist.js (localStorage).
 * Le PHP ne fait qu'émettre le placeholder + un template de carte.
 */
defined('ABSPATH') || exit;
?>
<section class="yv-shop-wishlist">
    <div class="yv-shop-wishlist__intro">
        <p class="yv-shop-wishlist__hint"><?php esc_html_e('Retrouvez ici tous les produits que vous avez ajoutés à vos coups de coeur. La liste est enregistrée sur votre appareil.', 'yv-shop'); ?></p>
    </div>
    <div class="yv-shop-wishlist__content" data-yv-wishlist-page>
        <noscript>
            <p><?php esc_html_e('Votre navigateur n\'autorise pas JavaScript. La liste des coups de coeur nécessite JavaScript.', 'yv-shop'); ?></p>
        </noscript>
    </div>
</section>
