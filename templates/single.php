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

// Brand detection pour eyebrow
$raw_name = (string) $product->name;
$name_parts = preg_split('/\s+/', $raw_name, 2);
$brand_eyebrow = (!empty($categories) && !is_wp_error($categories)) ? (string) $categories[0]->name : (string) ($name_parts[0] ?? '');

get_header();
?>

<div class="yv-shop-single">
    <div class="yv-shop-container">

        <div class="yv-shop-single__back-row">
            <a href="<?php echo esc_url($shop_url); ?>" class="yv-shop-single__back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                <?php esc_html_e('Retour', 'yv-shop'); ?>
            </a>
        </div>

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
                                <img src="<?php echo esc_url($product->imageUrl('yv_shop_thumb') ?: $product->imageUrl('medium')); ?>" alt="" loading="lazy">
                            </button>
                        <?php endif; ?>
                        <?php foreach ($product->gallery_ids as $gid):
                            $thumb_src = wp_get_attachment_image_src((int) $gid, 'yv_shop_thumb');
                            if (!$thumb_src) $thumb_src = wp_get_attachment_image_src((int) $gid, 'medium');
                            $full = wp_get_attachment_image_src((int) $gid, 'large');
                            if (!$thumb_src || !$full) continue;
                        ?>
                            <button type="button" class="yv-shop-single__thumb" data-src="<?php echo esc_url($full[0]); ?>">
                                <img src="<?php echo esc_url($thumb_src[0]); ?>" alt="" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="yv-shop-single__main-image" data-yv-zoom>
                    <?php if ($on_sale): ?>
                        <span class="yv-shop-single__badge yv-shop-single__badge--sale"><?php esc_html_e('Promo', 'yv-shop'); ?></span>
                    <?php endif; ?>
                    <button type="button" class="yv-shop-single__zoom-btn" data-yv-zoom-btn aria-label="<?php esc_attr_e('Zoomer', 'yv-shop'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                    </button>
                    <?php if ($main): ?>
                        <img id="yv-shop-main-image" src="<?php echo esc_url($main); ?>" alt="<?php echo esc_attr($product->name); ?>">
                    <?php else: ?>
                        <div class="yv-shop-single__image-placeholder" aria-hidden="true"></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="yv-shop-single__summary">

                <div class="yv-shop-single__price">
                    <?php if ($lens_on): ?>
                        <span class="yv-shop-single__price-from"><?php esc_html_e('À partir de', 'yv-shop'); ?></span>
                    <?php endif; ?>
                    <?php if ($on_sale): ?>
                        <del><?php echo esc_html(Currency::format($product->price)); ?></del>
                        <ins><?php echo esc_html(Currency::format($product->activePrice())); ?></ins>
                    <?php else: ?>
                        <span class="yv-shop-single__price-amount"><?php echo esc_html(Currency::format($product->activePrice())); ?></span>
                    <?php endif; ?>
                    <span class="yv-shop-single__price-tax"><?php esc_html_e('TTC', 'yv-shop'); ?></span>
                </div>

                <h1 class="yv-shop-single__title"><?php echo esc_html($product->name); ?></h1>

                <div class="yv-shop-single__meta">
                    <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                        <div class="yv-shop-single__meta-row">
                            <span class="yv-shop-single__meta-key"><?php esc_html_e('Catégorie', 'yv-shop'); ?></span>
                            <?php $cat_links = [];
                            foreach ($categories as $cat) {
                                $cat_links[] = '<a href="' . esc_url(add_query_arg('yv_category', $cat->slug, $shop_url)) . '">' . esc_html($cat->name) . '</a>';
                            }
                            echo wp_kses_post(implode(', ', $cat_links)); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($brand_eyebrow && $brand_eyebrow !== (!empty($categories) && !is_wp_error($categories) ? (string) $categories[0]->name : '')): ?>
                        <div class="yv-shop-single__meta-row">
                            <span class="yv-shop-single__meta-key"><?php esc_html_e('Marque :', 'yv-shop'); ?></span>
                            <span class="yv-shop-single__meta-value yv-shop-single__meta-value--accent"><?php echo esc_html($brand_eyebrow); ?></span>
                        </div>
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
                        <?php if ($lens_on): ?>
                            <div class="yv-shop-single__actions">
                                <button type="button" class="yv-shop-btn yv-shop-btn--ghost yv-shop-btn--wish" data-yv-wish-toggle="<?php echo (int) $product->id; ?>" aria-pressed="false" aria-label="<?php esc_attr_e('Ajouter aux coups de coeur', 'yv-shop'); ?>" data-label-active="<?php esc_attr_e('Retirer des coups de coeur', 'yv-shop'); ?>" data-label-inactive="<?php esc_attr_e('Ajouter aux coups de coeur', 'yv-shop'); ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                    <span data-yv-wish-label><?php esc_html_e('Ajouter aux coups de coeur', 'yv-shop'); ?></span>
                                </button>
                            </div>
                            <div class="yv-shop-single__actions">
                                <button type="button"
                                        class="yv-shop-btn yv-shop-btn--primary yv-shop-btn--large yv-shop-btn--block"
                                        data-lens-open
                                        data-product-id="<?php echo (int) $product->id; ?>"
                                        data-product-name="<?php echo esc_attr($product->name); ?>"
                                        data-product-price="<?php echo esc_attr((string) $product->activePrice()); ?>"
                                        data-product-image="<?php echo esc_attr($product->imageUrl('yv_shop_thumb') ?: $product->imageUrl('medium')); ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="14" r="4"/><circle cx="18" cy="14" r="4"/><path d="M10 14h4"/><path d="M2 10l4 4M22 10l-4 4"/></svg>
                                    <?php esc_html_e('Configurer mes verres', 'yv-shop'); ?>
                                </button>
                            </div>
                            <?php if ($fitmix_on && $fitmix_key && $fitmix_sku): ?>
                                <div class="yv-shop-single__actions">
                                    <button type="button" class="yv-shop-btn yv-shop-btn--secondary yv-shop-btn--large yv-shop-btn--block" data-fitmix-open data-fitmix-sku="<?php echo esc_attr($fitmix_sku); ?>">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                        <?php esc_html_e('Essayer virtuellement', 'yv-shop'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="yv-shop-single__actions">
                                <button type="button" class="yv-shop-btn yv-shop-btn--ghost yv-shop-btn--wish" data-yv-wish-toggle="<?php echo (int) $product->id; ?>" aria-pressed="false" aria-label="<?php esc_attr_e('Ajouter aux coups de coeur', 'yv-shop'); ?>" data-label-active="<?php esc_attr_e('Retirer des coups de coeur', 'yv-shop'); ?>" data-label-inactive="<?php esc_attr_e('Ajouter aux coups de coeur', 'yv-shop'); ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                    <span data-yv-wish-label><?php esc_html_e('Ajouter aux coups de coeur', 'yv-shop'); ?></span>
                                </button>
                            </div>
                            <div class="yv-shop-single__actions yv-shop-single__actions--cart">
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
                                        data-product-image="<?php echo esc_attr($product->imageUrl('yv_shop_thumb') ?: $product->imageUrl('medium')); ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    <?php esc_html_e('Ajouter au panier', 'yv-shop'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <p class="yv-shop-single__unavailable"><?php esc_html_e('Ce produit n\'est plus disponible pour le moment.', 'yv-shop'); ?></p>
                <?php endif; ?>

                <?php
                do_action('yv_shop_single_summary_end', $product);
                ?>

                <div class="yv-shop-single__reassurance" aria-label="<?php esc_attr_e('Nos garanties', 'yv-shop'); ?>">
                    <div>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        <strong><?php esc_html_e('Livraison offerte', 'yv-shop'); ?></strong>
                        <span><?php esc_html_e('Dès 50 € en France', 'yv-shop'); ?></span>
                    </div>
                    <div>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                        <strong><?php esc_html_e('Paiement sécurisé', 'yv-shop'); ?></strong>
                        <span><?php esc_html_e('CB, Apple Pay, Google Pay', 'yv-shop'); ?></span>
                    </div>
                    <div>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                        <strong><?php esc_html_e('Retours 14 jours', 'yv-shop'); ?></strong>
                        <span><?php esc_html_e('Satisfait ou remboursé', 'yv-shop'); ?></span>
                    </div>
                </div>

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
