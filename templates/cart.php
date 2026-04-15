<?php
/**
 * Template : panier.
 */
defined('ABSPATH') || exit;
?>
<div class="yv-shop-cart" id="yv-shop-cart-app">
    <div class="yv-shop-container">

        <h1 class="yv-shop-page-title"><?php esc_html_e('Panier', 'yv-shop'); ?></h1>

        <div class="yv-shop-cart__empty" data-yv-empty hidden>
            <p><?php esc_html_e('Votre panier est vide.', 'yv-shop'); ?></p>
            <a href="<?php echo esc_url(home_url('/' . trim(get_option('yv_shop_general_shop_slug', 'boutique'), '/') . '/')); ?>" class="yv-shop-btn yv-shop-btn--primary">
                <?php esc_html_e('Voir la boutique', 'yv-shop'); ?>
            </a>
        </div>

        <div class="yv-shop-cart__wrapper" data-yv-filled hidden>
            <div class="yv-shop-cart__items">
                <table class="yv-shop-cart__table">
                    <thead>
                        <tr>
                            <th colspan="2"><?php esc_html_e('Produit', 'yv-shop'); ?></th>
                            <th><?php esc_html_e('Prix', 'yv-shop'); ?></th>
                            <th><?php esc_html_e('Quantité', 'yv-shop'); ?></th>
                            <th><?php esc_html_e('Total', 'yv-shop'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-yv-cart-rows></tbody>
                </table>
            </div>

            <aside class="yv-shop-cart__totals">
                <h2><?php esc_html_e('Total', 'yv-shop'); ?></h2>
                <dl>
                    <dt><?php esc_html_e('Sous-total', 'yv-shop'); ?></dt>
                    <dd data-yv-subtotal>—</dd>
                </dl>
                <a href="<?php echo esc_url(get_permalink((int) get_option('yv_shop_checkout_page_id'))); ?>" class="yv-shop-btn yv-shop-btn--primary yv-shop-btn--block yv-shop-btn--large">
                    <?php esc_html_e('Passer commande', 'yv-shop'); ?>
                </a>
            </aside>
        </div>

    </div>

    <template id="yv-shop-cart-row">
        <tr data-row>
            <td class="yv-shop-cart__image"><img data-image alt=""></td>
            <td class="yv-shop-cart__name"><a data-link></a></td>
            <td class="yv-shop-cart__price" data-price></td>
            <td class="yv-shop-cart__qty">
                <input type="number" min="1" data-qty>
            </td>
            <td class="yv-shop-cart__line-total" data-line-total></td>
            <td><button type="button" class="yv-shop-cart__remove" data-remove aria-label="<?php esc_attr_e('Supprimer', 'yv-shop'); ?>">&times;</button></td>
        </tr>
    </template>
</div>
