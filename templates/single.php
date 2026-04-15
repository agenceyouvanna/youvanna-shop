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

$main = $product->imageUrl('large');
$on_sale = $product->isOnSale();
$in_stock = $product->isInStock();
$categories = function_exists('wp_get_object_terms') ? wp_get_object_terms($product->id, 'yv_category', ['fields' => 'all']) : [];
$shop_url = home_url('/' . trim((string) get_option('yv_shop_general_shop_slug', 'boutique'), '/') . '/');
$lens_on = (bool) get_post_meta((int) $product->id, '_yv_lens_configurable', true);
$fitmix_on = (bool) get_post_meta((int) $product->id, '_yv_fitmix_enabled', true);
$fitmix_sku = (string) get_post_meta((int) $product->id, '_yv_fitmix_sku', true);
$fitmix_key = (string) get_option('yv_shop_fitmix_key', '');

get_header();
?>

<div class="yv-shop-single">
    <div class="yv-shop-container">

        <nav class="yv-shop-breadcrumb" aria-label="<?php esc_attr_e('Fil d\'Ariane', 'yv-shop'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'yv-shop'); ?></a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Boutique', 'yv-shop'); ?></a>
            <?php if (!empty($categories) && !is_wp_error($categories)): $cat = $categories[0]; ?>
                <span aria-hidden="true">/</span>
                <a href="<?php echo esc_url(add_query_arg('yv_category', $cat->slug, $shop_url)); ?>"><?php echo esc_html($cat->name); ?></a>
            <?php endif; ?>
            <span aria-hidden="true">/</span>
            <span class="is-current"><?php echo esc_html($product->name); ?></span>
        </nav>

        <div class="yv-shop-single__hero">

            <div class="yv-shop-single__gallery">
                <?php if (!empty($product->gallery_ids)): ?>
                    <div class="yv-shop-single__thumbs" role="tablist">
                        <?php if ($main): ?>
                            <button type="button" class="yv-shop-single__thumb is-active" data-src="<?php echo esc_url($main); ?>" aria-label="<?php esc_attr_e('Image principale', 'yv-shop'); ?>">
                                <img src="<?php echo esc_url($product->imageUrl('thumbnail')); ?>" alt="" loading="lazy">
                            </button>
                        <?php endif; ?>
                        <?php foreach ($product->gallery_ids as $gid):
                            $src = wp_get_attachment_image_src((int) $gid, 'thumbnail');
                            $full = wp_get_attachment_image_src((int) $gid, 'large');
                            if (!$src || !$full) continue;
                        ?>
                            <button type="button" class="yv-shop-single__thumb" data-src="<?php echo esc_url($full[0]); ?>">
                                <img src="<?php echo esc_url($src[0]); ?>" alt="" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="yv-shop-single__main-image">
                    <?php if ($on_sale): ?>
                        <span class="yv-shop-single__badge yv-shop-single__badge--sale"><?php esc_html_e('Promo', 'yv-shop'); ?></span>
                    <?php endif; ?>
                    <?php if ($main): ?>
                        <img id="yv-shop-main-image" src="<?php echo esc_url($main); ?>" alt="<?php echo esc_attr($product->name); ?>">
                    <?php else: ?>
                        <div class="yv-shop-single__image-placeholder" aria-hidden="true"></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="yv-shop-single__summary">

                <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                    <div class="yv-shop-single__eyebrow"><?php echo esc_html($categories[0]->name); ?></div>
                <?php endif; ?>

                <h1 class="yv-shop-single__title"><?php echo esc_html($product->name); ?></h1>

                <div class="yv-shop-single__price">
                    <?php if ($on_sale): ?>
                        <del><?php echo esc_html(Currency::format($product->price)); ?></del>
                        <ins><?php echo esc_html(Currency::format($product->activePrice())); ?></ins>
                    <?php else: ?>
                        <span><?php echo esc_html(Currency::format($product->activePrice())); ?></span>
                    <?php endif; ?>
                </div>

                <div class="yv-shop-single__availability">
                    <?php if ($in_stock): ?>
                        <span class="yv-shop-pill yv-shop-pill--success">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php esc_html_e('En stock', 'yv-shop'); ?>
                        </span>
                    <?php else: ?>
                        <span class="yv-shop-pill yv-shop-pill--danger"><?php esc_html_e('Rupture', 'yv-shop'); ?></span>
                    <?php endif; ?>
                    <?php if ($product->sku): ?>
                        <span class="yv-shop-single__sku"><?php esc_html_e('Réf.', 'yv-shop'); ?> <?php echo esc_html($product->sku); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($product->short_description): ?>
                    <div class="yv-shop-single__short-description"><?php echo wp_kses_post($product->short_description); ?></div>
                <?php endif; ?>

                <?php if ($in_stock): ?>
                    <form class="yv-shop-single__form" data-yv-shop-add-form>
                        <div class="yv-shop-single__actions">
                            <?php if ($lens_on): ?>
                                <button type="button"
                                        class="yv-shop-btn yv-shop-btn--primary yv-shop-btn--large"
                                        data-lens-open
                                        data-product-id="<?php echo (int) $product->id; ?>"
                                        data-product-name="<?php echo esc_attr($product->name); ?>"
                                        data-product-price="<?php echo esc_attr((string) $product->activePrice()); ?>"
                                        data-product-image="<?php echo esc_attr($product->imageUrl('thumbnail')); ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="14" r="4"/><circle cx="18" cy="14" r="4"/><path d="M10 14h4"/><path d="M2 10l4 4M22 10l-4 4"/></svg>
                                    <?php esc_html_e('Configurer mes verres', 'yv-shop'); ?>
                                </button>
                                <?php if ($fitmix_on && $fitmix_key && $fitmix_sku): ?>
                                    <button type="button" class="yv-shop-btn yv-shop-btn--secondary yv-shop-btn--large" data-fitmix-open data-fitmix-sku="<?php echo esc_attr($fitmix_sku); ?>">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                        <?php esc_html_e('Essayer', 'yv-shop'); ?>
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="yv-shop-qty">
                                    <button type="button" class="yv-shop-qty__btn" data-qty-dec aria-label="<?php esc_attr_e('Diminuer la quantité', 'yv-shop'); ?>">-</button>
                                    <input type="number" id="yv-qty" name="quantity" value="1" min="1" <?php if ($product->manage_stock) echo 'max="' . (int) $product->stock_qty . '"'; ?>>
                                    <button type="button" class="yv-shop-qty__btn" data-qty-inc aria-label="<?php esc_attr_e('Augmenter la quantité', 'yv-shop'); ?>">+</button>
                                </div>
                                <button type="submit"
                                        class="yv-shop-btn yv-shop-btn--primary yv-shop-btn--large yv-shop-add-to-cart"
                                        data-product-id="<?php echo (int) $product->id; ?>"
                                        data-product-name="<?php echo esc_attr($product->name); ?>"
                                        data-product-price="<?php echo esc_attr((string) $product->activePrice()); ?>"
                                        data-product-image="<?php echo esc_attr($product->imageUrl('thumbnail')); ?>">
                                    <?php esc_html_e('Ajouter au panier', 'yv-shop'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="yv-shop-single__unavailable"><?php esc_html_e('Ce produit n\'est plus disponible pour le moment.', 'yv-shop'); ?></p>
                <?php endif; ?>

                <?php
                do_action('yv_shop_single_summary_end', $product);
                ?>

            </div>
        </div>

        <?php if ($product->description): ?>
            <section class="yv-shop-single__description">
                <h2><?php esc_html_e('Description', 'yv-shop'); ?></h2>
                <?php echo wp_kses_post($product->description); ?>
            </section>
        <?php endif; ?>

        <?php do_action('yv_shop_single_after_description', $product); ?>

    </div>
</div>

<?php if ($lens_on) {
    $lens_modal = YV_SHOP_DIR . '/templates/parts/lens-modal.php';
    if (file_exists($lens_modal)) {
        include $lens_modal;
    }
} ?>

<?php get_footer();
