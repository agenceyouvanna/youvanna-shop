<?php
namespace Youvanna\Shop;

defined('ABSPATH') || exit;

final class Activator
{
    public static function activate(): void
    {
        // Run migrations
        (new Migrator())->run();

        // Default options (only set if not already set)
        $defaults = self::defaultOptions();
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value, '', 'no'); // autoload no
            }
        }

        // Generate encryption key (independent of AUTH_KEY)
        if (!get_option('yv_shop_encryption_key')) {
            add_option('yv_shop_encryption_key', base64_encode(random_bytes(32)), '', 'no');
        }

        // HMAC secret for cart signing
        if (!get_option('yv_shop_cart_hmac_secret')) {
            add_option('yv_shop_cart_hmac_secret', base64_encode(random_bytes(32)), '', 'no');
        }

        // Capabilities
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('yv_shop_manage_products');
            $admin->add_cap('yv_shop_manage_orders');
            $admin->add_cap('yv_shop_view_reports');
            $admin->add_cap('yv_shop_manage_settings');
        }

        // Create default pages (cart, checkout) if they don't exist
        self::createPages();

        // Register taxonomies and rewrite rules
        (new Frontend\Router())->registerRewriteRules();
        flush_rewrite_rules();

        do_action('yv_shop_activated');
    }

    private static function defaultOptions(): array
    {
        return [
            'yv_shop_general_currency'         => 'EUR',
            'yv_shop_general_currency_symbol'  => '€',
            'yv_shop_general_currency_position'=> 'right_space', // 12,34 €
            'yv_shop_general_thousand_sep'     => ' ',
            'yv_shop_general_decimal_sep'      => ',',
            'yv_shop_general_decimals'         => 2,
            'yv_shop_general_shop_slug'        => 'boutique',
            'yv_shop_general_product_slug'     => 'produit',
            'yv_shop_general_country'          => 'FR',
            'yv_shop_products_per_page'        => 24,
            'yv_shop_products_default_sort'    => 'menu_order',
            'yv_shop_products_show_stock'      => 'yes',
            'yv_shop_cart_page_id'             => 0,
            'yv_shop_checkout_page_id'         => 0,
            'yv_shop_account_page_id'          => 0,
            'yv_shop_cart_redirect_after_add'  => 'no',
            'yv_shop_cart_ttl'                 => 7 * DAY_IN_SECONDS,
            'yv_shop_tax_mode'                 => 'incl',
            'yv_shop_tax_default_rate'         => 20.0,
            'yv_shop_payment_methods_enabled'  => ['bank_transfer'],
            'yv_shop_shipping_methods_enabled' => ['flat_rate'],
            'yv_shop_email_from_name'          => get_option('blogname'),
            'yv_shop_email_from_address'       => get_option('admin_email'),
            'yv_shop_uninstall_purge'          => 'no',
            'yv_shop_db_version'               => YV_SHOP_VERSION,
        ];
    }

    private static function createPages(): void
    {
        $pages = [
            'yv_shop_cart_page_id' => [
                'title'   => __('Panier', 'yv-shop'),
                'slug'    => 'panier',
                'content' => '[yv_shop_cart]',
            ],
            'yv_shop_checkout_page_id' => [
                'title'   => __('Commande', 'yv-shop'),
                'slug'    => 'commande',
                'content' => '[yv_shop_checkout]',
            ],
        ];

        foreach ($pages as $option_key => $page) {
            if (get_option($option_key)) {
                continue;
            }
            $existing = get_page_by_path($page['slug']);
            if ($existing) {
                update_option($option_key, $existing->ID);
                continue;
            }
            $id = wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $page['content'],
            ]);
            if ($id && !is_wp_error($id)) {
                update_option($option_key, $id);
            }
        }
    }
}
