<?php
namespace Youvanna\Shop\Admin;

defined('ABSPATH') || exit;

final class Dashboard
{
    public static function render(): void
    {
        global $wpdb;
        $products = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_products");
        $published = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_products WHERE status='published'");
        $orders = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_orders");
        $orders_30d = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $revenue_30d = (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$wpdb->prefix}yv_orders WHERE payment_status='paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Youvanna Shop', 'yv-shop') . '</h1>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-top:2rem">';
        self::card(__('Produits totaux', 'yv-shop'), (string) $products);
        self::card(__('Produits publiés', 'yv-shop'), (string) $published);
        self::card(__('Commandes totales', 'yv-shop'), (string) $orders);
        self::card(__('Commandes (30j)', 'yv-shop'), (string) $orders_30d);
        self::card(__('Chiffre d\'affaires (30j)', 'yv-shop'), \Youvanna\Shop\Helpers\Currency::format($revenue_30d));
        echo '</div>';
        echo '</div>';
    }

    private static function card(string $title, string $value): void
    {
        echo '<div style="background:#fff;padding:1.5rem;border:1px solid #ddd;border-radius:4px">';
        echo '<div style="color:#666;font-size:.85rem">' . esc_html($title) . '</div>';
        echo '<div style="font-size:1.8rem;font-weight:600;margin-top:.5rem">' . esc_html($value) . '</div>';
        echo '</div>';
    }
}
