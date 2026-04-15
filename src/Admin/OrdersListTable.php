<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Helpers\Currency;
use Youvanna\Shop\Repositories\OrderRepository;

defined('ABSPATH') || exit;

final class OrdersListTable
{
    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_orders')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        if ($action === 'edit') {
            OrderEditor::render();
            return;
        }

        $repo = new OrderRepository();
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 25;
        $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $orders = $repo->listForAdmin($per_page, ($paged - 1) * $per_page, $status_filter);

        echo '<div class="wrap"><h1>' . esc_html__('Commandes', 'yv-shop') . '</h1>';

        echo '<ul class="subsubsub">';
        $statuses = [
            '' => __('Toutes', 'yv-shop'),
            'pending' => __('En attente', 'yv-shop'),
            'processing' => __('En cours', 'yv-shop'),
            'on-hold' => __('En pause', 'yv-shop'),
            'completed' => __('Terminées', 'yv-shop'),
            'cancelled' => __('Annulées', 'yv-shop'),
            'refunded' => __('Remboursées', 'yv-shop'),
            'failed' => __('Échouées', 'yv-shop'),
            'review' => __('À vérifier', 'yv-shop'),
        ];
        $i = 0; $count = count($statuses);
        foreach ($statuses as $key => $label) {
            $url = admin_url('admin.php?page=yv-shop-orders' . ($key ? '&status=' . $key : ''));
            $cls = $status_filter === $key ? ' class="current"' : '';
            echo '<li><a href="' . esc_url($url) . '"' . $cls . '>' . esc_html($label) . '</a>' . (++$i < $count ? ' | ' : '') . '</li>';
        }
        echo '</ul>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('N°', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Date', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Client', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Statut', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Paiement', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Total', 'yv-shop') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($orders)) {
            echo '<tr><td colspan="6">' . esc_html__('Aucune commande.', 'yv-shop') . '</td></tr>';
        }

        foreach ($orders as $o) {
            $edit_url = admin_url('admin.php?page=yv-shop-orders&action=edit&id=' . (int) $o->id);
            $name = trim(($o->billing['first_name'] ?? '') . ' ' . ($o->billing['last_name'] ?? ''));
            echo '<tr>';
            echo '<td><strong><a href="' . esc_url($edit_url) . '">' . esc_html($o->order_number) . '</a></strong></td>';
            echo '<td>' . esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $o->created_at)) . '</td>';
            echo '<td>' . esc_html($name ?: $o->customer_email) . '<br><small>' . esc_html($o->customer_email) . '</small></td>';
            echo '<td><span class="yv-status yv-status-' . esc_attr($o->status) . '">' . esc_html($o->status) . '</span></td>';
            echo '<td>' . esc_html($o->payment_method) . ' / ' . esc_html($o->payment_status) . '</td>';
            echo '<td>' . esc_html(Currency::format($o->total)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}
