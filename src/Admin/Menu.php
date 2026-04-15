<?php
namespace Youvanna\Shop\Admin;

defined('ABSPATH') || exit;

final class Menu
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_filter('admin_body_class', [$this, 'bodyClass']);
    }

    public function addMenus(): void
    {
        $cap = 'yv_shop_manage_settings';
        add_menu_page(
            __('Youvanna Shop', 'yv-shop'),
            __('Youvanna Shop', 'yv-shop'),
            $cap,
            'yv-shop',
            [Dashboard::class, 'render'],
            'dashicons-cart',
            55
        );
        add_submenu_page('yv-shop', __('Tableau de bord', 'yv-shop'), __('Tableau de bord', 'yv-shop'), $cap, 'yv-shop', [Dashboard::class, 'render']);
        add_submenu_page('yv-shop', __('Produits', 'yv-shop'), __('Produits', 'yv-shop'), 'yv_shop_manage_products', 'yv-shop-products', [ProductsListTable::class, 'render']);
        add_submenu_page('yv-shop', __('Commandes', 'yv-shop'), __('Commandes', 'yv-shop'), 'yv_shop_manage_orders', 'yv-shop-orders', [OrdersListTable::class, 'render']);
        add_submenu_page('yv-shop', __('Coupons', 'yv-shop'), __('Coupons', 'yv-shop'), $cap, 'yv-shop-coupons', [CouponsListTable::class, 'render']);
        add_submenu_page('yv-shop', __('Avis clients', 'yv-shop'), __('Avis clients', 'yv-shop'), 'yv_shop_manage_orders', 'yv-shop-reviews', [ReviewsListTable::class, 'render']);
        add_submenu_page('yv-shop', __('Catégories', 'yv-shop'), __('Catégories', 'yv-shop'), 'yv_shop_manage_products', 'edit-tags.php?taxonomy=yv_category');
        add_submenu_page('yv-shop', __('Attributs', 'yv-shop'), __('Attributs', 'yv-shop'), 'yv_shop_manage_products', 'yv-shop-attributes', [AttributesPage::class, 'render']);
        add_submenu_page('yv-shop', __('Import / Export', 'yv-shop'), __('Import / Export', 'yv-shop'), 'yv_shop_manage_products', 'yv-shop-import', [ImportExportPage::class, 'render']);
        add_submenu_page('yv-shop', __('Verres (FittingBox)', 'yv-shop'), __('Verres (FittingBox)', 'yv-shop'), $cap, 'yv-shop-lens', [LensSettingsPage::class, 'render']);
        add_submenu_page('yv-shop', __('Réglages', 'yv-shop'), __('Réglages', 'yv-shop'), $cap, 'yv-shop-settings', [Settings::class, 'render']);
    }

    public function enqueue(string $hook): void
    {
        if (strpos((string) $hook, 'yv-shop') === false && strpos((string) ($_GET['page'] ?? ''), 'yv-shop') === false) {
            return;
        }
        $base = YV_SHOP_URL . 'assets/dist/';
        $ver = YV_SHOP_VERSION;
        wp_enqueue_style('yv-shop-admin', $base . 'admin.css', ['dashicons'], $ver);
        wp_enqueue_script('yv-shop-admin', $base . 'admin.js', ['jquery'], $ver, true);
    }

    public function bodyClass(string $classes): string
    {
        $page = (string) ($_GET['page'] ?? '');
        if (strpos($page, 'yv-shop') === 0) {
            $classes .= ' yv-admin-body';
        }
        return $classes;
    }
}
