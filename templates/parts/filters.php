<?php
/**
 * Sidebar filtres.
 *
 * @var array $args
 */
defined('ABSPATH') || exit;

/** @var \Youvanna\Shop\Models\SearchCriteria $criteria */
$criteria = $args['criteria'] ?? null;
$registry = $args['registry'] ?? [];
if (!$criteria) {
    return;
}

$shop_url = home_url('/' . trim(get_option('yv_shop_general_shop_slug', 'boutique'), '/') . '/');
$categories = get_terms(['taxonomy' => 'yv_category', 'hide_empty' => true]);
?>
<form method="get" class="yv-shop-filters" action="<?php echo esc_url($shop_url); ?>">

    <div class="yv-shop-filters__group">
        <label class="yv-shop-filters__label" for="yv-search"><?php esc_html_e('Rechercher', 'yv-shop'); ?></label>
        <input type="search" id="yv-search" name="s" value="<?php echo esc_attr($criteria->query); ?>" placeholder="<?php esc_attr_e('Produit, marque...', 'yv-shop'); ?>">
    </div>

    <?php if (!is_wp_error($categories) && !empty($categories)): ?>
        <div class="yv-shop-filters__group">
            <h3 class="yv-shop-filters__label"><?php esc_html_e('Catégories', 'yv-shop'); ?></h3>
            <ul class="yv-shop-filters__list">
                <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="<?php echo esc_url(trailingslashit($shop_url) . 'categorie/' . $cat->slug . '/'); ?>"
                           class="<?php echo in_array((int) $cat->term_id, $criteria->category_ids, true) ? 'is-active' : ''; ?>">
                            <?php echo esc_html($cat->name); ?>
                            <span>(<?php echo (int) $cat->count; ?>)</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="yv-shop-filters__group">
        <h3 class="yv-shop-filters__label"><?php esc_html_e('Prix', 'yv-shop'); ?></h3>
        <div class="yv-shop-filters__price">
            <input type="number" name="min_price" value="<?php echo esc_attr($criteria->min_price !== null ? (string) $criteria->min_price : ''); ?>" placeholder="<?php esc_attr_e('Min', 'yv-shop'); ?>" step="0.01">
            <span>-</span>
            <input type="number" name="max_price" value="<?php echo esc_attr($criteria->max_price !== null ? (string) $criteria->max_price : ''); ?>" placeholder="<?php esc_attr_e('Max', 'yv-shop'); ?>" step="0.01">
        </div>
    </div>

    <?php foreach ($registry as $attr_key => $attr_data):
        $label = $attr_data['label'] ?? $attr_key;
        $tax = 'yv_attr_' . $attr_key;
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
        if (is_wp_error($terms) || empty($terms)) continue;
        $selected = $criteria->attributes[$attr_key] ?? [];
    ?>
        <div class="yv-shop-filters__group">
            <h3 class="yv-shop-filters__label"><?php echo esc_html($label); ?></h3>
            <ul class="yv-shop-filters__list yv-shop-filters__checkboxes">
                <?php foreach ($terms as $t):
                    $checked = in_array((int) $t->term_id, $selected, true);
                ?>
                    <li>
                        <label>
                            <input type="checkbox" name="attr_<?php echo esc_attr($attr_key); ?>[]" value="<?php echo (int) $t->term_id; ?>" <?php checked($checked); ?>>
                            <span><?php echo esc_html($t->name); ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>

    <div class="yv-shop-filters__group">
        <label class="yv-shop-filters__checkbox-single">
            <input type="checkbox" name="in_stock" value="1" <?php checked($criteria->in_stock_only); ?>>
            <?php esc_html_e('En stock uniquement', 'yv-shop'); ?>
        </label>
        <label class="yv-shop-filters__checkbox-single">
            <input type="checkbox" name="on_sale" value="1" <?php checked($criteria->on_sale_only); ?>>
            <?php esc_html_e('Produits en promo', 'yv-shop'); ?>
        </label>
    </div>

    <button type="submit" class="yv-shop-btn yv-shop-btn--block"><?php esc_html_e('Appliquer', 'yv-shop'); ?></button>
    <a href="<?php echo esc_url($shop_url); ?>" class="yv-shop-filters__reset"><?php esc_html_e('Tout réinitialiser', 'yv-shop'); ?></a>
</form>
