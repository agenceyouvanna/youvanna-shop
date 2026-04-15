<?php
namespace Youvanna\Shop\Admin;

defined('ABSPATH') || exit;

final class AttributesPage
{
    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_products')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        $attrs = (array) get_option('yv_shop_attributes', []);

        if (!empty($_POST['yv_shop_attrs_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yv_shop_attrs_nonce'])), 'yv_shop_save_attrs')) {
            $action = sanitize_key($_POST['attr_action'] ?? '');
            if ($action === 'add') {
                $key = sanitize_key($_POST['attr_key'] ?? '');
                $label = sanitize_text_field(wp_unslash($_POST['attr_label'] ?? ''));
                if ($key && $label && !isset($attrs[$key])) {
                    $attrs[$key] = ['label' => $label];
                    update_option('yv_shop_attributes', $attrs);
                    self::registerTaxonomy($key, $label);
                    flush_rewrite_rules();
                }
            } elseif ($action === 'delete') {
                $key = sanitize_key($_POST['attr_key'] ?? '');
                if (isset($attrs[$key])) {
                    unset($attrs[$key]);
                    update_option('yv_shop_attributes', $attrs);
                }
            }
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-attributes&saved=1'));
            exit;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Attributs produits', 'yv-shop') . '</h1>';
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Attributs mis à jour.', 'yv-shop') . '</p></div>';
        }

        echo '<div style="display:grid;grid-template-columns:1fr 2fr;gap:2rem;margin-top:1rem">';

        echo '<div>';
        echo '<h2>' . esc_html__('Ajouter un attribut', 'yv-shop') . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('yv_shop_save_attrs', 'yv_shop_attrs_nonce');
        echo '<input type="hidden" name="attr_action" value="add">';
        echo '<p><label>' . esc_html__('Clé (slug, ex: couleur)', 'yv-shop') . '<br><input type="text" name="attr_key" required pattern="[a-z0-9_]+" class="regular-text"></label></p>';
        echo '<p><label>' . esc_html__('Libellé', 'yv-shop') . '<br><input type="text" name="attr_label" required class="regular-text"></label></p>';
        submit_button(__('Ajouter', 'yv-shop'));
        echo '</form>';
        echo '</div>';

        echo '<div>';
        echo '<h2>' . esc_html__('Attributs existants', 'yv-shop') . '</h2>';
        if (empty($attrs)) {
            echo '<p>' . esc_html__('Aucun attribut.', 'yv-shop') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
            echo '<th>' . esc_html__('Clé', 'yv-shop') . '</th><th>' . esc_html__('Libellé', 'yv-shop') . '</th>';
            echo '<th>' . esc_html__('Valeurs', 'yv-shop') . '</th><th></th></tr></thead><tbody>';
            foreach ($attrs as $key => $data) {
                $tax = 'yv_attr_' . $key;
                $terms_url = admin_url('edit-tags.php?taxonomy=' . $tax);
                echo '<tr>';
                echo '<td><code>' . esc_html($key) . '</code></td>';
                echo '<td>' . esc_html($data['label'] ?? $key) . '</td>';
                echo '<td><a href="' . esc_url($terms_url) . '">' . esc_html__('Gérer les valeurs', 'yv-shop') . '</a></td>';
                echo '<td><form method="post" style="display:inline" onsubmit="return confirm(\'' . esc_js(__('Supprimer cet attribut ?', 'yv-shop')) . '\')">';
                wp_nonce_field('yv_shop_save_attrs', 'yv_shop_attrs_nonce');
                echo '<input type="hidden" name="attr_action" value="delete">';
                echo '<input type="hidden" name="attr_key" value="' . esc_attr($key) . '">';
                echo '<button type="submit" class="button-link-delete">' . esc_html__('Supprimer', 'yv-shop') . '</button>';
                echo '</form></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '</div></div>';
    }

    private static function registerTaxonomy(string $key, string $label): void
    {
        register_taxonomy('yv_attr_' . $key, [], [
            'labels' => ['name' => $label, 'singular_name' => $label],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);
    }
}
