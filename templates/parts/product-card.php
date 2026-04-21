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

$categories = wp_get_object_terms((int) $product->id, 'yv_category', ['fields' => 'names']);
$eyebrow = (!is_wp_error($categories) && !empty($categories)) ? $categories[0] : '';

// Brand detection: first word of product name is usually the brand for optical
$name_parts = preg_split('/\s+/', (string) $product->name, 2);
$maybe_brand = (string) ($name_parts[0] ?? '');
if ($maybe_brand && !$eyebrow) {
    $eyebrow = $maybe_brand;
}

$has_lens_config = (bool) get_post_meta((int) $product->id, '_yv_lens_configurable', true);
?>
<article class="yv-shop-card" data-product-id="<?php echo (int) $product->id; ?>">
    <a href="<?php echo esc_url($product->permalink()); ?>" class="yv-shop-card__link">
        <div class="yv-shop-card__media">
            <?php if ($product->featured): ?>
                <span class="yv-shop-badge yv-shop-badge--featured"><?php esc_html_e('Nouveau', 'yv-shop'); ?></span>
            <?php endif; ?>
            <?php if ($product->isOnSale()): ?>
                <span class="yv-shop-badge yv-shop-badge--sale"><?php esc_html_e('Promo', 'yv-shop'); ?></span>
            <?php endif; ?>
            <?php if ($img = $product->imageUrl('medium_large')): ?>
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($product->name); ?>" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="yv-shop-card__media-placeholder" aria-hidden="true"></div>
            <?php endif; ?>
        </div>
        <div class="yv-shop-card__body">
            <?php if ($eyebrow): ?>
                <div class="yv-shop-card__eyebrow"><?php echo esc_html($eyebrow); ?></div>
            <?php endif; ?>
            <h3 class="yv-shop-card__title"><?php echo esc_html($product->name); ?></h3>
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
            </div>
        </div>
    </a>
</article>
