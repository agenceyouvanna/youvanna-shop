<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Helpers\Currency;
use Youvanna\Shop\Models\Coupon;
use Youvanna\Shop\Repositories\CouponRepository;

defined('ABSPATH') || exit;

final class CouponEditor
{
    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_settings')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        $repo = new CouponRepository();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $coupon = $id ? $repo->find($id) : new Coupon();

        if (!$coupon) {
            echo '<div class="wrap yv-admin"><div class="yv-admin-notice yv-admin-notice--error">' . esc_html__('Coupon introuvable.', 'yv-shop') . '</div></div>';
            return;
        }

        if (!empty($_POST['yv_shop_coupon_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yv_shop_coupon_nonce'])), 'yv_shop_save_coupon')) {
            self::save($coupon, $repo);
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-coupons&action=edit&id=' . (int) $coupon->id . '&saved=1'));
            exit;
        }

        echo '<div class="wrap yv-admin">';
        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title"><h1>' . esc_html($id ? sprintf(__('Coupon : %s', 'yv-shop'), $coupon->code) : __('Nouveau coupon', 'yv-shop')) . '</h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html__('Crée un code promo à partager avec tes clients. Il sera validé au checkout.', 'yv-shop') . '</p></div>';
        echo '<div class="yv-admin-header__actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=yv-shop-coupons')) . '" class="yv-btn yv-btn--ghost">← ' . esc_html__('Retour', 'yv-shop') . '</a>';
        echo '</div></div>';

        if (isset($_GET['saved'])) echo '<div class="yv-admin-notice">' . esc_html__('Coupon enregistré.', 'yv-shop') . '</div>';

        echo '<form method="post">';
        wp_nonce_field('yv_shop_save_coupon', 'yv_shop_coupon_nonce');

        echo '<div class="yv-grid yv-grid--main-side">';
        echo '<div>';

        // Code + type card
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Code et type de réduction', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-row-2col">';
        self::field('code', __('Code du coupon', 'yv-shop'), [
            'type' => 'text', 'value' => $coupon->code, 'required' => true,
            'attrs' => ['style' => 'font-family:ui-monospace,monospace;text-transform:uppercase'],
            'hint' => __('Ce que le client tape dans le panier. Toujours en majuscules. Exemples : <code>BIENVENUE10</code>, <code>NOEL2026</code>, <code>LIVRAISON</code>.', 'yv-shop'),
        ]);
        self::field('type', __('Type', 'yv-shop'), [
            'type' => 'select', 'value' => $coupon->type,
            'options' => [
                'percent' => __('Pourcentage (ex: 10%)', 'yv-shop'),
                'fixed_cart' => __('Montant fixe sur le panier', 'yv-shop'),
                'fixed_product' => __('Montant fixe par produit', 'yv-shop'),
                'free_shipping' => __('Livraison offerte', 'yv-shop'),
            ],
            'hint' => __('"Pourcentage" = -X% sur le total. "Montant fixe panier" = -X € sur le total. "Livraison offerte" = 0€ de port.', 'yv-shop'),
        ]);
        echo '</div>';

        self::field('amount', __('Valeur de la réduction', 'yv-shop'), [
            'type' => 'number', 'step' => '0.01', 'min' => '0',
            'value' => (string) $coupon->amount,
            'hint' => __('Pour "Pourcentage" : chiffre sans le % (ex: <code>10</code> pour -10%). Pour "Montant fixe" : montant en euros. Ignoré pour "Livraison offerte".', 'yv-shop'),
        ]);

        self::field('description', __('Description (interne)', 'yv-shop'), [
            'type' => 'text', 'value' => (string) $coupon->description,
            'hint' => __('Pour toi uniquement. Rappel de l\'objectif du coupon.', 'yv-shop'),
        ]);
        echo '</div></div>';

        // Restrictions card
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Conditions d\'utilisation', 'yv-shop') . '</h2></div><div class="yv-card__body">';

        echo '<div class="yv-row-2col">';
        self::field('minimum_amount', __('Montant minimum du panier', 'yv-shop'), [
            'type' => 'number', 'step' => '0.01', 'min' => '0',
            'value' => $coupon->minimum_amount !== null ? (string) $coupon->minimum_amount : '',
            'suffix' => Currency::symbol(),
            'hint' => __('Le coupon n\'est applicable que si le panier atteint ce montant. Laisse vide pour ne pas imposer de minimum.', 'yv-shop'),
        ]);
        self::field('maximum_amount', __('Montant maximum du panier', 'yv-shop'), [
            'type' => 'number', 'step' => '0.01', 'min' => '0',
            'value' => $coupon->maximum_amount !== null ? (string) $coupon->maximum_amount : '',
            'suffix' => Currency::symbol(),
            'hint' => __('Bloque le coupon au-delà de ce montant. Rarement utilisé.', 'yv-shop'),
        ]);
        echo '</div>';

        self::switchField('individual_use', __('Usage individuel (non cumulable)', 'yv-shop'), $coupon->individual_use, __('Si activé, ce coupon ne peut pas être combiné avec d\'autres coupons sur la même commande.', 'yv-shop'));
        self::switchField('exclude_sale_items', __('Exclure les articles en promo', 'yv-shop'), $coupon->exclude_sale_items, __('Si activé, les produits déjà en soldes ne bénéficient pas de ce coupon.', 'yv-shop'));

        echo '</div></div>';

        // Limits
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Limites d\'utilisation', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-row-2col">';
        self::field('usage_limit', __('Limite d\'utilisations (global)', 'yv-shop'), [
            'type' => 'number', 'min' => '0', 'step' => '1',
            'value' => $coupon->usage_limit !== null ? (string) $coupon->usage_limit : '',
            'hint' => __('Nombre max de fois que ce coupon peut être utilisé au total. Vide = illimité.', 'yv-shop'),
        ]);
        self::field('usage_limit_per_user', __('Limite par client', 'yv-shop'), [
            'type' => 'number', 'min' => '0', 'step' => '1',
            'value' => $coupon->usage_limit_per_user !== null ? (string) $coupon->usage_limit_per_user : '',
            'hint' => __('Nombre max de fois qu\'un même email peut utiliser ce coupon. Vide = illimité.', 'yv-shop'),
        ]);
        echo '</div>';
        echo '</div></div>';

        // Dates
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Période de validité', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-row-2col">';
        self::field('starts_at', __('Début de validité', 'yv-shop'), [
            'type' => 'datetime-local',
            'value' => $coupon->starts_at ? str_replace(' ', 'T', substr((string) $coupon->starts_at, 0, 16)) : '',
            'hint' => __('Vide = actif immédiatement.', 'yv-shop'),
        ]);
        self::field('expires_at', __('Expiration', 'yv-shop'), [
            'type' => 'datetime-local',
            'value' => $coupon->expires_at ? str_replace(' ', 'T', substr((string) $coupon->expires_at, 0, 16)) : '',
            'hint' => __('Vide = jamais. Après cette date, le coupon est automatiquement désactivé.', 'yv-shop'),
        ]);
        echo '</div>';
        echo '</div></div>';

        echo '</div>';

        // Sidebar
        echo '<aside class="yv-sidebar">';
        echo '<div class="yv-card"><div class="yv-card__body">';
        echo '<button type="submit" class="yv-btn yv-btn--primary" style="width:100%;justify-content:center">' . esc_html($id ? __('Enregistrer', 'yv-shop') : __('Créer le coupon', 'yv-shop')) . '</button>';
        if ($id) {
            echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=yv-shop-coupons&action=delete&id=' . (int) $coupon->id), 'yv_shop_delete_coupon_' . (int) $coupon->id)) . '" class="yv-btn yv-btn--danger" data-confirm="' . esc_attr__('Supprimer ce coupon ?', 'yv-shop') . '" style="width:100%;justify-content:center;margin-top:8px">' . esc_html__('Supprimer', 'yv-shop') . '</a>';
        }
        echo '</div></div>';

        if ($id) {
            echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Utilisation', 'yv-shop') . '</h2></div><div class="yv-card__body">';
            echo '<div class="yv-field"><div class="yv-field__label">' . esc_html__('Fois utilisé', 'yv-shop') . '</div><div style="font-size:24px;font-weight:700">' . (int) $coupon->used_count . '</div></div>';
            echo '<div class="yv-field"><div class="yv-field__label">' . esc_html__('Créé le', 'yv-shop') . '</div><div>' . esc_html($coupon->created_at ? mysql2date(get_option('date_format'), (string) $coupon->created_at) : '—') . '</div></div>';
            echo '</div></div>';
        }
        echo '</aside>';

        echo '</div>';
        echo '</form></div>';
    }

    private static function save(Coupon $c, CouponRepository $repo): void
    {
        $p = wp_unslash($_POST);
        $c->code = strtoupper(sanitize_text_field($p['code'] ?? ''));
        $c->type = sanitize_key($p['type'] ?? 'percent');
        $c->amount = (float) ($p['amount'] ?? 0);
        $c->description = $p['description'] !== '' ? sanitize_text_field($p['description']) : null;
        $c->minimum_amount = $p['minimum_amount'] !== '' ? (float) $p['minimum_amount'] : null;
        $c->maximum_amount = $p['maximum_amount'] !== '' ? (float) $p['maximum_amount'] : null;
        $c->individual_use = !empty($p['individual_use']);
        $c->exclude_sale_items = !empty($p['exclude_sale_items']);
        $c->usage_limit = $p['usage_limit'] !== '' ? (int) $p['usage_limit'] : null;
        $c->usage_limit_per_user = $p['usage_limit_per_user'] !== '' ? (int) $p['usage_limit_per_user'] : null;
        $c->starts_at = !empty($p['starts_at']) ? str_replace('T', ' ', (string) $p['starts_at']) . ':00' : null;
        $c->expires_at = !empty($p['expires_at']) ? str_replace('T', ' ', (string) $p['expires_at']) . ':00' : null;
        $repo->save($c);
    }

    private static function field(string $name, string $label, array $opts): void
    {
        $type = $opts['type'] ?? 'text';
        $value = (string) ($opts['value'] ?? '');
        $required = !empty($opts['required']);
        $hint = $opts['hint'] ?? '';
        $attrs = $opts['attrs'] ?? [];
        $attrStr = '';
        foreach ($attrs as $k => $v) $attrStr .= ' ' . $k . '="' . esc_attr((string) $v) . '"';

        echo '<div class="yv-field">';
        echo '<label class="yv-field__label" for="yv_c_' . esc_attr($name) . '">' . esc_html($label) . ($required ? ' <span class="yv-required">*</span>' : '') . '</label>';

        $prefix = $opts['prefix'] ?? null;
        $suffix = $opts['suffix'] ?? null;

        if ($type === 'textarea') {
            echo '<textarea id="yv_c_' . esc_attr($name) . '" name="' . esc_attr($name) . '" rows="' . (int) ($opts['rows'] ?? 3) . '" class="yv-textarea"' . ($required ? ' required' : '') . $attrStr . '>' . esc_textarea($value) . '</textarea>';
        } elseif ($type === 'select') {
            echo '<select id="yv_c_' . esc_attr($name) . '" name="' . esc_attr($name) . '" class="yv-select"' . $attrStr . '>';
            foreach (($opts['options'] ?? []) as $k => $v) {
                echo '<option value="' . esc_attr((string) $k) . '"' . ($value === (string) $k ? ' selected' : '') . '>' . esc_html($v) . '</option>';
            }
            echo '</select>';
        } else {
            $stepAttr = isset($opts['step']) ? ' step="' . esc_attr($opts['step']) . '"' : '';
            $minAttr = isset($opts['min']) ? ' min="' . esc_attr($opts['min']) . '"' : '';
            $input = '<input type="' . esc_attr($type) . '" id="yv_c_' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="yv-input"' . $stepAttr . $minAttr . ($required ? ' required' : '') . $attrStr . '>';
            if ($prefix || $suffix) {
                echo '<div class="yv-input-group">';
                if ($prefix) echo '<div class="yv-input-group__addon">' . esc_html($prefix) . '</div>';
                echo $input;
                if ($suffix) echo '<div class="yv-input-group__addon">' . esc_html($suffix) . '</div>';
                echo '</div>';
            } else {
                echo $input;
            }
        }
        if ($hint) echo '<small class="yv-field__hint">' . wp_kses_post($hint) . '</small>';
        echo '</div>';
    }

    private static function switchField(string $name, string $label, bool $value, string $hint = ''): void
    {
        echo '<div class="yv-field">';
        echo '<label class="yv-switch"><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . ($value ? ' checked' : '') . '><span class="yv-switch__track"></span><span class="yv-switch__label">' . esc_html($label) . '</span></label>';
        if ($hint) echo '<small class="yv-field__hint">' . wp_kses_post($hint) . '</small>';
        echo '</div>';
    }
}
