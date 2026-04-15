<?php
namespace Youvanna\Shop\Frontend;

defined('ABSPATH') || exit;

final class Router
{
    private ?string $context = null; // 'archive', 'single', 'cart', 'checkout', 'category'
    private ?int $current_product_id = null;
    private ?int $current_category_id = null;

    public function register(): void
    {
        // Taxonomies now registered centrally in Plugin.php (needed in admin too)
        add_action('init', [$this, 'registerRewriteRules'], 10);
        add_filter('query_vars', [$this, 'queryVars']);
        add_action('template_redirect', [$this, 'detectContext'], 5);
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 20);
        add_filter('template_include', [$this, 'maybeOverrideTemplate'], 99);
        add_filter('document_title_parts', [$this, 'titleParts']);
        add_filter('pre_get_document_title', [$this, 'preGetTitle'], 999);
        add_filter('wpseo_title', [$this, 'yoastTitle'], 999);
        add_filter('wpseo_metadesc', [$this, 'yoastMetaDesc'], 999);
        add_filter('wpseo_opengraph_title', [$this, 'yoastTitle'], 999);
        add_filter('wpseo_opengraph_desc', [$this, 'yoastMetaDesc'], 999);
        add_filter('wpseo_canonical', [$this, 'yoastCanonical'], 999);
        add_action('wp', [$this, 'fixMainQuery'], 5);
        add_action('wp_head', [$this, 'cartSessionPreloader'], 1);

        (new Shortcodes())->register();
    }

    public function registerTaxonomies(): void
    {
        register_taxonomy('yv_category', null, [
            'label'        => __('Catégories produits', 'yv-shop'),
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'hierarchical' => true,
            'rewrite'      => false,
        ]);
        register_taxonomy('yv_tag', null, [
            'label'        => __('Étiquettes produits', 'yv-shop'),
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'hierarchical' => false,
            'rewrite'      => false,
        ]);
        // Register attribute taxonomies from registry
        $registry = (array) get_option('yv_shop_attributes_registry', []);
        foreach ($registry as $key => $label) {
            $tax = 'yv_attr_' . preg_replace('/[^a-z0-9_]/', '', (string) $key);
            register_taxonomy($tax, null, [
                'label'        => (string) $label,
                'public'       => false,
                'show_ui'      => true,
                'show_in_menu' => false,
                'hierarchical' => false,
                'rewrite'      => false,
            ]);
        }
    }

    public function registerRewriteRules(): void
    {
        $shop = trim((string) get_option('yv_shop_general_shop_slug', 'boutique'), '/');
        $product = trim((string) get_option('yv_shop_general_product_slug', 'produit'), '/');
        if (!$shop || !$product) {
            return;
        }
        // Archive
        add_rewrite_rule('^' . $shop . '/?$', 'index.php?yv_shop_archive=1', 'top');
        add_rewrite_rule('^' . $shop . '/page/([0-9]+)/?$', 'index.php?yv_shop_archive=1&paged=$matches[1]', 'top');
        // Category
        add_rewrite_rule('^' . $shop . '/categorie/([^/]+)/?$', 'index.php?yv_shop_archive=1&yv_category=$matches[1]', 'top');
        add_rewrite_rule('^' . $shop . '/categorie/([^/]+)/page/([0-9]+)/?$', 'index.php?yv_shop_archive=1&yv_category=$matches[1]&paged=$matches[2]', 'top');
        // Single
        add_rewrite_rule('^' . $product . '/([^/]+)/?$', 'index.php?yv_shop_product=$matches[1]', 'top');
    }

    public function queryVars(array $vars): array
    {
        $vars[] = 'yv_shop_archive';
        $vars[] = 'yv_shop_product';
        $vars[] = 'yv_category';
        return $vars;
    }

    public function detectContext(): void
    {
        if (get_query_var('yv_shop_archive')) {
            $this->context = 'archive';
            $cat_slug = get_query_var('yv_category');
            if ($cat_slug) {
                $term = get_term_by('slug', $cat_slug, 'yv_category');
                if ($term) {
                    $this->current_category_id = (int) $term->term_id;
                }
            }
            return;
        }
        $product_slug = get_query_var('yv_shop_product');
        if ($product_slug) {
            $this->context = 'single';
            $repo = new \Youvanna\Shop\Repositories\ProductRepository();
            $product = $repo->findBySlug((string) $product_slug);
            if (!$product) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                return;
            }
            $this->current_product_id = (int) $product->id;
            $GLOBALS['yv_shop_current_product'] = $product;
            return;
        }
        // Detect cart/checkout pages
        if (is_page()) {
            $page_id = get_queried_object_id();
            if ($page_id === (int) get_option('yv_shop_cart_page_id')) {
                $this->context = 'cart';
            } elseif ($page_id === (int) get_option('yv_shop_checkout_page_id')) {
                $this->context = 'checkout';
            }
        }
    }

    public function enqueue(): void
    {
        if (!$this->context) {
            return;
        }
        $base = YV_SHOP_URL . 'assets/dist/';
        $ver = YV_SHOP_VERSION;
        wp_enqueue_style('yv-shop-base', $base . 'shop.css', [], $ver);
        $script_data = [
            'rest_url'         => esc_url_raw(rest_url('yv-shop/v1/')),
            'nonce'            => wp_create_nonce('wp_rest'),
            'currency'         => \Youvanna\Shop\Helpers\Currency::code(),
            'currency_symbol'  => \Youvanna\Shop\Helpers\Currency::symbol(),
            'cart_page'        => get_permalink((int) get_option('yv_shop_cart_page_id')),
            'checkout_page'    => get_permalink((int) get_option('yv_shop_checkout_page_id')),
            'shop_page'        => home_url('/' . trim(get_option('yv_shop_general_shop_slug', 'boutique'), '/') . '/'),
            'context'          => $this->context,
            'i18n'             => [
                'add_to_cart'     => __('Ajouter au panier', 'yv-shop'),
                'added'           => __('Ajouté', 'yv-shop'),
                'remove'          => __('Supprimer', 'yv-shop'),
                'empty_cart'      => __('Votre panier est vide', 'yv-shop'),
                'continue'        => __('Continuer mes achats', 'yv-shop'),
                'checkout'        => __('Commander', 'yv-shop'),
                'subtotal'        => __('Sous-total', 'yv-shop'),
                'shipping'        => __('Livraison', 'yv-shop'),
                'tax'             => __('TVA', 'yv-shop'),
                'total'           => __('Total', 'yv-shop'),
            ],
        ];
        wp_register_script('yv-shop-store', $base . 'store.js', [], $ver, true);
        wp_localize_script('yv-shop-store', 'yvShop', $script_data);
        wp_enqueue_script('yv-shop-store');

        wp_enqueue_script('yv-shop-mini-cart', $base . 'mini-cart.js', ['yv-shop-store'], $ver, true);

        if ($this->context === 'archive') {
            wp_enqueue_script('yv-shop-archive', $base . 'shop.js', ['yv-shop-store'], $ver, true);
        }
        if ($this->context === 'single') {
            wp_enqueue_script('yv-shop-product', $base . 'product.js', ['yv-shop-store'], $ver, true);

            // Lens configurator assets only when the current product opts in
            if ($this->current_product_id && (bool) get_post_meta($this->current_product_id, '_yv_lens_configurable', true)) {
                wp_enqueue_style('yv-shop-lens', $base . 'lens-configurator.css', ['yv-shop-base'], $ver);
                wp_register_script('yv-shop-lens', $base . 'lens-configurator.js', ['yv-shop-store'], $ver, true);
                wp_localize_script('yv-shop-lens', 'yvShopLens', [
                    'i18n_recap'        => __('Récapitulatif', 'yv-shop'),
                    'i18n_invalid'      => __('Configuration incomplète : ', 'yv-shop'),
                    'i18n_pd_required'  => __('Renseigne ton écart pupillaire.', 'yv-shop'),
                    'i18n_manual'       => __('Saisie manuelle', 'yv-shop'),
                    'i18n_photo'        => __('Photo envoyée', 'yv-shop'),
                    'i18n_uploading'    => __('Envoi en cours...', 'yv-shop'),
                    'i18n_upload_error' => __('Échec : ', 'yv-shop'),
                    'i18n_no_pd_key'    => __('Mesure caméra non disponible.', 'yv-shop'),
                    'i18n_remove'       => __('Retirer', 'yv-shop'),
                ]);
                wp_enqueue_script('yv-shop-lens');
            }
        }
        if ($this->context === 'cart') {
            wp_enqueue_script('yv-shop-cart', $base . 'cart.js', ['yv-shop-store'], $ver, true);
        }
        if ($this->context === 'checkout') {
            wp_enqueue_script('yv-shop-checkout', $base . 'checkout.js', ['yv-shop-store'], $ver, true);
        }
    }

    public function maybeOverrideTemplate(string $template): string
    {
        if (!$this->context || $this->context === 'cart' || $this->context === 'checkout') {
            return $template;
        }
        $name = $this->context === 'archive' ? 'archive.php' : 'single.php';
        return $this->locate($name) ?? $template;
    }

    public function titleParts(array $parts): array
    {
        if ($this->context === 'archive') {
            $parts['title'] = $this->archiveTitle();
        } elseif ($this->context === 'single' && !empty($GLOBALS['yv_shop_current_product'])) {
            $parts['title'] = $GLOBALS['yv_shop_current_product']->name;
        }
        return $parts;
    }

    public function preGetTitle($title)
    {
        $computed = $this->computeFullTitle();
        return $computed ?: $title;
    }

    public function yoastTitle($title)
    {
        $computed = $this->computeFullTitle();
        return $computed ?: $title;
    }

    public function yoastMetaDesc($desc)
    {
        if ($this->context === 'archive') {
            $override = (string) get_option('yv_shop_archive_meta_desc', '');
            if ($override) return $override;
            return sprintf(__('Découvrez notre collection %s. Livraison rapide et retours gratuits.', 'yv-shop'), get_bloginfo('name'));
        }
        if ($this->context === 'single' && !empty($GLOBALS['yv_shop_current_product'])) {
            $p = $GLOBALS['yv_shop_current_product'];
            if (!empty($p->meta_description)) return (string) $p->meta_description;
            $excerpt = wp_strip_all_tags((string) ($p->short_description ?? $p->description ?? ''));
            if ($excerpt) return mb_substr($excerpt, 0, 155);
        }
        return $desc;
    }

    public function yoastCanonical($canonical)
    {
        if ($this->context === 'archive') {
            return home_url('/' . trim((string) get_option('yv_shop_general_shop_slug', 'boutique'), '/') . '/');
        }
        if ($this->context === 'single' && !empty($GLOBALS['yv_shop_current_product'])) {
            $slug = trim((string) get_option('yv_shop_general_product_slug', 'produit'), '/');
            return home_url('/' . $slug . '/' . $GLOBALS['yv_shop_current_product']->slug . '/');
        }
        return $canonical;
    }

    public function fixMainQuery(): void
    {
        if (!$this->context) return;
        global $wp_query;
        // Prevent WP from treating our shop URLs as search/404/blog
        if (isset($wp_query)) {
            $wp_query->is_search = false;
            $wp_query->is_home = false;
            $wp_query->is_404 = false;
            $wp_query->is_singular = false;
            $wp_query->is_page = false;
            $wp_query->is_archive = ($this->context === 'archive');
            $wp_query->is_single = ($this->context === 'single');
            $wp_query->set('s', '');
        }
    }

    private function archiveTitle(): string
    {
        if ($this->current_category_id) {
            $term = get_term($this->current_category_id, 'yv_category');
            if ($term && !is_wp_error($term)) return (string) $term->name;
        }
        return (string) (get_option('yv_shop_archive_title') ?: __('Boutique', 'yv-shop'));
    }

    private function computeFullTitle(): string
    {
        if (!$this->context) return '';
        $sep = ' - ';
        $site = get_bloginfo('name');
        if ($this->context === 'archive') {
            return $this->archiveTitle() . $sep . $site;
        }
        if ($this->context === 'single' && !empty($GLOBALS['yv_shop_current_product'])) {
            $p = $GLOBALS['yv_shop_current_product'];
            $seo = !empty($p->meta_title) ? (string) $p->meta_title : (string) $p->name;
            return $seo . $sep . $site;
        }
        return '';
    }

    public function cartSessionPreloader(): void
    {
        // Reserve mini-cart count slot to avoid CLS
        echo "<style>.yv-shop-mini-cart-count{display:inline-block;min-width:1.2em;text-align:center}</style>\n";
    }

    public function locate(string $template): ?string
    {
        $candidates = [
            get_stylesheet_directory() . '/yv-shop/' . $template,
            get_template_directory() . '/yv-shop/' . $template,
            YV_SHOP_DIR . '/templates/' . $template,
        ];
        foreach ($candidates as $p) {
            if (file_exists($p)) {
                return $p;
            }
        }
        return null;
    }

    public function context(): ?string { return $this->context; }
    public function currentProductId(): ?int { return $this->current_product_id; }
    public function currentCategoryId(): ?int { return $this->current_category_id; }
}
