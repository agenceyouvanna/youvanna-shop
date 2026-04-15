<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Helpers\Currency;
use Youvanna\Shop\Repositories\CouponRepository;

defined('ABSPATH') || exit;

final class CouponsListTable
{
    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_settings')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        if ($action === 'edit' || $action === 'new') {
            CouponEditor::render();
            return;
        }
        if ($action === 'delete' && !empty($_GET['id']) && check_admin_referer('yv_shop_delete_coupon_' . (int) $_GET['id'])) {
            (new CouponRepository())->delete((int) $_GET['id']);
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-coupons&deleted=1'));
            exit;
        }

        $repo = new CouponRepository();
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 20;
        $items = $repo->all($per_page, ($paged - 1) * $per_page);
        $total = $repo->countAll();

        echo '<div class="wrap yv-admin">';
        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title"><h1>' . esc_html__('Coupons de réduction', 'yv-shop') . '</h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html__('Crée des codes promo en pourcentage, montant fixe ou livraison gratuite.', 'yv-shop') . '</p></div>';
        echo '<div class="yv-admin-header__actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=yv-shop-coupons&action=new')) . '" class="yv-btn yv-btn--primary"><span class="dashicons dashicons-plus-alt2"></span>' . esc_html__('Nouveau coupon', 'yv-shop') . '</a>';
        echo '</div></div>';

        if (isset($_GET['saved'])) echo '<div class="yv-admin-notice">' . esc_html__('Coupon enregistré.', 'yv-shop') . '</div>';
        if (isset($_GET['deleted'])) echo '<div class="yv-admin-notice">' . esc_html__('Coupon supprimé.', 'yv-shop') . '</div>';

        echo '<table class="yv-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Code', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Type', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Réduction', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Utilisations', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Expiration', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Statut', 'yv-shop') . '</th>';
        echo '</tr></thead><tbody>';

        if (!$items) {
            echo '<tr><td colspan="6" class="yv-table__empty">' . esc_html__('Aucun coupon. Crée le premier pour commencer.', 'yv-shop') . '</td></tr>';
        }

        foreach ($items as $c) {
            $edit_url = admin_url('admin.php?page=yv-shop-coupons&action=edit&id=' . (int) $c->id);
            $del_url = wp_nonce_url(admin_url('admin.php?page=yv-shop-coupons&action=delete&id=' . (int) $c->id), 'yv_shop_delete_coupon_' . (int) $c->id);
            $reduction = '';
            switch ($c->type) {
                case 'percent': $reduction = number_format($c->amount, 0) . '%'; break;
                case 'free_shipping': $reduction = esc_html__('Livraison offerte', 'yv-shop'); break;
                default: $reduction = Currency::format($c->amount); break;
            }
            $active = $c->isActive();
            echo '<tr>';
            echo '<td><strong><a href="' . esc_url($edit_url) . '" style="text-decoration:none;color:var(--yv-admin-text);font-family:ui-monospace,monospace">' . esc_html($c->code) . '</a></strong>';
            echo '<div class="yv-row-actions"><a href="' . esc_url($edit_url) . '">' . esc_html__('Modifier', 'yv-shop') . '</a> <a href="' . esc_url($del_url) . '" class="danger" data-confirm="' . esc_attr__('Supprimer ce coupon ?', 'yv-shop') . '">' . esc_html__('Supprimer', 'yv-shop') . '</a></div></td>';
            echo '<td>' . esc_html(self::typeLabel($c->type)) . '</td>';
            echo '<td><strong>' . $reduction . '</strong></td>';
            echo '<td>' . (int) $c->used_count . ($c->usage_limit ? ' / ' . (int) $c->usage_limit : '') . '</td>';
            echo '<td>' . ($c->expires_at ? esc_html(mysql2date(get_option('date_format'), (string) $c->expires_at)) : '<small style="color:var(--yv-admin-text-muted)">' . esc_html__('Jamais', 'yv-shop') . '</small>') . '</td>';
            echo '<td><span class="yv-pill yv-pill--' . ($active ? 'published' : 'draft') . '">' . esc_html($active ? __('Actif', 'yv-shop') : __('Inactif', 'yv-shop')) . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        $total_pages = (int) ceil($total / $per_page);
        if ($total_pages > 1) {
            echo '<div style="margin-top:20px;text-align:center">';
            echo paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $paged, 'total' => $total_pages]);
            echo '</div>';
        }
        echo '</div>';
    }

    private static function typeLabel(string $t): string
    {
        return [
            'percent' => __('Pourcentage', 'yv-shop'),
            'fixed_cart' => __('Montant fixe (panier)', 'yv-shop'),
            'fixed_product' => __('Montant fixe (par produit)', 'yv-shop'),
            'free_shipping' => __('Livraison gratuite', 'yv-shop'),
        ][$t] ?? $t;
    }
}
