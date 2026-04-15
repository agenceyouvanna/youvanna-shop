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
        register_setting('yv_shop_settings', 'yv_shop_stripe_test_secret', ['sanitize_callback' => [$this, 'encryptIfNeeded']]);
        register_setting('yv_shop_settings', 'yv_shop_stripe_live_secret', ['sanitize_callback' => [$this, 'encryptIfNeeded']]);
        register_setting('yv_shop_settings', 'yv_shop_stripe_test_webhook', ['sanitize_callback' => [$this, 'encryptIfNeeded']]);
        register_setting('yv_shop_settings', 'yv_shop_stripe_live_webhook', ['sanitize_callback' => [$this, 'encryptIfNeeded']]);
        register_setting('yv_shop_settings', 'yv_shop_payment_methods_enabled', ['sanitize_callback' => [$this, 'sanitizeArray']]);
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
        if (!is_array($value)) return [];
        return array_values(array_map('sanitize_key', $value));
    }

    public static function render(): void
    {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        $tabs = [
            'general' => ['label' => __('Général', 'yv-shop'), 'icon' => 'admin-generic'],
            'tax'     => ['label' => __('Taxes', 'yv-shop'), 'icon' => 'money-alt'],
            'payment' => ['label' => __('Paiements', 'yv-shop'), 'icon' => 'cart'],
            'email'   => ['label' => __('Emails', 'yv-shop'), 'icon' => 'email-alt'],
        ];

        echo '<div class="wrap yv-admin">';
        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title"><h1>' . esc_html__('Réglages', 'yv-shop') . '</h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html__('Configure ta boutique : devise, taxes, moyens de paiement, emails transactionnels.', 'yv-shop') . '</p></div>';
        echo '</div>';

        if (isset($_GET['settings-updated'])) echo '<div class="yv-admin-notice">' . esc_html__('Réglages enregistrés.', 'yv-shop') . '</div>';

        // Tabs
        echo '<div class="yv-tabs">';
        foreach ($tabs as $k => $cfg) {
            $url = add_query_arg(['page' => 'yv-shop-settings', 'tab' => $k], admin_url('admin.php'));
            $cls = $tab === $k ? ' is-active' : '';
            echo '<a href="' . esc_url($url) . '" class="yv-tab' . $cls . '"><span class="dashicons dashicons-' . esc_attr($cfg['icon']) . '"></span>' . esc_html($cfg['label']) . '</a>';
        }
        echo '</div>';

        echo '<form method="post" action="options.php">';
        settings_fields('yv_shop_settings');

        if ($tab === 'general') self::renderGeneral();
        elseif ($tab === 'tax') self::renderTax();
        elseif ($tab === 'payment') self::renderPayment();
        elseif ($tab === 'email') self::renderEmail();

        echo '<div style="margin-top:20px"><button type="submit" class="yv-btn yv-btn--primary">' . esc_html__('Enregistrer les modifications', 'yv-shop') . '</button></div>';
        echo '</form></div>';
    }

    private static function field(string $option, string $label, array $opts = []): void
    {
        $value = get_option($option, $opts['default'] ?? '');
        $type = $opts['type'] ?? 'text';
        $hint = $opts['hint'] ?? '';
        $placeholder = $opts['placeholder'] ?? '';
        $is_secret = !empty($opts['secret']);

        echo '<div class="yv-field">';
        echo '<label class="yv-field__label" for="' . esc_attr($option) . '">' . esc_html($label) . '</label>';

        if ($type === 'select') {
            echo '<select name="' . esc_attr($option) . '" id="' . esc_attr($option) . '" class="yv-select">';
            foreach (($opts['options'] ?? []) as $k => $v) {
                $sel = (string) $value === (string) $k ? ' selected' : '';
                echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($v) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'checkbox-list') {
            $vals = (array) get_option($option, []);
            echo '<div class="yv-checklist">';
            foreach (($opts['options'] ?? []) as $k => $v) {
                $checked = in_array((string) $k, array_map('strval', $vals), true) ? ' checked' : '';
                echo '<label><input type="checkbox" name="' . esc_attr($option) . '[]" value="' . esc_attr($k) . '"' . $checked . '> ' . esc_html($v) . '</label>';
            }
            echo '</div>';
        } else {
            $input_type = $is_secret ? 'password' : esc_attr($type);
            $display = $is_secret && $value && str_starts_with((string) $value, 'v1:') ? '' : esc_attr((string) $value);
            $ph = $is_secret && $value && str_starts_with((string) $value, 'v1:') ? '••••••••' : esc_attr((string) $placeholder);
            echo '<input type="' . $input_type . '" name="' . esc_attr($option) . '" id="' . esc_attr($option) . '" value="' . $display . '" placeholder="' . $ph . '" class="yv-input">';
            if ($is_secret && $value && str_starts_with((string) $value, 'v1:')) {
                echo '<small class="yv-field__hint" style="color:var(--yv-admin-success)">' . esc_html__('Secret chiffré enregistré. Saisir une nouvelle valeur pour le remplacer.', 'yv-shop') . '</small>';
            }
        }
        if ($hint) echo '<small class="yv-field__hint">' . wp_kses_post($hint) . '</small>';
        echo '</div>';
    }

    private static function renderGeneral(): void
    {
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Devise et format des prix', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-row-2col">';
        self::field('yv_shop_general_currency', __('Devise (ISO)', 'yv-shop'), ['hint' => __('Code ISO 4217. Ex : <code>EUR</code>, <code>USD</code>, <code>CHF</code>.', 'yv-shop'), 'placeholder' => 'EUR']);
        self::field('yv_shop_general_currency_symbol', __('Symbole affiché', 'yv-shop'), ['hint' => __('Ce que le client voit. Ex : <code>€</code>, <code>$</code>, <code>CHF</code>.', 'yv-shop'), 'placeholder' => '€']);
        echo '</div>';
        self::field('yv_shop_general_currency_position', __('Position du symbole', 'yv-shop'), [
            'type' => 'select',
            'options' => ['left' => '€99,99', 'left_space' => '€ 99,99', 'right' => '99,99€', 'right_space' => '99,99 €'],
            'hint' => __('Convention française : symbole à droite avec espace (99,99 €). Convention US : symbole à gauche ($99.99).', 'yv-shop'),
        ]);
        echo '<div class="yv-row-3col">';
        self::field('yv_shop_general_thousand_sep', __('Séparateur des milliers', 'yv-shop'), ['hint' => __('Ex : <code>1 000</code> (espace) ou <code>1,000</code> (virgule).', 'yv-shop'), 'placeholder' => ' ']);
        self::field('yv_shop_general_decimal_sep', __('Séparateur décimal', 'yv-shop'), ['hint' => __('Ex : <code>99,99</code> (virgule FR) ou <code>99.99</code> (point US).', 'yv-shop'), 'placeholder' => ',']);
        self::field('yv_shop_general_decimals', __('Nombre de décimales', 'yv-shop'), ['type' => 'number', 'hint' => __('2 pour la plupart des devises. 0 pour les devises sans décimales (JPY).', 'yv-shop'), 'placeholder' => '2']);
        echo '</div>';
        echo '</div></div>';

        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('URLs de la boutique', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-row-2col">';
        self::field('yv_shop_general_shop_slug', __('Slug de la boutique', 'yv-shop'), ['hint' => __('Apparaît dans l\'URL de la page catalogue. Ex : <code>boutique</code> → tonsite.com/boutique/', 'yv-shop'), 'placeholder' => 'boutique']);
        self::field('yv_shop_general_product_slug', __('Slug d\'un produit', 'yv-shop'), ['hint' => __('Base des URLs produits. Ex : <code>produit</code> → tonsite.com/produit/nom-du-produit/', 'yv-shop'), 'placeholder' => 'produit']);
        echo '</div>';
        echo '<div class="yv-row-2col">';
        self::field('yv_shop_general_country', __('Pays par défaut', 'yv-shop'), ['hint' => __('Code pays ISO 2 lettres. Ex : <code>FR</code>, <code>BE</code>, <code>CH</code>. Utilisé pour les calculs de taxe et de livraison.', 'yv-shop'), 'placeholder' => 'FR']);
        self::field('yv_shop_products_per_page', __('Produits par page', 'yv-shop'), ['type' => 'number', 'hint' => __('Pagination sur la page boutique. 12 ou 24 sont des valeurs courantes.', 'yv-shop'), 'placeholder' => '12']);
        echo '</div>';
        echo '</div></div>';
    }

    private static function renderTax(): void
    {
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Gestion de la TVA', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-infobox">';
        echo '<strong>' . esc_html__('Comment ça marche ?', 'yv-shop') . '</strong>';
        echo '<p>' . esc_html__('En France, les prix affichés au grand public doivent être TTC (obligation légale). Choisis "Prix saisis TTC" si tu rentres 100 et veux que le client voie 100 €. Choisis "Prix HT" si tu travailles surtout en B2B.', 'yv-shop') . '</p>';
        echo '</div>';
        self::field('yv_shop_tax_mode', __('Mode de saisie des prix', 'yv-shop'), [
            'type' => 'select',
            'options' => ['incl' => __('Prix TTC (taxes incluses)', 'yv-shop'), 'excl' => __('Prix HT (taxes en sus)', 'yv-shop')],
            'hint' => __('Les prix saisis dans les fiches produit sont-ils HT ou TTC ?', 'yv-shop'),
        ]);
        self::field('yv_shop_tax_default_rate', __('Taux de TVA par défaut (%)', 'yv-shop'), [
            'type' => 'number',
            'hint' => __('En France : <code>20</code> (taux normal), <code>10</code> (restauration), <code>5.5</code> (livres, alimentaire), <code>2.1</code> (médicaments remboursés). Ce taux s\'applique par défaut si le produit n\'a pas de classe de taxe spécifique.', 'yv-shop'),
            'placeholder' => '20',
        ]);
        echo '</div></div>';
    }

    private static function renderPayment(): void
    {
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Moyens de paiement activés', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        self::field('yv_shop_payment_methods_enabled', __('Méthodes proposées au checkout', 'yv-shop'), [
            'type' => 'checkbox-list',
            'options' => [
                'bank_transfer' => __('Virement bancaire (commande en attente jusqu\'au paiement)', 'yv-shop'),
                'stripe' => __('Carte bancaire via Stripe (paiement immédiat)', 'yv-shop'),
            ],
            'hint' => __('Au moins une méthode doit être activée pour pouvoir encaisser des commandes.', 'yv-shop'),
        ]);
        echo '</div></div>';

        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Virement bancaire', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<p style="margin-top:0;color:var(--yv-admin-text-muted)">' . esc_html__('Ces coordonnées sont envoyées au client dans l\'email de confirmation si le virement est choisi.', 'yv-shop') . '</p>';
        self::field('yv_shop_bank_account_name', __('Bénéficiaire', 'yv-shop'), ['hint' => __('Nom de ton entreprise ou raison sociale.', 'yv-shop')]);
        echo '<div class="yv-row-2col">';
        self::field('yv_shop_bank_iban', __('IBAN', 'yv-shop'), ['hint' => __('Format : <code>FR76 XXXX XXXX XXXX XXXX XXXX XXX</code>.', 'yv-shop')]);
        self::field('yv_shop_bank_bic', __('BIC / SWIFT', 'yv-shop'), ['hint' => __('Code identifiant la banque. 8 ou 11 caractères.', 'yv-shop')]);
        echo '</div>';
        echo '</div></div>';

        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Stripe (cartes bancaires)', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<div class="yv-infobox">';
        echo '<strong>' . esc_html__('Où trouver mes clés ?', 'yv-shop') . '</strong>';
        echo '<p>' . esc_html__('Sur dashboard.stripe.com → Développeurs → Clés API. Commence en mode Test, puis passe en Live quand tu es prêt.', 'yv-shop') . '</p>';
        echo '</div>';
        self::field('yv_shop_stripe_mode', __('Mode', 'yv-shop'), [
            'type' => 'select',
            'options' => ['test' => __('Test (aucun argent réel)', 'yv-shop'), 'live' => __('Live (production)', 'yv-shop')],
            'hint' => __('En mode Test, les cartes 4242 4242 4242 4242 fonctionnent. En Live, les vraies cartes sont débitées.', 'yv-shop'),
        ]);

        echo '<h3 style="margin-top:20px">' . esc_html__('Clés Test', 'yv-shop') . '</h3>';
        self::field('yv_shop_stripe_test_publishable', __('Clé publique Test', 'yv-shop'), ['hint' => __('Commence par <code>pk_test_</code>.', 'yv-shop'), 'placeholder' => 'pk_test_...']);
        self::field('yv_shop_stripe_test_secret', __('Clé secrète Test', 'yv-shop'), ['secret' => true, 'hint' => __('Commence par <code>sk_test_</code>. Elle est chiffrée en base de données.', 'yv-shop'), 'placeholder' => 'sk_test_...']);
        self::field('yv_shop_stripe_test_webhook', __('Webhook secret Test', 'yv-shop'), ['secret' => true, 'hint' => __('Commence par <code>whsec_</code>. À créer dans Stripe → Webhooks pour l\'URL <code>/wp-json/yv-shop/v1/stripe/webhook</code>.', 'yv-shop'), 'placeholder' => 'whsec_...']);

        echo '<h3 style="margin-top:20px">' . esc_html__('Clés Live', 'yv-shop') . '</h3>';
        self::field('yv_shop_stripe_live_publishable', __('Clé publique Live', 'yv-shop'), ['hint' => __('Commence par <code>pk_live_</code>.', 'yv-shop'), 'placeholder' => 'pk_live_...']);
        self::field('yv_shop_stripe_live_secret', __('Clé secrète Live', 'yv-shop'), ['secret' => true, 'hint' => __('Commence par <code>sk_live_</code>. Chiffrée en base.', 'yv-shop'), 'placeholder' => 'sk_live_...']);
        self::field('yv_shop_stripe_live_webhook', __('Webhook secret Live', 'yv-shop'), ['secret' => true, 'hint' => __('Commence par <code>whsec_</code>. Créer un webhook séparé pour la prod.', 'yv-shop'), 'placeholder' => 'whsec_...']);
        echo '</div></div>';
    }

    private static function renderEmail(): void
    {
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Expéditeur des emails transactionnels', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<p style="margin-top:0;color:var(--yv-admin-text-muted)">' . esc_html__('Nom et adresse affichés comme expéditeur dans les emails envoyés aux clients (confirmation de commande, expédition, etc.).', 'yv-shop') . '</p>';
        echo '<div class="yv-row-2col">';
        self::field('yv_shop_email_from_name', __('Nom de l\'expéditeur', 'yv-shop'), ['hint' => __('Ex : <code>Boutique Maison Mira</code>. Ce que le client voit dans sa boite mail.', 'yv-shop')]);
        self::field('yv_shop_email_from_address', __('Email de l\'expéditeur', 'yv-shop'), ['type' => 'email', 'hint' => __('Ex : <code>contact@tonsite.com</code>. Doit être une adresse de ton domaine pour éviter les spams.', 'yv-shop')]);
        echo '</div>';
        echo '</div></div>';
    }
}
