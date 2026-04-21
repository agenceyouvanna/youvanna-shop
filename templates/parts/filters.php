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
$chevron = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';

$render_checkboxes = static function (string $attr_key, array $terms, array $selected, int $show_limit = 8): void {
    $has_more = count($terms) > $show_limit;
    ?>
    <ul class="yv-shop-filters__checkboxes<?php echo $has_more ? ' is-expandable' : ''; ?>" data-show-limit="<?php echo (int) $show_limit; ?>">
        <?php foreach ($terms as $i => $t):
            $checked = in_array((int) $t->term_id, $selected, true);
            $hidden = $has_more && $i >= $show_limit && !$checked;
        ?>
            <li<?php echo $hidden ? ' hidden' : ''; ?> data-extra="<?php echo $i >= $show_limit ? '1' : '0'; ?>">
                <label>
                    <input type="checkbox" name="attr_<?php echo esc_attr($attr_key); ?>[]" value="<?php echo (int) $t->term_id; ?>" <?php checked($checked); ?>>
                    <span class="yv-shop-filters__label-text"><?php echo esc_html($t->name); ?></span>
                    <span class="yv-shop-filters__count">(<?php echo (int) $t->count; ?>)</span>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($has_more): ?>
        <button type="button" class="yv-shop-filters__more" data-yv-show-more aria-expanded="false">
            <span class="yv-shop-filters__more-label"><?php esc_html_e('Afficher plus', 'yv-shop'); ?></span>
        </button>
    <?php endif;
};

$render_swatches = static function (string $attr_key, array $terms, array $selected): void {
    ?>
    <ul class="yv-shop-filters__swatches">
        <?php foreach ($terms as $t):
            $checked = in_array((int) $t->term_id, $selected, true);
            $hex = get_term_meta((int) $t->term_id, 'yv_color_hex', true);
            if (!$hex) $hex = '#cccccc';
        ?>
            <li>
                <label class="yv-shop-filters__swatch<?php echo $checked ? ' is-active' : ''; ?>" title="<?php echo esc_attr($t->name); ?>">
                    <input type="checkbox" name="attr_<?php echo esc_attr($attr_key); ?>[]" value="<?php echo (int) $t->term_id; ?>" <?php checked($checked); ?>>
                    <span class="yv-shop-filters__swatch-chip" style="background:<?php echo esc_attr($hex); ?>" aria-hidden="true"></span>
                    <span class="yv-shop-filters__swatch-label"><?php echo esc_html($t->name); ?></span>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};

$render_pills = static function (string $attr_key, array $terms, array $selected): void {
    // Sort numerically by name
    usort($terms, static fn($a, $b) => ((int) $a->name) <=> ((int) $b->name));
    ?>
    <ul class="yv-shop-filters__pills">
        <?php foreach ($terms as $t):
            $checked = in_array((int) $t->term_id, $selected, true);
        ?>
            <li>
                <label class="yv-shop-filters__pill<?php echo $checked ? ' is-active' : ''; ?>">
                    <input type="checkbox" name="attr_<?php echo esc_attr($attr_key); ?>[]" value="<?php echo (int) $t->term_id; ?>" <?php checked($checked); ?>>
                    <span><?php echo esc_html($t->name); ?></span>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
?>
<form method="get" class="yv-shop-filters" action="<?php echo esc_url($shop_url); ?>">

    <div class="yv-shop-filters__head">
        <h2><?php esc_html_e('Filtrer', 'yv-shop'); ?></h2>
        <button type="button" class="yv-shop-filters__close" data-yv-filters-close aria-label="<?php esc_attr_e('Fermer', 'yv-shop'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <div class="yv-shop-filters__group">
        <label class="yv-shop-filters__label" for="yv-search"><?php esc_html_e('Recherche', 'yv-shop'); ?></label>
        <div class="yv-shop-filters__search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="yv-search" name="s" value="<?php echo esc_attr($criteria->query); ?>" placeholder="<?php esc_attr_e('Marque, modèle...', 'yv-shop'); ?>">
        </div>
    </div>

    <div class="yv-shop-filters__group" data-collapsible aria-expanded="true">
        <h3 class="yv-shop-filters__label">
            <?php esc_html_e('Prix', 'yv-shop'); ?>
            <?php echo $chevron; // phpcs:ignore ?>
        </h3>
        <div class="yv-shop-filters__body">
            <div class="yv-shop-filters__price">
                <input type="number" name="min_price" value="<?php echo esc_attr($criteria->min_price !== null ? (string) $criteria->min_price : ''); ?>" placeholder="<?php esc_attr_e('Min', 'yv-shop'); ?>" step="1" min="0" inputmode="numeric">
                <span>-</span>
                <input type="number" name="max_price" value="<?php echo esc_attr($criteria->max_price !== null ? (string) $criteria->max_price : ''); ?>" placeholder="<?php esc_attr_e('Max', 'yv-shop'); ?>" step="1" min="0" inputmode="numeric">
            </div>
        </div>
    </div>

    <?php
    global $wpdb;
    $yv_counts = (array) $wpdb->get_results(
        "SELECT taxonomy, term_id, COUNT(DISTINCT product_id) c
         FROM {$wpdb->prefix}yv_product_terms
         WHERE taxonomy LIKE 'yv_attr_%'
         GROUP BY taxonomy, term_id",
        ARRAY_A
    );
    $count_map = [];
    foreach ($yv_counts as $row) {
        $count_map[$row['taxonomy']][(int) $row['term_id']] = (int) $row['c'];
    }
    ?>
    <?php foreach ($registry as $attr_key => $attr_data):
        $label = is_array($attr_data) ? ($attr_data['label'] ?? $attr_key) : (string) $attr_data;
        $tax = 'yv_attr_' . $attr_key;
        $all_terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
        if (is_wp_error($all_terms) || empty($all_terms)) continue;
        $tax_counts = $count_map[$tax] ?? [];
        $terms = [];
        foreach ($all_terms as $t) {
            $c = $tax_counts[(int) $t->term_id] ?? 0;
            if ($c <= 0) continue;
            $t->count = $c;
            $terms[] = $t;
        }
        if (!$terms) continue;
        $selected = $criteria->attributes[$attr_key] ?? [];
        $is_open = !empty($selected) || in_array($attr_key, ['marque', 'couleur', 'calibre', 'montage'], true);
    ?>
        <div class="yv-shop-filters__group yv-shop-filters__group--<?php echo esc_attr($attr_key); ?>" data-collapsible aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
            <h3 class="yv-shop-filters__label">
                <?php echo esc_html($label); ?>
                <?php echo $chevron; // phpcs:ignore ?>
            </h3>
            <div class="yv-shop-filters__body">
                <?php
                if ($attr_key === 'couleur') {
                    $render_swatches($attr_key, $terms, $selected);
                } elseif ($attr_key === 'calibre') {
                    $render_pills($attr_key, $terms, $selected);
                } else {
                    $render_checkboxes($attr_key, $terms, $selected, 8);
                }
                ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="yv-shop-filters__group">
        <h3 class="yv-shop-filters__label"><?php esc_html_e('Disponibilité', 'yv-shop'); ?></h3>
        <label class="yv-shop-filters__checkbox-single">
            <input type="checkbox" name="in_stock" value="1" <?php checked($criteria->in_stock_only); ?>>
            <?php esc_html_e('En stock uniquement', 'yv-shop'); ?>
        </label>
        <label class="yv-shop-filters__checkbox-single">
            <input type="checkbox" name="on_sale" value="1" <?php checked($criteria->on_sale_only); ?>>
            <?php esc_html_e('Produits en promo', 'yv-shop'); ?>
        </label>
    </div>

    <div class="yv-shop-filters__actions">
        <button type="submit" class="yv-shop-btn yv-shop-btn--primary yv-shop-btn--block"><?php esc_html_e('Appliquer', 'yv-shop'); ?></button>
        <a href="<?php echo esc_url($shop_url); ?>" class="yv-shop-filters__reset" rel="nofollow"><?php esc_html_e('Tout réinitialiser', 'yv-shop'); ?></a>
    </div>
</form>
