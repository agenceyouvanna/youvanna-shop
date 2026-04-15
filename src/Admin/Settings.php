<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Services\Encryption;

defined('ABSPATH') || exit;

final class Settings
{
    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerSettings(): void
    {
        $opts = [
            'yv_shop_general_currency'         => 'sanitize_text_field',
            'yv_shop_general_currency_symbol'  => 'sanitize_text_field',
            'yv_shop_general_currency_position'=> 'sanitize_text_field',
            'yv_shop_general_thousand_sep'     => 'sanitize_text_field',
            'yv_shop_general_decimal_sep'      => 'sanitize_text_field',
            'yv_shop_general_decimals'         => 'absint',
            'yv_shop_general_shop_slug'        => 'sanitize_title',
            'yv_shop_general_product_slug'     => 'sanitize_title',
            'yv_shop_general_country'          => 'sanitize_text_field',
            'yv_shop_products_per_page'        => 'absint',
            'yv_shop_tax_default_rate'         => 'floatval',
            'yv_shop_tax_mode'                 => 'sanitize_text_field',
            'yv_shop_email_from_name'          => 'sanitize_text_field',
            'yv_shop_email_from_address'       => 'sanitize_email',
            'yv_shop_bank_iban'                => 'sanitize_text_field',
            'yv_shop_bank_bic'                 => 'sanitize_text_field',
            'yv_shop_bank_account_name'        => 'sanitize_text_field',
            'yv_shop_stripe_mode'              => 'sanitize_text_field',
            'yv_shop_stripe_test_publishable'  => 'sanitize_text_field',
            'yv_shop_stripe_live_publishable'  => 'sanitize_text_field',
        ];
        foreach ($opts as $key => $sanitize) {
            register_setting('yv_shop_settings', $key, ['sanitize_callback' => $sanitize]);
        }
        // Encrypted secrets
        register_setting('yv_shop_settings', 'yv_shop_stripe_test_secret', [
            'sanitize_callback' => [$this, 'encryptIfNeeded'],
        ]);
        register_setting('yv_shop_settings', 'yv_shop_stripe_live_secret', [
            'sanitize_callback' => [$this, 'encryptIfNeeded'],
        ]);
        register_setting('yv_shop_settings', 'yv_shop_stripe_test_webhook', [
            'sanitize_callback' => [$this, 'encryptIfNeeded'],
        ]);
        register_setting('yv_shop_settings', 'yv_shop_stripe_live_webhook', [
            'sanitize_callback' => [$this, 'encryptIfNeeded'],
        ]);
        register_setting('yv_shop_settings', 'yv_shop_payment_methods_enabled', [
            'sanitize_callback' => [$this, 'sanitizeArray'],
        ]);
    }

    public function encryptIfNeeded($value): string
    {
        $value = (string) $value;
        if ($value === '' || str_starts_with($value, 'v1:')) {
            return $value;
        }
        try {
            return (new Encryption())->encrypt($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function sanitizeArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_map('sanitize_key', $value));
    }

    public static function render(): void
    {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        $tabs = [
            'general' => __('Général', 'yv-shop'),
            'tax'     => __('Taxes', 'yv-shop'),
            'payment' => __('Paiements', 'yv-shop'),
            'email'   => __('Emails', 'yv-shop'),
        ];
        echo '<div class="wrap"><h1>' . esc_html__('Réglages Youvanna Shop', 'yv-shop') . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $cls = $key === $tab ? ' nav-tab-active' : '';
            $url = add_query_arg(['page' => 'yv-shop-settings', 'tab' => $key], admin_url('admin.php'));
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . esc_attr($cls) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields('yv_shop_settings');
        echo '<table class="form-table">';
        if ($tab === 'general') self::renderGeneral();
        elseif ($tab === 'tax') self::renderTax();
        elseif ($tab === 'payment') self::renderPayment();
        elseif ($tab === 'email') self::renderEmail();
        echo '</table>';
        submit_button();
        echo '</form></div>';
    }

    private static function field(string $label, string $option, string $type = 'text', array $options = []): void
    {
        $value = get_option($option, '');
        echo '<tr><th><label for="' . esc_attr($option) . '">' . esc_html($label) . '</label></th><td>';
        if ($type === 'select') {
            echo '<select name="' . esc_attr($option) . '" id="' . esc_attr($option) . '">';
            foreach ($options as $k => $v) {
                $sel = (string) $value === (string) $k ? ' selected' : '';
                echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($v) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'checkbox-list') {
            $vals = (array) get_option($option, []);
            foreach ($options as $k => $v) {
                $checked = in_array((string) $k, array_map('strval', $vals), true) ? ' checked' : '';
                echo '<label style="display:block"><input type="checkbox" name="' . esc_attr($option) . '[]" value="' . esc_attr($k) . '"' . $checked . '> ' . esc_html($v) . '</label>';
            }
        } else {
            $is_secret = isset($options['secret']) && $options['secret'];
            $display = $is_secret && $value && str_starts_with((string) $value, 'v1:') ? '••••••••' : esc_attr((string) $value);
            echo '<input type="' . ($is_secret ? 'password' : esc_attr($type)) . '" name="' . esc_attr($option) . '" id="' . esc_attr($option) . '" value="' . $display . '" class="regular-text">';
            if ($is_secret && $value && str_starts_with((string) $value, 'v1:')) {
                echo '<p class="description">' . esc_html__('Secret chiffré. Saisir une nouvelle valeur pour le remplacer.', 'yv-shop') . '</p>';
            }
        }
        echo '</td></tr>';
    }

    private static function renderGeneral(): void
    {
        self::field(__('Devise', 'yv-shop'), 'yv_shop_general_currency');
        self::field(__('Symbole', 'yv-shop'), 'yv_shop_general_currency_symbol');
        self::field(__('Position symbole', 'yv-shop'), 'yv_shop_general_currency_position', 'select', [
            'left' => 'Gauche', 'left_space' => 'Gauche + espace',
            'right' => 'Droite', 'right_space' => 'Droite + espace',
        ]);
        self::field(__('Séparateur milliers', 'yv-shop'), 'yv_shop_general_thousand_sep');
        self::field(__('Séparateur décimal', 'yv-shop'), 'yv_shop_general_decimal_sep');
        self::field(__('Décimales', 'yv-shop'), 'yv_shop_general_decimals', 'number');
        self::field(__('Slug boutique', 'yv-shop'), 'yv_shop_general_shop_slug');
        self::field(__('Slug produit', 'yv-shop'), 'yv_shop_general_product_slug');
        self::field(__('Pays par défaut', 'yv-shop'), 'yv_shop_general_country');
        self::field(__('Produits par page', 'yv-shop'), 'yv_shop_products_per_page', 'number');
    }

    private static function renderTax(): void
    {
        self::field(__('Mode prix', 'yv-shop'), 'yv_shop_tax_mode', 'select', [
            'incl' => __('Prix saisis TTC', 'yv-shop'),
            'excl' => __('Prix saisis HT', 'yv-shop'),
        ]);
        self::field(__('Taux TVA par défaut (%)', 'yv-shop'), 'yv_shop_tax_default_rate', 'number');
    }

    private static function renderPayment(): void
    {
        self::field(__('Méthodes activées', 'yv-shop'), 'yv_shop_payment_methods_enabled', 'checkbox-list', [
            'bank_transfer' => __('Virement bancaire', 'yv-shop'),
            'stripe'        => __('Stripe (CB)', 'yv-shop'),
        ]);
        echo '<tr><th colspan="2"><h3>' . esc_html__('Virement bancaire', 'yv-shop') . '</h3></th></tr>';
        self::field(__('Bénéficiaire', 'yv-shop'), 'yv_shop_bank_account_name');
        self::field(__('IBAN', 'yv-shop'), 'yv_shop_bank_iban');
        self::field(__('BIC', 'yv-shop'), 'yv_shop_bank_bic');
        echo '<tr><th colspan="2"><h3>' . esc_html__('Stripe', 'yv-shop') . '</h3></th></tr>';
        self::field(__('Mode', 'yv-shop'), 'yv_shop_stripe_mode', 'select', ['test' => 'Test', 'live' => 'Live']);
        self::field(__('Test - clé publique', 'yv-shop'), 'yv_shop_stripe_test_publishable');
        self::field(__('Test - clé secrète', 'yv-shop'), 'yv_shop_stripe_test_secret', 'text', ['secret' => true]);
        self::field(__('Test - webhook secret', 'yv-shop'), 'yv_shop_stripe_test_webhook', 'text', ['secret' => true]);
        self::field(__('Live - clé publique', 'yv-shop'), 'yv_shop_stripe_live_publishable');
        self::field(__('Live - clé secrète', 'yv-shop'), 'yv_shop_stripe_live_secret', 'text', ['secret' => true]);
        self::field(__('Live - webhook secret', 'yv-shop'), 'yv_shop_stripe_live_webhook', 'text', ['secret' => true]);
    }

    private static function renderEmail(): void
    {
        self::field(__('Nom de l\'expéditeur', 'yv-shop'), 'yv_shop_email_from_name');
        self::field(__('Email de l\'expéditeur', 'yv-shop'), 'yv_shop_email_from_address', 'email');
    }
}
