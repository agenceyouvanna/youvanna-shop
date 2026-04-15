<?php
/**
 * Mini panier (header).
 */
defined('ABSPATH') || exit;
?>
<a href="<?php echo esc_url(get_permalink((int) get_option('yv_shop_cart_page_id'))); ?>" class="yv-shop-mini-cart" aria-label="<?php esc_attr_e('Voir le panier', 'yv-shop'); ?>">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="9" cy="20" r="1"/>
        <circle cx="18" cy="20" r="1"/>
        <path d="M2 2h3l2.68 12.39a2 2 0 0 0 2 1.61h7.72a2 2 0 0 0 2-1.61L23 6H6"/>
    </svg>
    <span class="yv-shop-mini-cart-count" data-yv-shop-cart-count>0</span>
</a>
