<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Services\LensConfigurator;

defined('ABSPATH') || exit;

final class LensSettingsPage
{
    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerSettings(): void
    {
        register_setting('yv_shop_lens', 'yv_shop_fittingbox_pd_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('yv_shop_lens', 'yv_shop_fitmix_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('yv_shop_lens', 'yv_shop_lens_prices', ['sanitize_callback' => [$this, 'sanitizePrices']]);
    }

    public function sanitizePrices($value): array
    {
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $k => $v) {
            $key = sanitize_key((string) $k);
            $num = is_numeric($v) ? (float) $v : 0;
            if ($key !== '') $out[$key] = round($num, 2);
        }
        return $out;
    }

    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_settings')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        echo '<div class="wrap yv-admin">';
        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title"><h1>' . esc_html__('Configurateur de verres', 'yv-shop') . '</h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html__('Prix des options verres et clés FittingBox (mesure écart pupillaire + essayage virtuel).', 'yv-shop') . '</p></div>';
        echo '</div>';

        if (isset($_GET['settings-updated'])) {
            echo '<div class="yv-admin-notice">' . esc_html__('Réglages enregistrés.', 'yv-shop') . '</div>';
        }

        echo '<form method="post" action="options.php">';
        settings_fields('yv_shop_lens');

        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Intégration FittingBox', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-infobox">';
        echo '<strong>' . esc_html__('Où trouver mes clés ?', 'yv-shop') . '</strong>';
        echo '<p>' . wp_kses(__('Sur <code>client.fittingbox.com</code> → Products → Integration keys. La clé PD Measurement sert à mesurer l\'écart pupillaire. La clé FitMix sert à l\'essayage virtuel.', 'yv-shop'), ['code' => [], 'p' => []]) . '</p>';
        echo '</div>';

        $pd = (string) get_option('yv_shop_fittingbox_pd_key', '');
        $fm = (string) get_option('yv_shop_fitmix_key', '');

        echo '<div class="yv-field">';
        echo '<label class="yv-field__label" for="yv_fittingbox_pd">' . esc_html__('Clé PD Measurement', 'yv-shop') . '</label>';
        echo '<input type="text" id="yv_fittingbox_pd" name="yv_shop_fittingbox_pd_key" value="' . esc_attr($pd) . '" class="yv-input" placeholder="mira-pd-xxxx">';
        echo '<small class="yv-field__hint">' . esc_html__('Active le widget de mesure par caméra. Laisse vide pour proposer uniquement la saisie manuelle.', 'yv-shop') . '</small>';
        echo '</div>';

        echo '<div class="yv-field">';
        echo '<label class="yv-field__label" for="yv_fitmix">' . esc_html__('Clé FitMix (VTO)', 'yv-shop') . '</label>';
        echo '<input type="text" id="yv_fitmix" name="yv_shop_fitmix_key" value="' . esc_attr($fm) . '" class="yv-input" placeholder="mira-vto-xxxx">';
        echo '<small class="yv-field__hint">' . esc_html__('Essayage virtuel de la monture via caméra. Doit correspondre au compte FittingBox qui héberge les modèles 3D.', 'yv-shop') . '</small>';
        echo '</div>';

        echo '</div></div>';

        $prices = LensConfigurator::prices();
        $labels = [
            'tinted_polarized'      => __('Verres polarisants (solaires)', 'yv-shop'),
            'thinning_16'           => __('Amincissement indice 1.6', 'yv-shop'),
            'thinning_167'          => __('Amincissement indice 1.67', 'yv-shop'),
            'coating_basic'         => __('Anti-reflets basique (solaires)', 'yv-shop'),
            'coating_standard'      => __('Anti-reflets standard', 'yv-shop'),
            'coating_blue'          => __('Anti-lumière bleue', 'yv-shop'),
            'coating_pc'            => __('Anti-reflets photochromiques', 'yv-shop'),
            'range_standard'        => __('Gamme Standard (blancs)', 'yv-shop'),
            'range_premium'         => __('Gamme Premium Clearview', 'yv-shop'),
            'photochromic'          => __('Traitement photochromique', 'yv-shop'),
            'progressive_easyview'  => __('Progressifs EasyView HD', 'yv-shop'),
            'progressive_classic'   => __('Progressifs Classic', 'yv-shop'),
        ];

        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Prix des options verres', 'yv-shop') . '</h2><span class="yv-card__hint">' . esc_html__('Supplément ajouté au prix de la monture selon les choix du client.', 'yv-shop') . '</span></div><div class="yv-card__body">';
        echo '<div class="yv-row-2col">';
        foreach ($labels as $key => $label) {
            $val = isset($prices[$key]) ? (float) $prices[$key] : 0;
            echo '<div class="yv-field">';
            echo '<label class="yv-field__label" for="yv_price_' . esc_attr($key) . '">' . esc_html($label) . '</label>';
            echo '<div class="yv-input-group">';
            echo '<input type="number" step="0.01" min="0" id="yv_price_' . esc_attr($key) . '" name="yv_shop_lens_prices[' . esc_attr($key) . ']" value="' . esc_attr((string) $val) . '" class="yv-input">';
            echo '<div class="yv-input-group__addon">' . esc_html(\Youvanna\Shop\Helpers\Currency::symbol()) . '</div>';
            echo '</div></div>';
        }
        echo '</div></div></div>';

        echo '<div style="margin-top:20px"><button type="submit" class="yv-btn yv-btn--primary">' . esc_html__('Enregistrer les réglages', 'yv-shop') . '</button></div>';
        echo '</form></div>';
    }
}
