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

        $registry = (array) get_option('yv_shop_attributes_registry', []);

        if (!empty($_POST['yv_shop_attrs_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yv_shop_attrs_nonce'])), 'yv_shop_save_attrs')) {
            $action = sanitize_key($_POST['attr_action'] ?? '');
            if ($action === 'add') {
                $key = sanitize_key($_POST['attr_key'] ?? '');
                $label = sanitize_text_field(wp_unslash($_POST['attr_label'] ?? ''));
                if ($key && $label && !isset($registry[$key])) {
                    $registry[$key] = $label;
                    update_option('yv_shop_attributes_registry', $registry);
                    self::registerTaxonomy($key, $label);
                    flush_rewrite_rules();
                }
            } elseif ($action === 'delete') {
                $key = sanitize_key($_POST['attr_key'] ?? '');
                if (isset($registry[$key])) {
                    unset($registry[$key]);
                    update_option('yv_shop_attributes_registry', $registry);
                }
            }
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-attributes&saved=1'));
            exit;
        }

        // Flat map for display
        $attrs = [];
        foreach ($registry as $k => $label) {
            $attrs[$k] = ['label' => is_array($label) ? ($label['label'] ?? $k) : (string) $label];
        }

        echo '<div class="wrap yv-admin">';
        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title"><h1>' . esc_html__('Attributs produits', 'yv-shop') . '</h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html__('Les attributs servent à déclarer des caractéristiques réutilisables (taille, couleur, matière...) qui génèrent ensuite les variations de produit et les filtres côté boutique.', 'yv-shop') . '</p></div>';
        echo '</div>';

        if (isset($_GET['saved'])) echo '<div class="yv-admin-notice">' . esc_html__('Attributs mis à jour.', 'yv-shop') . '</div>';

        echo '<div class="yv-grid yv-grid--main-side">';

        // Main: existing attrs
        echo '<div>';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Attributs existants', 'yv-shop') . '</h2></div><div class="yv-card__body yv-card__body--flush">';
        if (empty($attrs)) {
            echo '<p style="padding:32px;text-align:center;color:var(--yv-admin-text-muted)">' . esc_html__('Aucun attribut pour l\'instant. Crée-en un à droite pour commencer.', 'yv-shop') . '</p>';
        } else {
            echo '<table class="yv-table"><thead><tr>';
            echo '<th>' . esc_html__('Libellé', 'yv-shop') . '</th>';
            echo '<th>' . esc_html__('Clé technique', 'yv-shop') . '</th>';
            echo '<th>' . esc_html__('Valeurs', 'yv-shop') . '</th>';
            echo '<th></th>';
            echo '</tr></thead><tbody>';
            foreach ($attrs as $key => $data) {
                $tax = 'yv_attr_' . $key;
                $terms_url = admin_url('edit-tags.php?taxonomy=' . $tax);
                echo '<tr>';
                echo '<td><strong>' . esc_html($data['label'] ?? $key) . '</strong></td>';
                echo '<td><code style="font-family:ui-monospace,monospace;color:var(--yv-admin-text-muted)">' . esc_html($key) . '</code></td>';
                echo '<td><a href="' . esc_url($terms_url) . '" class="yv-btn yv-btn--ghost yv-btn--sm">' . esc_html__('Gérer les valeurs', 'yv-shop') . ' →</a></td>';
                echo '<td style="text-align:right"><form method="post" style="display:inline" data-confirm="' . esc_attr__('Supprimer cet attribut ? Les produits conserveront leurs valeurs mais le filtre disparaîtra.', 'yv-shop') . '">';
                wp_nonce_field('yv_shop_save_attrs', 'yv_shop_attrs_nonce');
                echo '<input type="hidden" name="attr_action" value="delete">';
                echo '<input type="hidden" name="attr_key" value="' . esc_attr($key) . '">';
                echo '<button type="submit" class="yv-btn yv-btn--danger yv-btn--sm">' . esc_html__('Supprimer', 'yv-shop') . '</button>';
                echo '</form></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div></div>';

        // Info card
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('À savoir', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<ul style="margin:0;padding-left:20px;line-height:1.8">';
        echo '<li>' . esc_html__('La clé technique (slug) ne peut contenir que des lettres minuscules, chiffres et underscores. Une fois créée, elle ne peut plus être modifiée.', 'yv-shop') . '</li>';
        echo '<li>' . esc_html__('Le libellé apparait côté admin et côté boutique (filtres). Tu peux le changer via "Gérer les valeurs".', 'yv-shop') . '</li>';
        echo '<li>' . esc_html__('Après création, va dans "Gérer les valeurs" pour ajouter les options possibles (ex: S, M, L pour Taille).', 'yv-shop') . '</li>';
        echo '<li>' . esc_html__('Les attributs sont réutilisables sur tous tes produits. Tu n\'as pas à recréer "Taille" pour chaque produit.', 'yv-shop') . '</li>';
        echo '</ul>';
        echo '</div></div>';
        echo '</div>';

        // Sidebar: add new
        echo '<aside class="yv-sidebar">';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Ajouter un attribut', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<form method="post">';
        wp_nonce_field('yv_shop_save_attrs', 'yv_shop_attrs_nonce');
        echo '<input type="hidden" name="attr_action" value="add">';

        echo '<div class="yv-field">';
        echo '<label class="yv-field__label" for="attr_label">' . esc_html__('Libellé', 'yv-shop') . ' <span class="yv-required">*</span></label>';
        echo '<input type="text" id="attr_label" name="attr_label" required class="yv-input" placeholder="' . esc_attr__('Ex: Couleur', 'yv-shop') . '">';
        echo '<small class="yv-field__hint">' . esc_html__('Nom affiché au client. Ex : Couleur, Taille, Matière.', 'yv-shop') . '</small>';
        echo '</div>';

        echo '<div class="yv-field">';
        echo '<label class="yv-field__label" for="attr_key">' . esc_html__('Clé technique', 'yv-shop') . ' <span class="yv-required">*</span></label>';
        echo '<input type="text" id="attr_key" name="attr_key" required pattern="[a-z0-9_]+" class="yv-input" placeholder="' . esc_attr__('Ex: couleur', 'yv-shop') . '" style="font-family:ui-monospace,monospace">';
        echo '<small class="yv-field__hint">' . esc_html__('Identifiant interne. Minuscules, chiffres, underscores uniquement. Ne peut plus être modifié après création.', 'yv-shop') . '</small>';
        echo '</div>';

        echo '<button type="submit" class="yv-btn yv-btn--primary" style="width:100%;justify-content:center">' . esc_html__('Ajouter l\'attribut', 'yv-shop') . '</button>';
        echo '</form>';
        echo '</div></div>';
        echo '</aside>';

        echo '</div>';
        echo '</div>';
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
