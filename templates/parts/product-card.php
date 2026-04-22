<?php
/**
 * Card produit (grille archive).
 *
 * @var array $args
 */
defined('ABSPATH') || exit;

/** @var \Youvanna\Shop\Models\Product $product */
$product = $args['product'] ?? null;
if (!$product) {
    return;
}

use Youvanna\Shop\Helpers\Currency;

// Brand detection: first word of product name is usually the brand for optical
$raw_name = (string) $product->name;
$name_parts = preg_split('/\s+/', $raw_name, 2);
$brand = (string) ($name_parts[0] ?? '');
$rest_name = (string) ($name_parts[1] ?? '');

// Si categorie explicite differente de la marque detectee, on la prefere
$categories = wp_get_object_terms((int) $product->id, 'yv_category', ['fields' => 'names']);
if (!is_wp_error($categories) && !empty($categories)) {
    $brand = (string) $categories[0];
    $rest_name = $raw_name;
}

$has_lens_config = (bool) get_post_meta((int) $product->id, '_yv_lens_configurable', true);

// CTA label selon config verres / stock / promo
$in_stock = $product->isInStock();
if (!$in_stock) {
    $cta_label = __('Voir le produit', 'yv-shop');
} elseif ($has_lens_config) {
    $cta_label = __('Choix des options', 'yv-shop');
} else {
    $cta_label = __('Ajouter au panier', 'yv-shop');
}
?>
<article class="yv-shop-card" data-product-id="<?php echo (int) $product->id; ?>">
    <a href="<?php echo esc_url($product->permalink()); ?>" class="yv-shop-card__media-link" aria-label="<?php echo esc_attr($raw_name); ?>">
        <div class="yv-shop-card__media">
            <?php if ($product->featured): ?>
                <span class="yv-shop-badge yv-shop-badge--featured"><?php esc_html_e('Nouveau', 'yv-shop'); ?></span>
            <?php endif; ?>
            <?php if ($product->isOnSale()): ?>
                <span class="yv-shop-badge yv-shop-badge--sale"><?php esc_html_e('Promo', 'yv-shop'); ?></span>
            <?php endif; ?>
            <?php if ($img = $product->imageUrl('yv_shop_card')): ?>
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($raw_name); ?>" loading="lazy" decoding="async">
            <?php elseif ($img = $product->imageUrl('medium_large')): ?>
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($raw_name); ?>" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="yv-shop-card__media-placeholder" aria-hidden="true"></div>
            <?php endif; ?>
        </div>
    </a>
    <div class="yv-shop-card__body">
        <?php if ($brand): ?>
            <div class="yv-shop-card__eyebrow"><?php echo esc_html($brand); ?></div>
        <?php endif; ?>
        <h3 class="yv-shop-card__title">
            <a href="<?php echo esc_url($product->permalink()); ?>">
                <?php echo esc_html($rest_name !== '' ? $rest_name : $raw_name); ?>
            </a>
        </h3>
        <div class="yv-shop-card__price">
            <?php if ($has_lens_config): ?>
                <span class="yv-shop-card__from"><?php esc_html_e('À partir de', 'yv-shop'); ?></span>
            <?php endif; ?>
            <?php if ($product->isOnSale()): ?>
                <del><?php echo esc_html(Currency::format($product->price)); ?></del>
                <ins><?php echo esc_html(Currency::format($product->activePrice())); ?></ins>
            <?php else: ?>
                <span><?php echo esc_html(Currency::format($product->activePrice())); ?></span>
            <?php endif; ?>
            <span class="yv-shop-card__tax"><?php esc_html_e('TTC', 'yv-shop'); ?></span>
        </div>
        <div class="yv-shop-card__actions">
            <a href="<?php echo esc_url($product->permalink()); ?>" class="yv-shop-card__cta">
                <?php echo esc_html($cta_label); ?>
            </a>
            <button type="button" class="yv-shop-card__wish" data-yv-wish data-product-id="<?php echo (int) $product->id; ?>" aria-label="<?php esc_attr_e('Ajouter aux coups de coeur', 'yv-shop'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span><?php esc_html_e('Ajouter aux coups de coeur', 'yv-shop'); ?></span>
            </button>
        </div>
    </div>
</article>
