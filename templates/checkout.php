<?php
/**
 * Template : checkout.
 */
defined('ABSPATH') || exit;

$payment_methods = (array) get_option('yv_shop_payment_methods_enabled', ['bank_transfer']);
$country = get_option('yv_shop_general_country', 'FR');
?>
<div class="yv-shop-checkout" id="yv-shop-checkout-app">
    <div class="yv-shop-container">

        <h1 class="yv-shop-page-title"><?php esc_html_e('Commander', 'yv-shop'); ?></h1>

        <div data-yv-empty hidden>
            <p><?php esc_html_e('Votre panier est vide.', 'yv-shop'); ?></p>
            <a href="<?php echo esc_url(home_url('/' . trim(get_option('yv_shop_general_shop_slug', 'boutique'), '/') . '/')); ?>" class="yv-shop-btn yv-shop-btn--primary">
                <?php esc_html_e('Voir la boutique', 'yv-shop'); ?>
            </a>
        </div>

        <form class="yv-shop-checkout__form" data-yv-checkout-form hidden>

            <div class="yv-shop-checkout__layout">

                <div class="yv-shop-checkout__customer">

                    <section class="yv-shop-checkout__section">
                        <h2><?php esc_html_e('Contact', 'yv-shop'); ?></h2>
                        <label>
                            <span><?php esc_html_e('Email', 'yv-shop'); ?> *</span>
                            <input type="email" name="customer_email" required autocomplete="email">
                        </label>
                        <label>
                            <span><?php esc_html_e('Téléphone', 'yv-shop'); ?></span>
                            <input type="tel" name="customer_phone" autocomplete="tel">
                        </label>
                    </section>

                    <section class="yv-shop-checkout__section">
                        <h2><?php esc_html_e('Adresse de facturation', 'yv-shop'); ?></h2>
                        <div class="yv-shop-checkout__grid">
                            <label>
                                <span><?php esc_html_e('Prénom', 'yv-shop'); ?> *</span>
                                <input type="text" name="billing_first_name" required autocomplete="given-name">
                            </label>
                            <label>
                                <span><?php esc_html_e('Nom', 'yv-shop'); ?> *</span>
                                <input type="text" name="billing_last_name" required autocomplete="family-name">
                            </label>
                        </div>
                        <label>
                            <span><?php esc_html_e('Société', 'yv-shop'); ?></span>
                            <input type="text" name="billing_company" autocomplete="organization">
                        </label>
                        <label>
                            <span><?php esc_html_e('Adresse', 'yv-shop'); ?> *</span>
                            <input type="text" name="billing_address_1" required autocomplete="address-line1">
                        </label>
                        <label>
                            <span><?php esc_html_e('Complément', 'yv-shop'); ?></span>
                            <input type="text" name="billing_address_2" autocomplete="address-line2">
                        </label>
                        <div class="yv-shop-checkout__grid">
                            <label>
                                <span><?php esc_html_e('Code postal', 'yv-shop'); ?> *</span>
                                <input type="text" name="billing_postcode" required autocomplete="postal-code">
                            </label>
                            <label>
                                <span><?php esc_html_e('Ville', 'yv-shop'); ?> *</span>
                                <input type="text" name="billing_city" required autocomplete="address-level2">
                            </label>
                        </div>
                        <label>
                            <span><?php esc_html_e('Pays', 'yv-shop'); ?> *</span>
                            <select name="billing_country" required autocomplete="country">
                                <option value="FR" <?php selected($country, 'FR'); ?>>France</option>
                                <option value="BE" <?php selected($country, 'BE'); ?>>Belgique</option>
                                <option value="CH" <?php selected($country, 'CH'); ?>>Suisse</option>
                                <option value="LU" <?php selected($country, 'LU'); ?>>Luxembourg</option>
                            </select>
                        </label>
                    </section>

                    <section class="yv-shop-checkout__section">
                        <h2><?php esc_html_e('Note (optionnel)', 'yv-shop'); ?></h2>
                        <textarea name="customer_note" rows="3" placeholder="<?php esc_attr_e('Instructions de livraison...', 'yv-shop'); ?>"></textarea>
                    </section>

                    <section class="yv-shop-checkout__section">
                        <h2><?php esc_html_e('Paiement', 'yv-shop'); ?></h2>
                        <?php if (in_array('bank_transfer', $payment_methods, true)): ?>
                            <label class="yv-shop-payment-option">
                                <input type="radio" name="payment_method" value="bank_transfer" checked>
                                <span><?php esc_html_e('Virement bancaire', 'yv-shop'); ?></span>
                            </label>
                        <?php endif; ?>
                        <?php if (in_array('stripe', $payment_methods, true)): ?>
                            <label class="yv-shop-payment-option">
                                <input type="radio" name="payment_method" value="stripe">
                                <span><?php esc_html_e('Carte bancaire', 'yv-shop'); ?></span>
                            </label>
                            <div id="yv-shop-stripe-element" hidden></div>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="yv-shop-checkout__summary">
                    <h2><?php esc_html_e('Votre commande', 'yv-shop'); ?></h2>
                    <div data-yv-order-items></div>
                    <dl class="yv-shop-checkout__totals">
                        <dt><?php esc_html_e('Sous-total', 'yv-shop'); ?></dt><dd data-yv-subtotal>—</dd>
                        <dt><?php esc_html_e('Livraison', 'yv-shop'); ?></dt><dd data-yv-shipping>—</dd>
                        <dt><?php esc_html_e('TVA', 'yv-shop'); ?></dt><dd data-yv-tax>—</dd>
                        <dt class="is-total"><?php esc_html_e('Total', 'yv-shop'); ?></dt><dd class="is-total" data-yv-total>—</dd>
                    </dl>
                    <button type="submit" class="yv-shop-btn yv-shop-btn--primary yv-shop-btn--block yv-shop-btn--large" data-yv-submit>
                        <?php esc_html_e('Confirmer et payer', 'yv-shop'); ?>
                    </button>
                    <p class="yv-shop-checkout__error" data-yv-error hidden></p>
                </aside>
            </div>
        </form>

    </div>
</div>
