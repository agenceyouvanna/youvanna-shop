<?php
namespace Youvanna\Shop\Admin;

defined('ABSPATH') || exit;

final class Menu
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenus']);
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
        add_submenu_page('yv-shop', __('Catégories', 'yv-shop'), __('Catégories', 'yv-shop'), 'yv_shop_manage_products', 'edit-tags.php?taxonomy=yv_category');
        add_submenu_page('yv-shop', __('Attributs', 'yv-shop'), __('Attributs', 'yv-shop'), 'yv_shop_manage_products', 'yv-shop-attributes', [AttributesPage::class, 'render']);
        add_submenu_page('yv-shop', __('Réglages', 'yv-shop'), __('Réglages', 'yv-shop'), $cap, 'yv-shop-settings', [Settings::class, 'render']);
    }
}
