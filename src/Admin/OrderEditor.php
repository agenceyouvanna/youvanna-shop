<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Helpers\Currency;
use Youvanna\Shop\Repositories\OrderRepository;

defined('ABSPATH') || exit;

final class OrderEditor
{
    private const STATUSES = [
        'pending' => 'En attente de paiement',
        'processing' => 'En cours de traitement',
        'on-hold' => 'En pause',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
        'refunded' => 'Remboursée',
        'failed' => 'Échouée',
        'review' => 'À vérifier (fraude ?)',
    ];

    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_orders')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $repo = new OrderRepository();
        $order = $id ? $repo->find($id) : null;

        if (!$order) {
            echo '<div class="wrap yv-admin"><div class="yv-admin-notice yv-admin-notice--error">' . esc_html__('Commande introuvable.', 'yv-shop') . '</div></div>';
            return;
        }

        if (!empty($_POST['yv_shop_order_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yv_shop_order_nonce'])), 'yv_shop_save_order_' . (int) $order->id)) {
            $new_status = sanitize_key($_POST['status'] ?? $order->status);
            $tracking = sanitize_text_field(wp_unslash($_POST['shipping_tracking'] ?? ''));
            $admin_note = sanitize_textarea_field(wp_unslash($_POST['admin_note'] ?? ''));
            if ($new_status !== $order->status) {
                $repo->updateStatus((int) $order->id, $new_status);
            }
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'yv_orders', [
                'shipping_tracking' => $tracking,
                'updated_at' => current_time('mysql'),
            ], ['id' => $order->id]);

            if ($admin_note !== '') {
                $notes_table = $wpdb->prefix . 'yv_order_notes';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$notes_table}'")) {
                    $wpdb->insert($notes_table, [
                        'order_id' => $order->id,
                        'author_id' => get_current_user_id(),
                        'note' => $admin_note,
                        'visibility' => 'private',
                        'created_at' => current_time('mysql'),
                    ]);
                }
            }
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-orders&action=edit&id=' . (int) $order->id . '&saved=1'));
            exit;
        }

        $name = trim(($order->billing['first_name'] ?? '') . ' ' . ($order->billing['last_name'] ?? ''));

        echo '<div class="wrap yv-admin">';
        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title">';
        echo '<h1>' . esc_html__('Commande', 'yv-shop') . ' <span style="color:var(--yv-admin-text-muted);font-weight:500">' . esc_html($order->order_number) . '</span></h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html(sprintf(__('Passée le %s par %s', 'yv-shop'), mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $order->created_at), $name ?: $order->customer_email)) . '</p></div>';
        echo '<div class="yv-admin-header__actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=yv-shop-orders')) . '" class="yv-btn yv-btn--ghost">← ' . esc_html__('Retour', 'yv-shop') . '</a>';
        echo '</div></div>';

        if (isset($_GET['saved'])) echo '<div class="yv-admin-notice">' . esc_html__('Commande mise à jour.', 'yv-shop') . '</div>';

        echo '<form method="post">';
        wp_nonce_field('yv_shop_save_order_' . (int) $order->id, 'yv_shop_order_nonce');

        echo '<div class="yv-grid yv-grid--main-side">';

        // Main column
        echo '<div>';

        // Items
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Articles commandés', 'yv-shop') . '</h2></div><div class="yv-card__body yv-card__body--flush">';
        echo '<table class="yv-table"><thead><tr>';
        echo '<th>' . esc_html__('Produit', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('SKU', 'yv-shop') . '</th>';
        echo '<th style="text-align:center">' . esc_html__('Qté', 'yv-shop') . '</th>';
        echo '<th style="text-align:right">' . esc_html__('Prix', 'yv-shop') . '</th>';
        echo '<th style="text-align:right">' . esc_html__('Total', 'yv-shop') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($order->items as $it) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($it->name) . '</strong></td>';
            echo '<td><small style="color:var(--yv-admin-text-muted);font-family:ui-monospace,monospace">' . esc_html($it->sku) . '</small></td>';
            echo '<td style="text-align:center"><span class="yv-pill yv-pill--draft">×' . (int) $it->qty . '</span></td>';
            echo '<td style="text-align:right">' . esc_html(Currency::format($it->price)) . '</td>';
            echo '<td style="text-align:right"><strong>' . esc_html(Currency::format($it->line_total)) . '</strong></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div></div>';

        // Totals
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Totaux', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-totals">';
        self::total(__('Sous-total', 'yv-shop'), Currency::format($order->subtotal));
        if ($order->discount_total > 0) self::total(__('Remise', 'yv-shop'), '-' . Currency::format($order->discount_total), 'var(--yv-admin-danger)');
        self::total(__('Livraison', 'yv-shop'), Currency::format($order->shipping_total));
        self::total(__('TVA', 'yv-shop'), Currency::format($order->tax_total));
        self::total('<strong>' . __('Total', 'yv-shop') . '</strong>', '<strong style="font-size:18px">' . Currency::format($order->total) . '</strong>', null, true);
        echo '</div>';
        echo '</div></div>';

        // Customer + addresses
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Client et adresses', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-row-3col">';
        echo '<div><strong>' . esc_html__('Contact', 'yv-shop') . '</strong><br>';
        echo '<a href="mailto:' . esc_attr($order->customer_email) . '">' . esc_html($order->customer_email) . '</a>';
        if ($order->customer_phone) echo '<br>' . esc_html($order->customer_phone);
        echo '</div>';
        echo '<div><strong>' . esc_html__('Facturation', 'yv-shop') . '</strong><br>' . self::formatAddress($order->billing) . '</div>';
        if (!empty($order->shipping)) {
            echo '<div><strong>' . esc_html__('Livraison', 'yv-shop') . '</strong><br>' . self::formatAddress($order->shipping) . '</div>';
        }
        echo '</div>';
        echo '</div></div>';

        // Admin note
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Ajouter une note interne', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-field">';
        echo '<textarea name="admin_note" rows="3" class="yv-textarea" placeholder="' . esc_attr__('Ex: Client VIP, livrer en priorité...', 'yv-shop') . '"></textarea>';
        echo '<small class="yv-field__hint">' . esc_html__('Note privée, visible uniquement dans l\'admin. Pas envoyée au client.', 'yv-shop') . '</small>';
        echo '</div>';
        echo '</div></div>';

        echo '</div>';

        // Sidebar
        echo '<aside class="yv-sidebar">';

        // Status + actions
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Statut', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-field">';
        echo '<label class="yv-field__label" for="yv_order_status">' . esc_html__('Changer le statut', 'yv-shop') . '</label>';
        echo '<select id="yv_order_status" name="status" class="yv-select">';
        foreach (self::STATUSES as $k => $lbl) {
            $sel = $order->status === $k ? ' selected' : '';
            echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($lbl) . '</option>';
        }
        echo '</select>';
        echo '<small class="yv-field__hint">' . esc_html__('"Terminée" déclenche l\'email de confirmation. "Remboursée" rend le stock au produit.', 'yv-shop') . '</small>';
        echo '</div>';
        echo '<div class="yv-field">';
        echo '<label class="yv-field__label" for="yv_tracking">' . esc_html__('N° de suivi colis', 'yv-shop') . '</label>';
        echo '<input type="text" id="yv_tracking" name="shipping_tracking" value="' . esc_attr((string) $order->shipping_tracking) . '" class="yv-input" placeholder="' . esc_attr__('Ex: 6A12345678901', 'yv-shop') . '">';
        echo '<small class="yv-field__hint">' . esc_html__('Affiché au client dans l\'email de livraison.', 'yv-shop') . '</small>';
        echo '</div>';
        echo '<button type="submit" class="yv-btn yv-btn--primary" style="width:100%;justify-content:center">' . esc_html__('Enregistrer', 'yv-shop') . '</button>';
        echo '</div></div>';

        // Payment
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Paiement', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-field"><div class="yv-field__label">' . esc_html__('Méthode', 'yv-shop') . '</div><div>' . esc_html(self::paymentMethodLabel($order->payment_method)) . '</div></div>';
        echo '<div class="yv-field"><div class="yv-field__label">' . esc_html__('Statut', 'yv-shop') . '</div><div><span class="yv-pill yv-pill--' . esc_attr($order->payment_status) . '">' . esc_html($order->payment_status) . '</span></div></div>';
        if ($order->payment_reference) {
            echo '<div class="yv-field"><div class="yv-field__label">' . esc_html__('Référence', 'yv-shop') . '</div><div><small style="font-family:ui-monospace,monospace;word-break:break-all">' . esc_html($order->payment_reference) . '</small></div></div>';
        }
        echo '</div></div>';

        echo '</aside>';
        echo '</div>';
        echo '</form></div>';
    }

    private static function total(string $label, string $value, ?string $color = null, bool $border = false): void
    {
        $style = 'display:flex;justify-content:space-between;padding:8px 0';
        if ($border) $style .= ';border-top:2px solid var(--yv-admin-border);margin-top:6px;padding-top:14px';
        if ($color) $style .= ';color:' . $color;
        echo '<div style="' . esc_attr($style) . '"><span>' . wp_kses_post($label) . '</span><span>' . wp_kses_post($value) . '</span></div>';
    }

    private static function paymentMethodLabel(string $m): string
    {
        return [
            'bank_transfer' => __('Virement bancaire', 'yv-shop'),
            'stripe' => __('Carte bancaire (Stripe)', 'yv-shop'),
        ][$m] ?? $m;
    }

    private static function formatAddress(array $a): string
    {
        $lines = [];
        $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
        if ($name) $lines[] = esc_html($name);
        if (!empty($a['company'])) $lines[] = esc_html($a['company']);
        if (!empty($a['address_1'])) $lines[] = esc_html($a['address_1']);
        if (!empty($a['address_2'])) $lines[] = esc_html($a['address_2']);
        $loc = trim(($a['postcode'] ?? '') . ' ' . ($a['city'] ?? ''));
        if ($loc) $lines[] = esc_html($loc);
        if (!empty($a['country'])) $lines[] = esc_html($a['country']);
        return implode('<br>', $lines);
    }
}
