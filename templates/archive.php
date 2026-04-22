<?php
/**
 * Template : archive boutique.
 * Surchargeable via theme/yv-shop/archive.php
 *
 * @var array $args
 */
defined('ABSPATH') || exit;

use Youvanna\Shop\Frontend\TemplateLoader;
use Youvanna\Shop\Models\SearchCriteria;
use Youvanna\Shop\Repositories\ProductRepository;

$repo = new ProductRepository();

$criteria = new SearchCriteria();
$criteria->page = max(1, (int) get_query_var('paged', 1));
$criteria->per_page = (int) get_option('yv_shop_products_per_page', 12);
$criteria->statuses = ['published'];

$sort_raw = !empty($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'menu_order';
if (strpos($sort_raw, '|') !== false) {
    [$ob, $od] = explode('|', $sort_raw, 2);
    $criteria->orderby = sanitize_key($ob);
    $criteria->order = sanitize_key($od);
} else {
    $criteria->orderby = sanitize_key($sort_raw);
    $criteria->order = !empty($_GET['order']) ? sanitize_key(wp_unslash($_GET['order'])) : 'asc';
}

$current_category_id = null;
$cat_slug = get_query_var('yv_category');
if ($cat_slug) {
    $term = get_term_by('slug', $cat_slug, 'yv_category');
    if ($term) {
        $current_category_id = (int) $term->term_id;
        $criteria->category_ids = [$current_category_id];
    }
}

if (!empty($_GET['s'])) {
    $criteria->query = sanitize_text_field(wp_unslash($_GET['s']));
}
if (!empty($_GET['min_price'])) {
    $criteria->min_price = (float) $_GET['min_price'];
}
if (!empty($_GET['max_price'])) {
    $criteria->max_price = (float) $_GET['max_price'];
}
if (!empty($_GET['in_stock'])) {
    $criteria->in_stock_only = true;
}
if (!empty($_GET['on_sale'])) {
    $criteria->on_sale_only = true;
}

$registry = (array) get_option('yv_shop_attributes', []);
foreach ($registry as $attr_key => $_data) {
    $q_key = 'attr_' . $attr_key;
    if (!empty($_GET[$q_key])) {
        $criteria->attributes[$attr_key] = array_map('intval', (array) $_GET[$q_key]);
    }
}

$result = $repo->search($criteria);
$shop_title = $current_category_id ? get_term($current_category_id)->name : __('Boutique', 'yv-shop');
$shop_url = home_url('/' . trim((string) get_option('yv_shop_general_shop_slug', 'boutique'), '/') . '/');

$all_categories = get_terms(['taxonomy' => 'yv_category', 'hide_empty' => false]);
if (is_wp_error($all_categories)) $all_categories = [];

// Calcul de la pagination affichage "X-Y sur Z resultats"
$per_page = (int) $criteria->per_page;
$total = (int) $result['total'];
$range_from = $total > 0 ? (($criteria->page - 1) * $per_page) + 1 : 0;
$range_to = min($criteria->page * $per_page, $total);

$active_chips = [];
if ($criteria->query) {
    $active_chips[] = [
        'label' => __('Recherche', 'yv-shop'),
        'value' => $criteria->query,
        'remove_key' => ['s'],
    ];
}
if ($criteria->min_price !== null) {
    $active_chips[] = [
        'label' => __('Prix min', 'yv-shop'),
        'value' => number_format((float) $criteria->min_price, 0, ',', ' ') . ' €',
        'remove_key' => ['min_price'],
    ];
}
if ($criteria->max_price !== null) {
    $active_chips[] = [
        'label' => __('Prix max', 'yv-shop'),
        'value' => number_format((float) $criteria->max_price, 0, ',', ' ') . ' €',
        'remove_key' => ['max_price'],
    ];
}
if ($criteria->in_stock_only) {
    $active_chips[] = [
        'label' => __('Dispo', 'yv-shop'),
        'value' => __('En stock', 'yv-shop'),
        'remove_key' => ['in_stock'],
    ];
}
if ($criteria->on_sale_only) {
    $active_chips[] = [
        'label' => __('Offre', 'yv-shop'),
        'value' => __('En promo', 'yv-shop'),
        'remove_key' => ['on_sale'],
    ];
}
foreach ($criteria->attributes as $attr_key => $term_ids) {
    $label = $registry[$attr_key]['label'] ?? ucfirst($attr_key);
    foreach ($term_ids as $tid) {
        $t = get_term((int) $tid);
        if (!$t || is_wp_error($t)) continue;
        $active_chips[] = [
            'label' => $label,
            'value' => $t->name,
            'remove_key' => ['attr_' . $attr_key, (int) $tid],
        ];
    }
}

$filter_count = count($active_chips);

$build_remove_url = static function (array $remove) use ($shop_url): string {
    $qs = $_GET;
    $key = $remove[0];
    if (!isset($remove[1])) {
        unset($qs[$key]);
    } else {
        $needle = (string) $remove[1];
        if (isset($qs[$key]) && is_array($qs[$key])) {
            $qs[$key] = array_values(array_filter($qs[$key], static fn($v) => (string) $v !== $needle));
            if (!$qs[$key]) unset($qs[$key]);
        } else {
            unset($qs[$key]);
        }
    }
    unset($qs['paged']);
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
    $base = home_url($path);
    return $qs ? add_query_arg($qs, $base) : $base;
};

// Hero configurable via options yv_shop_hero_image_id / yv_shop_hero_eyebrow / yv_shop_hero_brands
$hero_image_id = (int) get_option('yv_shop_hero_image_id', 0);
$hero_image_url = $hero_image_id ? wp_get_attachment_image_url($hero_image_id, 'full') : '';
$hero_eyebrow = (string) get_option('yv_shop_hero_eyebrow', __('Notre collection', 'yv-shop'));
$hero_brands_raw = (string) get_option('yv_shop_hero_brands', '');
$hero_brands = array_values(array_filter(array_map('trim', explode(',', $hero_brands_raw))));

get_header();
?>

<div class="yv-shop-archive">

    <?php if ($hero_image_url): ?>
        <section class="yv-shop-archive__hero-banner" style="background-image: url('<?php echo esc_url($hero_image_url); ?>');">
            <div class="yv-shop-archive__hero-overlay"></div>
            <?php if (!empty($hero_brands)): ?>
                <div class="yv-shop-archive__hero-brands" aria-hidden="true">
                    <?php foreach ($hero_brands as $brand): ?>
                        <span><?php echo esc_html($brand); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="yv-shop-archive__hero-banner yv-shop-archive__hero-banner--plain">
            <div class="yv-shop-archive__hero-inner">
                <p class="yv-shop-archive__hero-eyebrow"><?php echo esc_html($hero_eyebrow); ?></p>
                <h1 class="yv-shop-archive__hero-title"><?php echo esc_html($shop_title); ?></h1>
            </div>
        </section>
    <?php endif; ?>

    <div class="yv-shop-container">

        <?php if ($hero_image_url): ?>
            <header class="yv-shop-archive__head">
                <p class="yv-shop-archive__eyebrow"><?php echo esc_html($hero_eyebrow); ?></p>
                <h1 class="yv-shop-archive__title"><?php echo esc_html($shop_title); ?></h1>
                <?php if ($current_category_id && ($desc = term_description($current_category_id, 'yv_category'))): ?>
                    <div class="yv-shop-archive__description"><?php echo wp_kses_post($desc); ?></div>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if (!empty($all_categories) && count($all_categories) > 1): ?>
            <nav class="yv-shop-cat-pills" aria-label="<?php esc_attr_e('Catégories', 'yv-shop'); ?>">
                <a href="<?php echo esc_url($shop_url); ?>"
                   class="yv-shop-cat-pill <?php echo $current_category_id ? '' : 'is-active'; ?>">
                    <?php esc_html_e('Tout voir', 'yv-shop'); ?>
                </a>
                <?php foreach ($all_categories as $cat): ?>
                    <a href="<?php echo esc_url(trailingslashit($shop_url) . 'categorie/' . $cat->slug . '/'); ?>"
                       class="yv-shop-cat-pill <?php echo ((int) $cat->term_id === $current_category_id) ? 'is-active' : ''; ?>">
                        <?php echo esc_html($cat->name); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="yv-shop-archive__layout">

            <aside class="yv-shop-archive__sidebar" data-yv-filters>
                <?php TemplateLoader::render('parts/filters.php', ['criteria' => $criteria, 'registry' => $registry]); ?>
            </aside>

            <main class="yv-shop-archive__main">

                <div class="yv-shop-toolbar">
                    <div class="yv-shop-toolbar__count">
                        <?php if ($total > 0): ?>
                            <?php printf(
                                /* translators: 1: range from, 2: range to, 3: total */
                                esc_html__('Affichage de %1$s-%2$s sur %3$s résultats', 'yv-shop'),
                                '<strong>' . (int) $range_from . '</strong>',
                                '<strong>' . (int) $range_to . '</strong>',
                                '<strong>' . (int) $total . '</strong>'
                            ); ?>
                        <?php else: ?>
                            <?php esc_html_e('Aucun produit', 'yv-shop'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="yv-shop-toolbar__actions">
                        <button type="button" class="yv-shop-toolbar__filter-btn" data-yv-filters-open aria-expanded="false">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
                            <?php esc_html_e('Filtrer', 'yv-shop'); ?>
                            <?php if ($filter_count > 0): ?>
                                <span class="yv-shop-toolbar__filter-btn__count"><?php echo (int) $filter_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <form method="get" class="yv-shop-toolbar__sort">
                            <?php foreach ($_GET as $gk => $gv) {
                                if (in_array($gk, ['orderby', 'order', 'paged'], true)) continue;
                                if (is_array($gv)) {
                                    foreach ($gv as $sub) echo '<input type="hidden" name="' . esc_attr($gk) . '[]" value="' . esc_attr((string) $sub) . '">';
                                } else {
                                    echo '<input type="hidden" name="' . esc_attr($gk) . '" value="' . esc_attr((string) $gv) . '">';
                                }
                            } ?>
                            <label for="yv-shop-sort" class="yv-shop-toolbar__sort-label"><?php esc_html_e('Trier par', 'yv-shop'); ?></label>
                            <select id="yv-shop-sort" name="orderby" onchange="this.form.submit()">
                                <option value="menu_order" <?php selected($criteria->orderby, 'menu_order'); ?>><?php esc_html_e('Tri par défaut', 'yv-shop'); ?></option>
                                <option value="price|asc" <?php selected($criteria->orderby . '|' . $criteria->order, 'price|asc'); ?>><?php esc_html_e('Prix croissant', 'yv-shop'); ?></option>
                                <option value="price|desc" <?php selected($criteria->orderby . '|' . $criteria->order, 'price|desc'); ?>><?php esc_html_e('Prix décroissant', 'yv-shop'); ?></option>
                                <option value="sales_count|desc" <?php selected($criteria->orderby . '|' . $criteria->order, 'sales_count|desc'); ?>><?php esc_html_e('Plus populaires', 'yv-shop'); ?></option>
                                <option value="created_at|desc" <?php selected($criteria->orderby . '|' . $criteria->order, 'created_at|desc'); ?>><?php esc_html_e('Nouveautés', 'yv-shop'); ?></option>
                                <option value="rating_avg|desc" <?php selected($criteria->orderby . '|' . $criteria->order, 'rating_avg|desc'); ?>><?php esc_html_e('Mieux notés', 'yv-shop'); ?></option>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if (!empty($active_chips)): ?>
                    <div class="yv-shop-active-filters">
                        <?php foreach ($active_chips as $chip): ?>
                            <a href="<?php echo esc_url($build_remove_url($chip['remove_key'])); ?>" class="yv-shop-chip" rel="nofollow">
                                <span class="yv-shop-chip__key"><?php echo esc_html($chip['label']); ?>:</span>
                                <span><?php echo esc_html($chip['value']); ?></span>
                                <span class="yv-shop-chip__close" aria-hidden="true">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </span>
                            </a>
                        <?php endforeach; ?>
                        <a href="<?php echo esc_url($shop_url); ?>" class="yv-shop-chip--reset" rel="nofollow">
                            <?php esc_html_e('Tout effacer', 'yv-shop'); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if (empty($result['items'])): ?>
                    <div class="yv-shop-empty">
                        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <h3><?php esc_html_e('Aucun produit trouvé', 'yv-shop'); ?></h3>
                        <p><?php esc_html_e('Essayez de modifier vos critères de recherche ou de filtrage.', 'yv-shop'); ?></p>
                        <a href="<?php echo esc_url($shop_url); ?>" class="yv-shop-btn"><?php esc_html_e('Réinitialiser les filtres', 'yv-shop'); ?></a>
                    </div>
                <?php else: ?>
                    <div class="yv-shop-grid">
                        <?php foreach ($result['items'] as $product) {
                            TemplateLoader::render('parts/product-card.php', ['product' => $product]);
                        } ?>
                    </div>

                    <?php if ($result['total_pages'] > 1): ?>
                        <nav class="yv-shop-pagination" aria-label="<?php esc_attr_e('Pagination', 'yv-shop'); ?>">
                            <?php echo paginate_links([ // phpcs:ignore
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'current' => $criteria->page,
                                'total' => $result['total_pages'],
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ]); ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>

            </main>

        </div>
    </div>

    <div class="yv-shop-filters-backdrop" data-yv-filters-close aria-hidden="true"></div>
</div>

<?php get_footer();
