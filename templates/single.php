<?php
/**
 * Template : page produit.
 *
 * @var array $args
 */
defined('ABSPATH') || exit;

use Youvanna\Shop\Helpers\Currency;

/** @var \Youvanna\Shop\Models\Product|null $product */
$product = $GLOBALS['yv_shop_current_product'] ?? null;
if (!$product) {
    get_header();
    echo '<div class="yv-shop-container"><p>' . esc_html__('Produit introuvable.', 'yv-shop') . '</p></div>';
    get_footer();
    return;
}

get_header();
?>

<div class="yv-shop-single">
    <div class="yv-shop-container">

        <div class="yv-shop-single__layout">

            <div class="yv-shop-single__gallery">
                <?php $main = $product->imageUrl('large'); if ($main): ?>
                    <div class="yv-shop-single__main-image">
                        <img id="yv-shop-main-image" src="<?php echo esc_url($main); ?>" alt="<?php echo esc_attr($product->name); ?>">
                    </div>
                <?php endif; ?>
                <?php if (!empty($product->gallery_ids)): ?>
                    <div class="yv-shop-single__thumbs">
                        <?php if ($main): ?>
                            <button type="button" class="yv-shop-single__thumb is-active" data-src="<?php echo esc_url($main); ?>">
                                <img src="<?php echo esc_url($product->imageUrl('thumbnail')); ?>" alt="">
                            </button>
                        <?php endif; ?>
                        <?php foreach ($product->gallery_ids as $gid):
                            $src = wp_get_attachment_image_src((int) $gid, 'thumbnail');
                            $full = wp_get_attachment_image_src((int) $gid, 'large');
                            if (!$src || !$full) continue;
                        ?>
                            <button type="button" class="yv-shop-single__thumb" data-src="<?php echo esc_url($full[0]); ?>">
                                <img src="<?php echo esc_url($src[0]); ?>" alt="">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="yv-shop-single__summary">
                <h1 class="yv-shop-single__title"><?php echo esc_html($product->name); ?></h1>

                <div class="yv-shop-single__price">
                    <?php if ($product->isOnSale()): ?>
                        <del><?php echo esc_html(Currency::format($product->price)); ?></del>
                        <ins><?php echo esc_html(Currency::format($product->activePrice())); ?></ins>
                    <?php else: ?>
                        <span><?php echo esc_html(Currency::format($product->activePrice())); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($product->short_description): ?>
                    <div class="yv-shop-single__short-description"><?php echo wp_kses_post($product->short_description); ?></div>
                <?php endif; ?>

                <div class="yv-shop-single__meta">
                    <?php if ($product->sku): ?>
                        <div><?php esc_html_e('Réf.', 'yv-shop'); ?> <strong><?php echo esc_html($product->sku); ?></strong></div>
                    <?php endif; ?>
                    <div><?php echo $product->isInStock() ? '<span class="yv-shop-instock">' . esc_html__('En stock', 'yv-shop') . '</span>' : '<span class="yv-shop-outofstock">' . esc_html__('Rupture', 'yv-shop') . '</span>'; ?></div>
                </div>

                <?php if ($product->isInStock()): ?>
                    <form class="yv-shop-single__form" data-yv-shop-add-form>
                        <div class="yv-shop-qty">
                            <label for="yv-qty"><?php esc_html_e('Quantité', 'yv-shop'); ?></label>
                            <input type="number" id="yv-qty" name="quantity" value="1" min="1" <?php if ($product->manage_stock) echo 'max="' . (int) $product->stock_qty . '"'; ?>>
                        </div>
                        <button type="submit"
                                class="yv-shop-btn yv-shop-btn--primary yv-shop-btn--large yv-shop-add-to-cart"
                                data-product-id="<?php echo (int) $product->id; ?>"
                                data-product-name="<?php echo esc_attr($product->name); ?>"
                                data-product-price="<?php echo esc_attr((string) $product->activePrice()); ?>"
                                data-product-image="<?php echo esc_attr($product->imageUrl('thumbnail')); ?>">
                            <?php esc_html_e('Ajouter au panier', 'yv-shop'); ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($product->description): ?>
                    <div class="yv-shop-single__description">
                        <h2><?php esc_html_e('Description', 'yv-shop'); ?></h2>
                        <?php echo wp_kses_post($product->description); ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php get_footer();
