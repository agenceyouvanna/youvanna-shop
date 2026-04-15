<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Models\SearchCriteria;
use Youvanna\Shop\Repositories\ProductRepository;
use Youvanna\Shop\Helpers\Currency;

defined('ABSPATH') || exit;

final class ProductsListTable
{
    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_products')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        if ($action === 'edit' || $action === 'new') {
            ProductEditor::render();
            return;
        }

        if ($action === 'delete' && !empty($_GET['id']) && check_admin_referer('yv_shop_delete_product_' . (int) $_GET['id'])) {
            (new ProductRepository())->delete((int) $_GET['id']);
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-products&deleted=1'));
            exit;
        }

        $repo = new ProductRepository();
        $criteria = new SearchCriteria();
        $criteria->page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $criteria->per_page = 20;
        $criteria->statuses = ['published', 'draft', 'private'];
        $criteria->orderby = 'created_at';
        $criteria->order = 'desc';
        if (!empty($_GET['s'])) {
            $criteria->query = sanitize_text_field(wp_unslash($_GET['s']));
        }

        $result = $repo->search($criteria);
        $new_url = admin_url('admin.php?page=yv-shop-products&action=new');

        echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__('Produits', 'yv-shop') . '</h1>';
        echo ' <a href="' . esc_url($new_url) . '" class="page-title-action">' . esc_html__('Ajouter', 'yv-shop') . '</a>';
        echo '<hr class="wp-header-end">';

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Produit enregistré.', 'yv-shop') . '</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Produit supprimé.', 'yv-shop') . '</p></div>';
        }

        echo '<form method="get"><input type="hidden" name="page" value="yv-shop-products">';
        echo '<p class="search-box"><input type="search" name="s" value="' . esc_attr($criteria->query) . '" placeholder="' . esc_attr__('Rechercher...', 'yv-shop') . '">';
        submit_button(__('Rechercher', 'yv-shop'), '', '', false);
        echo '</p></form>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Nom', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('SKU', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Prix', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Stock', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Statut', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Date', 'yv-shop') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($result['items'])) {
            echo '<tr><td colspan="6">' . esc_html__('Aucun produit.', 'yv-shop') . '</td></tr>';
        }

        foreach ($result['items'] as $p) {
            $edit_url = admin_url('admin.php?page=yv-shop-products&action=edit&id=' . (int) $p->id);
            $del_url = wp_nonce_url(admin_url('admin.php?page=yv-shop-products&action=delete&id=' . (int) $p->id), 'yv_shop_delete_product_' . (int) $p->id);
            echo '<tr>';
            echo '<td><strong><a href="' . esc_url($edit_url) . '">' . esc_html($p->name) . '</a></strong>';
            echo '<div class="row-actions">';
            echo '<span><a href="' . esc_url($edit_url) . '">' . esc_html__('Modifier', 'yv-shop') . '</a> | </span>';
            echo '<span class="delete"><a href="' . esc_url($del_url) . '" onclick="return confirm(\'' . esc_js(__('Supprimer ce produit ?', 'yv-shop')) . '\')">' . esc_html__('Supprimer', 'yv-shop') . '</a></span>';
            echo '</div></td>';
            echo '<td>' . esc_html($p->sku) . '</td>';
            echo '<td>' . esc_html(Currency::format($p->activePrice())) . '</td>';
            echo '<td>' . ($p->manage_stock ? esc_html((string) $p->stock_qty) : '—') . ' <small>(' . esc_html($p->stock_status) . ')</small></td>';
            echo '<td>' . esc_html($p->status) . '</td>';
            echo '<td>' . esc_html(mysql2date(get_option('date_format'), (string) $p->created_at)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        if ($result['total_pages'] > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([ // phpcs:ignore
                'base'    => add_query_arg('paged', '%#%'),
                'format'  => '',
                'current' => $criteria->page,
                'total'   => $result['total_pages'],
            ]);
            echo '</div></div>';
        }

        echo '</div>';
    }
}
