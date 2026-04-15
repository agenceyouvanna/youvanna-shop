<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Models\Product;
use Youvanna\Shop\Repositories\ProductRepository;

defined('ABSPATH') || exit;

final class ProductEditor
{
    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_products')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        $repo = new ProductRepository();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $product = $id ? $repo->find($id) : new Product();

        if (!$product) {
            echo '<div class="wrap"><h1>' . esc_html__('Produit introuvable', 'yv-shop') . '</h1></div>';
            return;
        }

        if (!empty($_POST['yv_shop_product_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yv_shop_product_nonce'])), 'yv_shop_save_product')) {
            self::saveFromRequest($product, $repo);
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-products&action=edit&id=' . (int) $product->id . '&saved=1'));
            exit;
        }

        echo '<div class="wrap"><h1>' . esc_html($id ? __('Modifier le produit', 'yv-shop') : __('Nouveau produit', 'yv-shop')) . '</h1>';
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Produit enregistré.', 'yv-shop') . '</p></div>';
        }

        echo '<form method="post">';
        wp_nonce_field('yv_shop_save_product', 'yv_shop_product_nonce');

        echo '<table class="form-table">';
        self::textRow('name', __('Nom', 'yv-shop'), $product->name, true);
        self::textRow('slug', __('Slug', 'yv-shop'), $product->slug);
        self::textRow('sku', __('SKU', 'yv-shop'), $product->sku);
        self::selectRow('status', __('Statut', 'yv-shop'), $product->status, [
            'draft' => __('Brouillon', 'yv-shop'),
            'published' => __('Publié', 'yv-shop'),
            'private' => __('Privé', 'yv-shop'),
        ]);
        self::checkboxRow('featured', __('Mis en avant', 'yv-shop'), $product->featured);
        self::textareaRow('short_description', __('Description courte', 'yv-shop'), $product->short_description);
        self::textareaRow('description', __('Description', 'yv-shop'), $product->description, 10);
        self::textRow('price', __('Prix', 'yv-shop'), (string) $product->price, false, 'number', 'step="0.01"');
        self::textRow('sale_price', __('Prix promo', 'yv-shop'), $product->sale_price !== null ? (string) $product->sale_price : '', false, 'number', 'step="0.01"');
        self::textRow('cost_price', __('Prix de revient', 'yv-shop'), $product->cost_price !== null ? (string) $product->cost_price : '', false, 'number', 'step="0.01"');
        self::selectRow('tax_status', __('TVA applicable', 'yv-shop'), $product->tax_status, [
            'taxable' => __('Taxable', 'yv-shop'),
            'none' => __('Non taxable', 'yv-shop'),
        ]);
        self::textRow('tax_class', __('Classe TVA', 'yv-shop'), $product->tax_class);
        self::checkboxRow('manage_stock', __('Gérer le stock', 'yv-shop'), $product->manage_stock);
        self::textRow('stock_qty', __('Quantité', 'yv-shop'), (string) $product->stock_qty, false, 'number');
        self::selectRow('stock_status', __('Statut stock', 'yv-shop'), $product->stock_status, [
            'instock' => __('En stock', 'yv-shop'),
            'outofstock' => __('Rupture', 'yv-shop'),
            'onbackorder' => __('Commande possible', 'yv-shop'),
        ]);
        self::textRow('weight', __('Poids (kg)', 'yv-shop'), $product->weight !== null ? (string) $product->weight : '', false, 'number', 'step="0.001"');
        self::imageRow('image_id', __('Image principale', 'yv-shop'), $product->image_id);
        self::categoriesRow('categories', $product->category_ids);
        self::textRow('sort_order', __('Ordre', 'yv-shop'), (string) $product->sort_order, false, 'number');
        echo '</table>';

        submit_button($id ? __('Enregistrer', 'yv-shop') : __('Créer', 'yv-shop'));
        echo '</form></div>';

        self::enqueueMedia();
    }

    private static function saveFromRequest(Product $product, ProductRepository $repo): void
    {
        $post = wp_unslash($_POST);
        $product->name = sanitize_text_field($post['name'] ?? '');
        $product->slug = sanitize_title($post['slug'] ?? '');
        $product->sku = sanitize_text_field($post['sku'] ?? '');
        $product->status = sanitize_key($post['status'] ?? 'draft');
        $product->featured = !empty($post['featured']);
        $product->short_description = wp_kses_post($post['short_description'] ?? '');
        $product->description = wp_kses_post($post['description'] ?? '');
        $product->price = (float) ($post['price'] ?? 0);
        $product->sale_price = $post['sale_price'] !== '' ? (float) $post['sale_price'] : null;
        $product->cost_price = $post['cost_price'] !== '' ? (float) $post['cost_price'] : null;
        $product->tax_status = sanitize_key($post['tax_status'] ?? 'taxable');
        $product->tax_class = sanitize_key($post['tax_class'] ?? 'standard');
        $product->manage_stock = !empty($post['manage_stock']);
        $product->stock_qty = (int) ($post['stock_qty'] ?? 0);
        $product->stock_status = sanitize_key($post['stock_status'] ?? 'instock');
        $product->weight = $post['weight'] !== '' ? (float) $post['weight'] : null;
        $product->image_id = !empty($post['image_id']) ? (int) $post['image_id'] : null;
        $product->sort_order = (int) ($post['sort_order'] ?? 0);
        $product->category_ids = !empty($post['categories']) ? array_map('intval', (array) $post['categories']) : [];

        $repo->save($product);
    }

    private static function textRow(string $name, string $label, string $value, bool $required = false, string $type = 'text', string $extra = ''): void
    {
        echo '<tr><th><label for="yv_' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="' . esc_attr($type) . '" id="yv_' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text" ' . ($required ? 'required' : '') . ' ' . $extra . '>';
        echo '</td></tr>';
    }

    private static function textareaRow(string $name, string $label, ?string $value, int $rows = 4): void
    {
        echo '<tr><th><label for="yv_' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<textarea id="yv_' . esc_attr($name) . '" name="' . esc_attr($name) . '" rows="' . (int) $rows . '" class="large-text">' . esc_textarea((string) $value) . '</textarea>';
        echo '</td></tr>';
    }

    private static function selectRow(string $name, string $label, string $value, array $options): void
    {
        echo '<tr><th><label for="yv_' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<select id="yv_' . esc_attr($name) . '" name="' . esc_attr($name) . '">';
        foreach ($options as $k => $v) {
            $sel = $value === $k ? ' selected' : '';
            echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($v) . '</option>';
        }
        echo '</select></td></tr>';
    }

    private static function checkboxRow(string $name, string $label, bool $checked): void
    {
        echo '<tr><th>' . esc_html($label) . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . '> ' . esc_html__('Activer', 'yv-shop') . '</label>';
        echo '</td></tr>';
    }

    private static function imageRow(string $name, string $label, ?int $image_id): void
    {
        $url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        echo '<tr><th>' . esc_html($label) . '</th><td>';
        echo '<div class="yv-shop-image-picker">';
        echo '<input type="hidden" id="yv_' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) ($image_id ?? '')) . '">';
        echo '<img id="yv_' . esc_attr($name) . '_preview" src="' . esc_url($url) . '" style="max-width:120px;display:' . ($url ? 'block' : 'none') . '">';
        echo '<button type="button" class="button" id="yv_' . esc_attr($name) . '_pick">' . esc_html__('Choisir une image', 'yv-shop') . '</button>';
        echo ' <button type="button" class="button-link-delete" id="yv_' . esc_attr($name) . '_clear">' . esc_html__('Retirer', 'yv-shop') . '</button>';
        echo '</div>';
        echo '<script>jQuery(function($){
            var f=' . wp_json_encode('yv_' . $name) . ';
            $("#"+f+"_pick").on("click",function(e){e.preventDefault();var frame=wp.media({title:"Sélectionner",multiple:false});frame.on("select",function(){var a=frame.state().get("selection").first().toJSON();$("#"+f).val(a.id);$("#"+f+"_preview").attr("src",a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url).show();});frame.open();});
            $("#"+f+"_clear").on("click",function(e){e.preventDefault();$("#"+f).val("");$("#"+f+"_preview").hide();});
        });</script>';
        echo '</td></tr>';
    }

    private static function categoriesRow(string $name, array $selected): void
    {
        $terms = get_terms(['taxonomy' => 'yv_category', 'hide_empty' => false]);
        echo '<tr><th>' . esc_html__('Catégories', 'yv-shop') . '</th><td>';
        if (is_wp_error($terms) || empty($terms)) {
            echo '<em>' . esc_html__('Aucune catégorie. Créez-en une via Produits → Catégories.', 'yv-shop') . '</em>';
        } else {
            foreach ($terms as $t) {
                $checked = in_array((int) $t->term_id, $selected, true) ? ' checked' : '';
                echo '<label style="display:block"><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . (int) $t->term_id . '"' . $checked . '> ' . esc_html($t->name) . '</label>';
            }
        }
        echo '</td></tr>';
    }

    private static function enqueueMedia(): void
    {
        wp_enqueue_media();
    }
}
