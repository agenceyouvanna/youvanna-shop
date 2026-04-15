<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Helpers\Currency;
use Youvanna\Shop\Repositories\OrderRepository;

defined('ABSPATH') || exit;

final class OrderEditor
{
    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_orders')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $repo = new OrderRepository();
        $order = $id ? $repo->find($id) : null;

        if (!$order) {
            echo '<div class="wrap"><h1>' . esc_html__('Commande introuvable', 'yv-shop') . '</h1></div>';
            return;
        }

        if (!empty($_POST['yv_shop_order_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yv_shop_order_nonce'])), 'yv_shop_save_order_' . (int) $order->id)) {
            $new_status = sanitize_key($_POST['status'] ?? $order->status);
            $tracking = sanitize_text_field(wp_unslash($_POST['shipping_tracking'] ?? ''));
            if ($new_status !== $order->status) {
                $repo->updateStatus((int) $order->id, $new_status);
            }
            if ($tracking !== (string) $order->shipping_tracking) {
                global $wpdb;
                $wpdb->update($wpdb->prefix . 'yv_orders', ['shipping_tracking' => $tracking, 'updated_at' => current_time('mysql')], ['id' => $order->id]);
            }
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-orders&action=edit&id=' . (int) $order->id . '&saved=1'));
            exit;
        }

        echo '<div class="wrap"><h1>' . esc_html(sprintf(__('Commande %s', 'yv-shop'), $order->order_number)) . '</h1>';
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Commande enregistrée.', 'yv-shop') . '</p></div>';
        }

        echo '<div style="display:grid;grid-template-columns:2fr 1fr;gap:2rem;margin-top:1rem">';

        echo '<div>';
        echo '<h2>' . esc_html__('Articles', 'yv-shop') . '</h2>';
        echo '<table class="wp-list-table widefat"><thead><tr>';
        echo '<th>' . esc_html__('Produit', 'yv-shop') . '</th><th>' . esc_html__('SKU', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Qté', 'yv-shop') . '</th><th>' . esc_html__('Prix', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('TVA', 'yv-shop') . '</th><th>' . esc_html__('Total', 'yv-shop') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($order->items as $it) {
            echo '<tr>';
            echo '<td>' . esc_html($it->name) . '</td>';
            echo '<td>' . esc_html($it->sku) . '</td>';
            echo '<td>' . (int) $it->qty . '</td>';
            echo '<td>' . esc_html(Currency::format($it->price)) . '</td>';
            echo '<td>' . esc_html(Currency::format($it->line_tax)) . '</td>';
            echo '<td>' . esc_html(Currency::format($it->line_total)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<table class="widefat" style="margin-top:1rem"><tbody>';
        echo '<tr><td>' . esc_html__('Sous-total', 'yv-shop') . '</td><td style="text-align:right">' . esc_html(Currency::format($order->subtotal)) . '</td></tr>';
        if ($order->discount_total > 0) {
            echo '<tr><td>' . esc_html__('Remise', 'yv-shop') . '</td><td style="text-align:right">-' . esc_html(Currency::format($order->discount_total)) . '</td></tr>';
        }
        echo '<tr><td>' . esc_html__('Livraison', 'yv-shop') . '</td><td style="text-align:right">' . esc_html(Currency::format($order->shipping_total)) . '</td></tr>';
        echo '<tr><td>' . esc_html__('TVA', 'yv-shop') . '</td><td style="text-align:right">' . esc_html(Currency::format($order->tax_total)) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Total', 'yv-shop') . '</strong></td><td style="text-align:right"><strong>' . esc_html(Currency::format($order->total)) . '</strong></td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        echo '<div>';
        echo '<form method="post">';
        wp_nonce_field('yv_shop_save_order_' . (int) $order->id, 'yv_shop_order_nonce');
        echo '<h2>' . esc_html__('Actions', 'yv-shop') . '</h2>';
        echo '<p><label>' . esc_html__('Statut', 'yv-shop') . '<br><select name="status">';
        foreach (['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'review'] as $s) {
            $sel = $order->status === $s ? ' selected' : '';
            echo '<option value="' . esc_attr($s) . '"' . $sel . '>' . esc_html($s) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>' . esc_html__('N° suivi', 'yv-shop') . '<br><input type="text" name="shipping_tracking" value="' . esc_attr((string) $order->shipping_tracking) . '" class="regular-text"></label></p>';
        submit_button(__('Mettre à jour', 'yv-shop'));
        echo '</form>';

        echo '<h3>' . esc_html__('Client', 'yv-shop') . '</h3>';
        echo '<p><strong>' . esc_html($order->customer_email) . '</strong>';
        if ($order->customer_phone) {
            echo '<br>' . esc_html($order->customer_phone);
        }
        echo '</p>';

        echo '<h3>' . esc_html__('Facturation', 'yv-shop') . '</h3>';
        echo '<p>' . self::formatAddress($order->billing) . '</p>';

        if (!empty($order->shipping)) {
            echo '<h3>' . esc_html__('Livraison', 'yv-shop') . '</h3>';
            echo '<p>' . self::formatAddress($order->shipping) . '</p>';
        }

        echo '<h3>' . esc_html__('Paiement', 'yv-shop') . '</h3>';
        echo '<p>' . esc_html($order->payment_method) . ' - ' . esc_html($order->payment_status);
        if ($order->payment_reference) {
            echo '<br><small>' . esc_html($order->payment_reference) . '</small>';
        }
        echo '</p>';

        echo '</div>';
        echo '</div></div>';
    }

    private static function formatAddress(array $a): string
    {
        $lines = [];
        $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
        if ($name) {
            $lines[] = esc_html($name);
        }
        if (!empty($a['company'])) {
            $lines[] = esc_html($a['company']);
        }
        if (!empty($a['address_1'])) {
            $lines[] = esc_html($a['address_1']);
        }
        if (!empty($a['address_2'])) {
            $lines[] = esc_html($a['address_2']);
        }
        $loc = trim(($a['postcode'] ?? '') . ' ' . ($a['city'] ?? ''));
        if ($loc) {
            $lines[] = esc_html($loc);
        }
        if (!empty($a['country'])) {
            $lines[] = esc_html($a['country']);
        }
        return implode('<br>', $lines);
    }
}
