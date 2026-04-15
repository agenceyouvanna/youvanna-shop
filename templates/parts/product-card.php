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
?>
<article class="yv-shop-card" data-product-id="<?php echo (int) $product->id; ?>">
    <a href="<?php echo esc_url($product->permalink()); ?>" class="yv-shop-card__link">
        <div class="yv-shop-card__media">
            <?php if ($img = $product->imageUrl('medium_large')): ?>
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($product->name); ?>" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="yv-shop-card__media-placeholder"></div>
            <?php endif; ?>
            <?php if ($product->isOnSale()): ?>
                <span class="yv-shop-badge yv-shop-badge--sale"><?php esc_html_e('Promo', 'yv-shop'); ?></span>
            <?php endif; ?>
            <?php if ($product->featured): ?>
                <span class="yv-shop-badge yv-shop-badge--featured"><?php esc_html_e('Nouveau', 'yv-shop'); ?></span>
            <?php endif; ?>
        </div>
        <div class="yv-shop-card__body">
            <h3 class="yv-shop-card__title"><?php echo esc_html($product->name); ?></h3>
            <div class="yv-shop-card__price">
                <?php if ($product->isOnSale()): ?>
                    <del><?php echo esc_html(Currency::format($product->price)); ?></del>
                    <ins><?php echo esc_html(Currency::format($product->activePrice())); ?></ins>
                <?php else: ?>
                    <span><?php echo esc_html(Currency::format($product->activePrice())); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <div class="yv-shop-card__actions">
        <?php if ($product->isInStock()): ?>
            <button type="button" class="yv-shop-btn yv-shop-btn--primary yv-shop-add-to-cart"
                    data-product-id="<?php echo (int) $product->id; ?>"
                    data-product-name="<?php echo esc_attr($product->name); ?>"
                    data-product-price="<?php echo esc_attr((string) $product->activePrice()); ?>"
                    data-product-image="<?php echo esc_attr($product->imageUrl('thumbnail')); ?>">
                <?php esc_html_e('Ajouter au panier', 'yv-shop'); ?>
            </button>
        <?php else: ?>
            <span class="yv-shop-outofstock"><?php esc_html_e('Rupture', 'yv-shop'); ?></span>
        <?php endif; ?>
    </div>
</article>
