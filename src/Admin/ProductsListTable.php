<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Helpers\Currency;
use Youvanna\Shop\Models\SearchCriteria;
use Youvanna\Shop\Repositories\ProductRepository;

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

        // Bulk actions
        if (!empty($_POST['bulk_action']) && !empty($_POST['ids']) && check_admin_referer('yv_shop_products_bulk')) {
            self::handleBulk(sanitize_key($_POST['bulk_action']), array_map('intval', (array) $_POST['ids']));
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-products&bulk=1'));
            exit;
        }

        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $repo = new ProductRepository();
        $criteria = new SearchCriteria();
        $criteria->page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $criteria->per_page = 20;
        $criteria->statuses = $status ? [$status] : ['published', 'draft', 'private'];
        $criteria->orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'created_at';
        $criteria->order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';
        if (!empty($_GET['s'])) {
            $criteria->query = sanitize_text_field(wp_unslash($_GET['s']));
        }

        $result = $repo->search($criteria);
        $new_url = admin_url('admin.php?page=yv-shop-products&action=new');

        echo '<div class="wrap yv-admin">';

        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title"><h1>' . esc_html__('Produits', 'yv-shop') . '</h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html(sprintf(__('%d produits au total', 'yv-shop'), (int) $result['total'])) . '</p></div>';
        echo '<div class="yv-admin-header__actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=yv-shop-import')) . '" class="yv-btn yv-btn--secondary"><span class="dashicons dashicons-upload"></span>' . esc_html__('Import CSV', 'yv-shop') . '</a>';
        echo '<a href="' . esc_url($new_url) . '" class="yv-btn yv-btn--primary"><span class="dashicons dashicons-plus-alt2"></span>' . esc_html__('Nouveau produit', 'yv-shop') . '</a>';
        echo '</div></div>';

        if (isset($_GET['saved'])) echo '<div class="yv-admin-notice">' . esc_html__('Produit enregistré.', 'yv-shop') . '</div>';
        if (isset($_GET['deleted'])) echo '<div class="yv-admin-notice">' . esc_html__('Produit supprimé.', 'yv-shop') . '</div>';
        if (isset($_GET['bulk'])) echo '<div class="yv-admin-notice">' . esc_html__('Action groupée appliquée.', 'yv-shop') . '</div>';

        // Filters bar
        echo '<div class="yv-filters">';
        echo '<div class="yv-filters__tabs">';
        $statuses = [
            '' => __('Tous', 'yv-shop'),
            'published' => __('Publiés', 'yv-shop'),
            'draft' => __('Brouillons', 'yv-shop'),
            'private' => __('Privés', 'yv-shop'),
        ];
        foreach ($statuses as $k => $lbl) {
            $url = admin_url('admin.php?page=yv-shop-products' . ($k ? '&status=' . $k : ''));
            $cls = $status === $k ? ' is-active' : '';
            echo '<a href="' . esc_url($url) . '" class="yv-filters__tab' . $cls . '">' . esc_html($lbl) . '</a>';
        }
        echo '</div>';
        echo '<div class="yv-filters__spacer"></div>';
        echo '<form method="get" class="yv-filters__search"><input type="hidden" name="page" value="yv-shop-products">';
        if ($status) echo '<input type="hidden" name="status" value="' . esc_attr($status) . '">';
        echo '<input type="search" name="s" class="yv-input" value="' . esc_attr($criteria->query) . '" placeholder="' . esc_attr__('Rechercher un produit...', 'yv-shop') . '">';
        echo '</form>';
        echo '</div>';

        echo '<form method="post">';
        wp_nonce_field('yv_shop_products_bulk');

        echo '<table class="yv-table">';
        echo '<thead><tr>';
        echo '<th style="width:28px"><input type="checkbox" onclick="jQuery(this).closest(\'table\').find(\'tbody input[type=checkbox]\').prop(\'checked\',this.checked)"></th>';
        echo '<th style="width:72px"></th>';
        echo '<th>' . esc_html__('Nom', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('SKU', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Prix', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Stock', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Statut', 'yv-shop') . '</th>';
        echo '<th>' . esc_html__('Date', 'yv-shop') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($result['items'])) {
            echo '<tr><td colspan="8" class="yv-table__empty">' . esc_html__('Aucun produit. Commence par en créer un.', 'yv-shop') . '</td></tr>';
        }

        foreach ($result['items'] as $p) {
            $edit_url = admin_url('admin.php?page=yv-shop-products&action=edit&id=' . (int) $p->id);
            $del_url = wp_nonce_url(admin_url('admin.php?page=yv-shop-products&action=delete&id=' . (int) $p->id), 'yv_shop_delete_product_' . (int) $p->id);
            $thumb = $p->image_id ? wp_get_attachment_image_url($p->image_id, 'thumbnail') : '';
            echo '<tr>';
            echo '<td><input type="checkbox" name="ids[]" value="' . (int) $p->id . '"></td>';
            echo '<td>' . ($thumb ? '<img src="' . esc_url($thumb) . '" alt="" style="width:48px;height:48px;object-fit:contain;background:#fafbfd;border:1px solid var(--yv-admin-border);border-radius:6px;padding:4px">' : '<div style="width:48px;height:48px;background:#fafbfd;border:1px solid var(--yv-admin-border);border-radius:6px"></div>') . '</td>';
            echo '<td><strong><a href="' . esc_url($edit_url) . '" style="text-decoration:none;color:var(--yv-admin-text)">' . esc_html($p->name) . '</a></strong>';
            echo '<div class="yv-row-actions"><a href="' . esc_url($edit_url) . '">' . esc_html__('Modifier', 'yv-shop') . '</a>';
            echo ' <a href="' . esc_url($p->permalink()) . '" target="_blank">' . esc_html__('Voir', 'yv-shop') . '</a>';
            echo ' <a href="' . esc_url($del_url) . '" class="danger" data-confirm="' . esc_attr__('Supprimer ce produit ?', 'yv-shop') . '">' . esc_html__('Supprimer', 'yv-shop') . '</a>';
            echo '</div></td>';
            echo '<td>' . esc_html($p->sku) . '</td>';
            echo '<td><strong>' . esc_html(Currency::format($p->activePrice())) . '</strong>';
            if ($p->isOnSale()) echo '<br><small style="text-decoration:line-through;color:var(--yv-admin-text-soft)">' . esc_html(Currency::format($p->price)) . '</small>';
            echo '</td>';
            if ($p->manage_stock) {
                echo '<td><strong>' . (int) $p->stock_qty . '</strong> <small style="color:var(--yv-admin-text-muted)">' . esc_html(self::stockLabel($p->stock_status)) . '</small></td>';
            } else {
                echo '<td><small style="color:var(--yv-admin-text-muted)">' . esc_html(self::stockLabel($p->stock_status)) . '</small></td>';
            }
            echo '<td><span class="yv-pill yv-pill--' . esc_attr($p->status) . '">' . esc_html(self::statusLabel($p->status)) . '</span></td>';
            echo '<td><small style="color:var(--yv-admin-text-muted)">' . esc_html(mysql2date(get_option('date_format'), (string) $p->created_at)) . '</small></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        // Bulk actions
        echo '<div style="margin-top:14px;display:flex;gap:10px;align-items:center">';
        echo '<select name="bulk_action" class="yv-select" style="width:auto;min-width:200px">';
        echo '<option value="">' . esc_html__('Actions groupées...', 'yv-shop') . '</option>';
        echo '<option value="publish">' . esc_html__('Publier', 'yv-shop') . '</option>';
        echo '<option value="draft">' . esc_html__('Passer en brouillon', 'yv-shop') . '</option>';
        echo '<option value="feature">' . esc_html__('Mettre en avant', 'yv-shop') . '</option>';
        echo '<option value="unfeature">' . esc_html__('Retirer mise en avant', 'yv-shop') . '</option>';
        echo '<option value="delete">' . esc_html__('Supprimer', 'yv-shop') . '</option>';
        echo '</select>';
        echo '<button type="submit" class="yv-btn yv-btn--secondary" data-confirm="' . esc_attr__('Appliquer sur les produits sélectionnés ?', 'yv-shop') . '">' . esc_html__('Appliquer', 'yv-shop') . '</button>';
        echo '</div>';

        echo '</form>';

        if ($result['total_pages'] > 1) {
            echo '<div style="margin-top:20px;text-align:center">';
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $criteria->page,
                'total' => $result['total_pages'],
            ]);
            echo '</div>';
        }

        echo '</div>';
    }

    private static function statusLabel(string $status): string
    {
        return [
            'draft' => __('Brouillon', 'yv-shop'),
            'published' => __('Publié', 'yv-shop'),
            'private' => __('Privé', 'yv-shop'),
        ][$status] ?? $status;
    }

    private static function stockLabel(string $status): string
    {
        return [
            'instock' => __('En stock', 'yv-shop'),
            'outofstock' => __('Rupture', 'yv-shop'),
            'onbackorder' => __('Sur commande', 'yv-shop'),
        ][$status] ?? $status;
    }

    private static function handleBulk(string $action, array $ids): void
    {
        if (!$ids || !current_user_can('yv_shop_manage_products')) return;
        $repo = new ProductRepository();
        foreach ($ids as $id) {
            $p = $repo->find((int) $id);
            if (!$p) continue;
            switch ($action) {
                case 'publish': $p->status = 'published'; $repo->save($p); break;
                case 'draft': $p->status = 'draft'; $repo->save($p); break;
                case 'feature': $p->featured = true; $repo->save($p); break;
                case 'unfeature': $p->featured = false; $repo->save($p); break;
                case 'delete': $repo->delete((int) $id); break;
            }
        }
    }
}
