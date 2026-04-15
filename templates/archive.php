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
$criteria->orderby = !empty($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'menu_order';
$criteria->order = !empty($_GET['order']) ? sanitize_key($_GET['order']) : 'asc';

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

get_header();
?>

<div class="yv-shop-archive">
    <div class="yv-shop-container">

        <header class="yv-shop-archive__header">
            <h1 class="yv-shop-archive__title"><?php echo esc_html($shop_title); ?></h1>
            <?php if ($current_category_id && ($desc = term_description($current_category_id, 'yv_category'))): ?>
                <div class="yv-shop-archive__description"><?php echo wp_kses_post($desc); ?></div>
            <?php endif; ?>
            <div class="yv-shop-archive__count">
                <?php echo esc_html(sprintf(_n('%d produit', '%d produits', (int) $result['total'], 'yv-shop'), (int) $result['total'])); ?>
            </div>
        </header>

        <div class="yv-shop-archive__layout">

            <aside class="yv-shop-archive__sidebar">
                <?php TemplateLoader::render('parts/filters.php', ['criteria' => $criteria, 'registry' => $registry]); ?>
            </aside>

            <main class="yv-shop-archive__main">

                <div class="yv-shop-toolbar">
                    <form method="get" class="yv-shop-toolbar__sort">
                        <?php foreach ($_GET as $gk => $gv) {
                            if (in_array($gk, ['orderby', 'order', 'paged'], true)) continue;
                            if (is_array($gv)) {
                                foreach ($gv as $sub) echo '<input type="hidden" name="' . esc_attr($gk) . '[]" value="' . esc_attr($sub) . '">';
                            } else {
                                echo '<input type="hidden" name="' . esc_attr($gk) . '" value="' . esc_attr($gv) . '">';
                            }
                        } ?>
                        <label><?php esc_html_e('Trier par', 'yv-shop'); ?>
                            <select name="orderby" onchange="this.form.submit()">
                                <option value="menu_order" <?php selected($criteria->orderby, 'menu_order'); ?>><?php esc_html_e('Défaut', 'yv-shop'); ?></option>
                                <option value="price" <?php selected($criteria->orderby, 'price'); ?>><?php esc_html_e('Prix', 'yv-shop'); ?></option>
                                <option value="sales_count" <?php selected($criteria->orderby, 'sales_count'); ?>><?php esc_html_e('Populaires', 'yv-shop'); ?></option>
                                <option value="created_at" <?php selected($criteria->orderby, 'created_at'); ?>><?php esc_html_e('Nouveautés', 'yv-shop'); ?></option>
                                <option value="rating_avg" <?php selected($criteria->orderby, 'rating_avg'); ?>><?php esc_html_e('Note', 'yv-shop'); ?></option>
                            </select>
                        </label>
                        <select name="order" onchange="this.form.submit()">
                            <option value="asc" <?php selected($criteria->order, 'asc'); ?>><?php esc_html_e('Croissant', 'yv-shop'); ?></option>
                            <option value="desc" <?php selected($criteria->order, 'desc'); ?>><?php esc_html_e('Décroissant', 'yv-shop'); ?></option>
                        </select>
                    </form>
                </div>

                <?php if (empty($result['items'])): ?>
                    <p class="yv-shop-empty"><?php esc_html_e('Aucun produit trouvé.', 'yv-shop'); ?></p>
                <?php else: ?>
                    <div class="yv-shop-grid">
                        <?php foreach ($result['items'] as $product) {
                            TemplateLoader::render('parts/product-card.php', ['product' => $product]);
                        } ?>
                    </div>

                    <?php if ($result['total_pages'] > 1): ?>
                        <nav class="yv-shop-pagination">
                            <?php echo paginate_links([ // phpcs:ignore
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'current' => $criteria->page,
                                'total' => $result['total_pages'],
                                'prev_text' => __('Précédent', 'yv-shop'),
                                'next_text' => __('Suivant', 'yv-shop'),
                            ]); ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>

            </main>

        </div>
    </div>
</div>

<?php get_footer();
