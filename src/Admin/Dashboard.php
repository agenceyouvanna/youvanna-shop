<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Helpers\Currency;
use Youvanna\Shop\Repositories\OrderRepository;

defined('ABSPATH') || exit;

final class Dashboard
{
    public static function render(): void
    {
        global $wpdb;

        $products = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_products");
        $published = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_products WHERE status='published'");
        $drafts = $products - $published;
        $low_stock = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_products WHERE manage_stock=1 AND stock_qty > 0 AND stock_qty <= 5");
        $out_of_stock = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_products WHERE manage_stock=1 AND stock_qty <= 0");

        $orders_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_orders");
        $orders_pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_orders WHERE status IN ('pending','processing','on-hold')");
        $orders_30d = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $orders_7d = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yv_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

        $revenue_30d = (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$wpdb->prefix}yv_orders WHERE payment_status='paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $revenue_7d = (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$wpdb->prefix}yv_orders WHERE payment_status='paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $avg_order = $orders_30d > 0 ? $revenue_30d / $orders_30d : 0.0;

        $reviews_pending = 0;
        $reviews_table = $wpdb->prefix . 'yv_product_reviews';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$reviews_table}'")) {
            $reviews_pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$reviews_table} WHERE status='pending'");
        }

        // Sales chart (last 30 days)
        $sales_rows = $wpdb->get_results(
            "SELECT DATE(created_at) AS d, COUNT(*) AS n, COALESCE(SUM(total),0) AS revenue
             FROM {$wpdb->prefix}yv_orders
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND payment_status='paid'
             GROUP BY DATE(created_at) ORDER BY d ASC",
            ARRAY_A
        ) ?: [];
        $chart = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $chart[$d] = ['n' => 0, 'revenue' => 0.0];
        }
        foreach ($sales_rows as $r) {
            if (isset($chart[$r['d']])) {
                $chart[$r['d']] = ['n' => (int) $r['n'], 'revenue' => (float) $r['revenue']];
            }
        }

        // Top products (30d)
        $top_products = $wpdb->get_results(
            "SELECT p.id, p.name, p.sku, SUM(oi.qty) AS qty, SUM(oi.line_total) AS total
             FROM {$wpdb->prefix}yv_order_items oi
             INNER JOIN {$wpdb->prefix}yv_orders o ON o.id = oi.order_id
             INNER JOIN {$wpdb->prefix}yv_products p ON p.id = oi.product_id
             WHERE o.payment_status='paid' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY p.id ORDER BY qty DESC LIMIT 5",
            ARRAY_A
        ) ?: [];

        // Recent orders
        $recent = (new OrderRepository())->listForAdmin(8, 0, '');

        echo '<div class="wrap yv-admin">';
        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title"><h1>' . esc_html__('Tableau de bord', 'yv-shop') . '</h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html__('Vue d\'ensemble de ta boutique : ventes, stock, avis, actions à traiter.', 'yv-shop') . '</p></div>';
        echo '<div class="yv-admin-header__actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=yv-shop-products&action=new')) . '" class="yv-btn yv-btn--primary"><span class="dashicons dashicons-plus-alt2"></span>' . esc_html__('Nouveau produit', 'yv-shop') . '</a>';
        echo '</div></div>';

        // KPI Grid
        echo '<div class="yv-kpi-grid">';
        self::kpi(__('Chiffre d\'affaires 30j', 'yv-shop'), Currency::format($revenue_30d), __('Ventes payées des 30 derniers jours', 'yv-shop'), 'chart-line');
        self::kpi(__('Commandes 30j', 'yv-shop'), (string) $orders_30d, sprintf(__('Dont %d cette semaine', 'yv-shop'), $orders_7d), 'cart');
        self::kpi(__('Panier moyen', 'yv-shop'), Currency::format($avg_order), __('Sur les 30 derniers jours', 'yv-shop'), 'money-alt');
        self::kpi(__('À traiter', 'yv-shop'), (string) $orders_pending, __('Commandes en attente ou en cours', 'yv-shop'), 'warning', $orders_pending > 0 ? 'warn' : '');
        echo '</div>';

        // Second row: inventory + reviews alerts
        echo '<div class="yv-kpi-grid" style="margin-top:16px">';
        self::kpi(__('Produits publiés', 'yv-shop'), (string) $published, sprintf(__('%d brouillons', 'yv-shop'), $drafts), 'products');
        self::kpi(__('Stock faible', 'yv-shop'), (string) $low_stock, __('Produits avec 5 unités ou moins', 'yv-shop'), 'archive', $low_stock > 0 ? 'warn' : '');
        self::kpi(__('Ruptures', 'yv-shop'), (string) $out_of_stock, __('Produits en stock épuisé', 'yv-shop'), 'dismiss', $out_of_stock > 0 ? 'danger' : '');
        self::kpi(__('Avis en attente', 'yv-shop'), (string) $reviews_pending, __('À modérer', 'yv-shop'), 'star-filled', $reviews_pending > 0 ? 'warn' : '');
        echo '</div>';

        echo '<div class="yv-grid yv-grid--main-side" style="margin-top:20px">';

        echo '<div>';
        // Sales chart
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Ventes des 30 derniers jours', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        self::renderChart($chart);
        echo '</div></div>';

        // Recent orders
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Commandes récentes', 'yv-shop') . '</h2><a href="' . esc_url(admin_url('admin.php?page=yv-shop-orders')) . '" class="yv-card__link">' . esc_html__('Voir tout', 'yv-shop') . ' →</a></div><div class="yv-card__body yv-card__body--flush">';
        if (!$recent) {
            echo '<p style="padding:24px;text-align:center;color:var(--yv-admin-text-muted)">' . esc_html__('Aucune commande pour l\'instant.', 'yv-shop') . '</p>';
        } else {
            echo '<table class="yv-table"><thead><tr>';
            echo '<th>' . esc_html__('N°', 'yv-shop') . '</th><th>' . esc_html__('Client', 'yv-shop') . '</th>';
            echo '<th>' . esc_html__('Statut', 'yv-shop') . '</th><th>' . esc_html__('Total', 'yv-shop') . '</th><th>' . esc_html__('Date', 'yv-shop') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($recent as $o) {
                $url = admin_url('admin.php?page=yv-shop-orders&action=edit&id=' . (int) $o->id);
                $name = trim(($o->billing['first_name'] ?? '') . ' ' . ($o->billing['last_name'] ?? ''));
                echo '<tr>';
                echo '<td><strong><a href="' . esc_url($url) . '" style="color:var(--yv-admin-text)">' . esc_html($o->order_number) . '</a></strong></td>';
                echo '<td>' . esc_html($name ?: $o->customer_email) . '<br><small style="color:var(--yv-admin-text-muted)">' . esc_html($o->customer_email) . '</small></td>';
                echo '<td><span class="yv-pill yv-pill--' . esc_attr($o->status) . '">' . esc_html($o->status) . '</span></td>';
                echo '<td><strong>' . esc_html(Currency::format($o->total)) . '</strong></td>';
                echo '<td><small style="color:var(--yv-admin-text-muted)">' . esc_html(human_time_diff(strtotime((string) $o->created_at)) . ' ago') . '</small></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div></div>';
        echo '</div>';

        echo '<aside class="yv-sidebar">';

        // Top products
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Top produits 30j', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        if (!$top_products) {
            echo '<p style="color:var(--yv-admin-text-muted);margin:0">' . esc_html__('Pas encore de ventes.', 'yv-shop') . '</p>';
        } else {
            echo '<ol style="list-style:none;counter-reset:item;padding:0;margin:0">';
            foreach ($top_products as $r) {
                $url = admin_url('admin.php?page=yv-shop-products&action=edit&id=' . (int) $r['id']);
                echo '<li style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--yv-admin-border)">';
                echo '<div style="flex:1;min-width:0"><a href="' . esc_url($url) . '" style="color:var(--yv-admin-text);font-weight:500;text-decoration:none">' . esc_html($r['name']) . '</a>';
                echo '<div style="font-size:12px;color:var(--yv-admin-text-muted)">' . (int) $r['qty'] . ' ' . esc_html__('vendus', 'yv-shop') . '</div></div>';
                echo '<div style="font-weight:700">' . esc_html(Currency::format((float) $r['total'])) . '</div>';
                echo '</li>';
            }
            echo '</ol>';
        }
        echo '</div></div>';

        // Quick actions
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Actions rapides', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div style="display:flex;flex-direction:column;gap:8px">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=yv-shop-products&action=new')) . '" class="yv-btn yv-btn--ghost" style="justify-content:flex-start">📦 ' . esc_html__('Nouveau produit', 'yv-shop') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=yv-shop-coupons&action=new')) . '" class="yv-btn yv-btn--ghost" style="justify-content:flex-start">🎟 ' . esc_html__('Nouveau coupon', 'yv-shop') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=yv-shop-import')) . '" class="yv-btn yv-btn--ghost" style="justify-content:flex-start">📤 ' . esc_html__('Import / Export CSV', 'yv-shop') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=yv-shop-settings')) . '" class="yv-btn yv-btn--ghost" style="justify-content:flex-start">⚙ ' . esc_html__('Réglages', 'yv-shop') . '</a>';
        echo '</div>';
        echo '</div></div>';

        echo '</aside>';
        echo '</div>';
        echo '</div>';
    }

    private static function kpi(string $label, string $value, string $hint, string $icon, string $tone = ''): void
    {
        $toneCls = $tone ? ' yv-kpi--' . $tone : '';
        echo '<div class="yv-kpi' . $toneCls . '">';
        echo '<div class="yv-kpi__icon"><span class="dashicons dashicons-' . esc_attr($icon) . '"></span></div>';
        echo '<div class="yv-kpi__body">';
        echo '<div class="yv-kpi__label">' . esc_html($label) . '</div>';
        echo '<div class="yv-kpi__value">' . esc_html($value) . '</div>';
        echo '<div class="yv-kpi__hint">' . esc_html($hint) . '</div>';
        echo '</div></div>';
    }

    private static function renderChart(array $chart): void
    {
        $max = 0.0;
        foreach ($chart as $d) if ($d['revenue'] > $max) $max = $d['revenue'];
        if ($max <= 0) $max = 1;

        echo '<div class="yv-chart">';
        foreach ($chart as $date => $d) {
            $h = max(2, (int) round(($d['revenue'] / $max) * 100));
            $tooltip = sprintf('%s : %s (%d cmd)', mysql2date(get_option('date_format'), $date), Currency::format($d['revenue']), (int) $d['n']);
            echo '<div class="yv-chart__bar" style="height:' . $h . '%" title="' . esc_attr($tooltip) . '"></div>';
        }
        echo '</div>';
        echo '<div class="yv-chart__legend">';
        $first = array_key_first($chart);
        $last = array_key_last($chart);
        echo '<span>' . esc_html(mysql2date('d M', $first)) . '</span>';
        echo '<span>' . esc_html(mysql2date('d M', $last)) . '</span>';
        echo '</div>';
    }
}
