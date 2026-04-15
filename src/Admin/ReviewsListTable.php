<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Repositories\ProductRepository;
use Youvanna\Shop\Repositories\ReviewRepository;

defined('ABSPATH') || exit;

final class ReviewsListTable
{
    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_orders')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        $repo = new ReviewRepository();
        if ($action === 'update' && !empty($_GET['id']) && !empty($_GET['status']) && check_admin_referer('yv_shop_review_' . (int) $_GET['id'])) {
            $repo->updateStatus((int) $_GET['id'], sanitize_key($_GET['status']));
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-reviews&updated=1'));
            exit;
        }
        if ($action === 'delete' && !empty($_GET['id']) && check_admin_referer('yv_shop_review_delete_' . (int) $_GET['id'])) {
            $repo->delete((int) $_GET['id']);
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-reviews&deleted=1'));
            exit;
        }

        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 20;
        $items = $repo->all($status, $per_page, ($paged - 1) * $per_page);
        $total = $repo->countAll($status);

        $productRepo = new ProductRepository();

        echo '<div class="wrap yv-admin">';
        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title"><h1>' . esc_html__('Avis clients', 'yv-shop') . '</h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html__('Modère les avis avant qu\'ils apparaissent sur la boutique.', 'yv-shop') . '</p></div>';
        echo '</div>';

        if (isset($_GET['updated'])) echo '<div class="yv-admin-notice">' . esc_html__('Avis mis à jour.', 'yv-shop') . '</div>';
        if (isset($_GET['deleted'])) echo '<div class="yv-admin-notice">' . esc_html__('Avis supprimé.', 'yv-shop') . '</div>';

        // Filters
        echo '<div class="yv-filters"><div class="yv-filters__tabs">';
        $tabs = [
            '' => __('Tous', 'yv-shop'),
            'pending' => __('En attente', 'yv-shop'),
            'approved' => __('Approuvés', 'yv-shop'),
            'spam' => __('Spam', 'yv-shop'),
            'trash' => __('Corbeille', 'yv-shop'),
        ];
        foreach ($tabs as $k => $lbl) {
            $count = $repo->countAll($k);
            $url = admin_url('admin.php?page=yv-shop-reviews' . ($k ? '&status=' . $k : ''));
            $cls = $status === $k ? ' is-active' : '';
            echo '<a href="' . esc_url($url) . '" class="yv-filters__tab' . $cls . '">' . esc_html($lbl) . '<span class="yv-count">' . (int) $count . '</span></a>';
        }
        echo '</div></div>';

        echo '<table class="yv-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Auteur', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Note', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Avis', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Produit', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Statut', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Date', 'yv-shop') . '</th>';
        echo '</tr></thead><tbody>';

        if (!$items) {
            echo '<tr><td colspan="6" class="yv-table__empty">' . esc_html__('Aucun avis pour ce filtre.', 'yv-shop') . '</td></tr>';
        }

        foreach ($items as $r) {
            $product = $productRepo->find((int) $r->product_id);
            $stars = str_repeat('★', (int) $r->rating) . str_repeat('☆', 5 - (int) $r->rating);
            $approve_url = wp_nonce_url(admin_url('admin.php?page=yv-shop-reviews&action=update&id=' . (int) $r->id . '&status=approved'), 'yv_shop_review_' . (int) $r->id);
            $pending_url = wp_nonce_url(admin_url('admin.php?page=yv-shop-reviews&action=update&id=' . (int) $r->id . '&status=pending'), 'yv_shop_review_' . (int) $r->id);
            $spam_url = wp_nonce_url(admin_url('admin.php?page=yv-shop-reviews&action=update&id=' . (int) $r->id . '&status=spam'), 'yv_shop_review_' . (int) $r->id);
            $delete_url = wp_nonce_url(admin_url('admin.php?page=yv-shop-reviews&action=delete&id=' . (int) $r->id), 'yv_shop_review_delete_' . (int) $r->id);
            echo '<tr>';
            echo '<td><strong>' . esc_html($r->author_name) . '</strong><br><small style="color:var(--yv-admin-text-muted)">' . esc_html($r->author_email) . '</small></td>';
            echo '<td><span class="yv-stars">' . esc_html($stars) . '</span></td>';
            echo '<td>';
            if ($r->title) echo '<strong>' . esc_html($r->title) . '</strong><br>';
            echo '<span style="font-size:13px;color:var(--yv-admin-text)">' . esc_html(mb_strimwidth((string) $r->content, 0, 200, '...')) . '</span>';
            echo '<div class="yv-row-actions">';
            if ($r->status !== 'approved') echo '<a href="' . esc_url($approve_url) . '">' . esc_html__('Approuver', 'yv-shop') . '</a>';
            if ($r->status !== 'pending') echo '<a href="' . esc_url($pending_url) . '">' . esc_html__('En attente', 'yv-shop') . '</a>';
            if ($r->status !== 'spam') echo '<a href="' . esc_url($spam_url) . '">' . esc_html__('Spam', 'yv-shop') . '</a>';
            echo '<a href="' . esc_url($delete_url) . '" class="danger" data-confirm="' . esc_attr__('Supprimer cet avis ?', 'yv-shop') . '">' . esc_html__('Supprimer', 'yv-shop') . '</a>';
            echo '</div>';
            echo '</td>';
            if ($product) {
                echo '<td><a href="' . esc_url(admin_url('admin.php?page=yv-shop-products&action=edit&id=' . (int) $product->id)) . '">' . esc_html($product->name) . '</a></td>';
            } else {
                echo '<td><em style="color:var(--yv-admin-text-muted)">' . esc_html__('Supprimé', 'yv-shop') . '</em></td>';
            }
            echo '<td><span class="yv-pill yv-pill--' . esc_attr($r->status) . '">' . esc_html(self::statusLabel($r->status)) . '</span></td>';
            echo '<td><small>' . esc_html(mysql2date(get_option('date_format'), (string) $r->created_at)) . '</small></td>';
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

    private static function statusLabel(string $s): string
    {
        return [
            'pending' => __('En attente', 'yv-shop'),
            'approved' => __('Approuvé', 'yv-shop'),
            'spam' => __('Spam', 'yv-shop'),
            'trash' => __('Corbeille', 'yv-shop'),
        ][$s] ?? $s;
    }
}
