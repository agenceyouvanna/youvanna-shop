<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Helpers\Currency;
use Youvanna\Shop\Models\Product;
use Youvanna\Shop\Models\Variation;
use Youvanna\Shop\Repositories\ProductRepository;
use Youvanna\Shop\Repositories\VariationRepository;

defined('ABSPATH') || exit;

final class ProductEditor
{
    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_products')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        wp_enqueue_media();

        $repo = new ProductRepository();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $product = $id ? $repo->find($id) : new Product();

        if (!$product) {
            echo '<div class="wrap yv-admin"><div class="yv-admin-notice yv-admin-notice--error">' . esc_html__('Produit introuvable.', 'yv-shop') . '</div></div>';
            return;
        }

        if (!empty($_POST['yv_shop_product_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yv_shop_product_nonce'])), 'yv_shop_save_product')) {
            self::saveFromRequest($product, $repo);
            wp_safe_redirect(admin_url('admin.php?page=yv-shop-products&action=edit&id=' . (int) $product->id . '&saved=1'));
            exit;
        }

        echo '<div class="wrap yv-admin">';
        self::renderHeader($id, $product);

        if (isset($_GET['saved'])) {
            echo '<div class="yv-admin-notice">' . esc_html__('Produit enregistré avec succès.', 'yv-shop') . '</div>';
        }

        echo '<form method="post" class="yv-admin-wrap">';
        wp_nonce_field('yv_shop_save_product', 'yv_shop_product_nonce');

        echo '<div class="yv-grid yv-grid--main-side">';
        echo '<div>';
        self::renderTabs($product);
        echo '</div>';
        echo '<aside class="yv-sidebar">';
        self::renderSidebar($product);
        echo '</aside>';
        echo '</div>';

        echo '</form></div>';
    }

    private static function renderHeader(int $id, Product $product): void
    {
        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title">';
        echo '<h1>' . esc_html($id ? sprintf(__('Modifier : %s', 'yv-shop'), $product->name ?: '#'.$id) : __('Nouveau produit', 'yv-shop')) . '</h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html__('Crée ou modifie un produit de ta boutique. Navigue entre les onglets pour configurer tous les aspects.', 'yv-shop') . '</p>';
        echo '</div>';
        echo '<div class="yv-admin-header__actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=yv-shop-products')) . '" class="yv-btn yv-btn--ghost">' . esc_html__('← Retour à la liste', 'yv-shop') . '</a>';
        if ($id && $product->slug) {
            echo '<a href="' . esc_url($product->permalink()) . '" target="_blank" class="yv-btn yv-btn--secondary">' . esc_html__('Voir sur le site ↗', 'yv-shop') . '</a>';
        }
        echo '<button type="submit" form="" class="yv-btn yv-btn--primary" onclick="this.closest(\'.wrap\').querySelector(\'form\').submit();return false;">' . esc_html($id ? __('Enregistrer', 'yv-shop') : __('Créer le produit', 'yv-shop')) . '</button>';
        echo '</div></div>';
    }

    private static function renderTabs(Product $product): void
    {
        $tabs = [
            'general'    => ['icon' => 'dashicons-edit', 'label' => __('Général', 'yv-shop')],
            'pricing'    => ['icon' => 'dashicons-money-alt', 'label' => __('Prix', 'yv-shop')],
            'stock'      => ['icon' => 'dashicons-archive', 'label' => __('Stock', 'yv-shop')],
            'media'      => ['icon' => 'dashicons-format-gallery', 'label' => __('Images', 'yv-shop')],
            'taxonomy'   => ['icon' => 'dashicons-category', 'label' => __('Catégories', 'yv-shop')],
            'variations' => ['icon' => 'dashicons-grid-view', 'label' => __('Variations', 'yv-shop')],
            'shipping'   => ['icon' => 'dashicons-cart', 'label' => __('Livraison', 'yv-shop')],
            'seo'        => ['icon' => 'dashicons-search', 'label' => __('SEO', 'yv-shop')],
        ];
        echo '<div class="yv-tabs" role="tablist">';
        foreach ($tabs as $key => $t) {
            echo '<button type="button" class="yv-tabs__btn" data-tab="' . esc_attr($key) . '"><span class="dashicons ' . esc_attr($t['icon']) . '"></span>' . esc_html($t['label']) . '</button>';
        }
        echo '</div>';

        self::panelGeneral($product);
        self::panelPricing($product);
        self::panelStock($product);
        self::panelMedia($product);
        self::panelTaxonomy($product);
        self::panelVariations($product);
        self::panelShipping($product);
        self::panelSeo($product);
    }

    private static function panelGeneral(Product $product): void
    {
        echo '<div class="yv-tab-panel" data-panel="general">';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Informations générales', 'yv-shop') . '</h2><span class="yv-card__hint">' . esc_html__('Le nom et la description visibles par le client.', 'yv-shop') . '</span></div><div class="yv-card__body">';

        self::field('name', __('Nom du produit', 'yv-shop'), [
            'type' => 'text',
            'value' => $product->name,
            'required' => true,
            'hint' => __('Apparaît en titre sur la fiche produit et dans les listings. 60 caractères max recommandés pour le SEO.', 'yv-shop'),
            'attrs' => ['data-slug-source' => ''],
        ]);

        self::field('slug', __('URL du produit (slug)', 'yv-shop'), [
            'type' => 'text',
            'value' => $product->slug,
            'hint' => sprintf(__('Le bout d\'adresse qui apparaît dans l\'URL : %s<code>[slug]</code>/. Laisse vide pour auto-générer depuis le nom.', 'yv-shop'), home_url('/' . trim((string) get_option('yv_shop_general_product_slug', 'produit'), '/') . '/')),
            'prefix' => '/' . trim((string) get_option('yv_shop_general_product_slug', 'produit'), '/') . '/',
            'attrs' => ['data-slug-target' => ''],
        ]);

        self::field('sku', __('SKU (référence interne)', 'yv-shop'), [
            'type' => 'text',
            'value' => $product->sku,
            'hint' => __('Code unique pour identifier le produit dans tes stocks. Exemple : POLO-BLEU-M. Laisse vide si tu n\'en as pas.', 'yv-shop'),
        ]);

        self::field('short_description', __('Description courte', 'yv-shop'), [
            'type' => 'textarea',
            'value' => (string) $product->short_description,
            'rows' => 3,
            'hint' => __('Résumé en 1 à 2 phrases. Apparaît à côté du prix sur la fiche produit. 160 caractères max.', 'yv-shop'),
        ]);

        self::field('description', __('Description complète', 'yv-shop'), [
            'type' => 'textarea',
            'value' => (string) $product->description,
            'rows' => 10,
            'hint' => __('HTML autorisé (paragraphes, listes, gras, italique). Affiché en dessous de l\'image produit.', 'yv-shop'),
        ]);

        echo '</div></div>';
        echo '</div>';
    }

    private static function panelPricing(Product $product): void
    {
        echo '<div class="yv-tab-panel" data-panel="pricing">';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Prix et promotions', 'yv-shop') . '</h2></div><div class="yv-card__body">';

        $sym = Currency::symbol();

        echo '<div class="yv-row-2col">';
        self::field('price', __('Prix de vente', 'yv-shop'), [
            'type' => 'number', 'step' => '0.01', 'min' => '0',
            'value' => (string) $product->price,
            'required' => true,
            'suffix' => $sym,
            'hint' => __('Prix TTC affiché au client avant toute promotion.', 'yv-shop'),
        ]);
        self::field('cost_price', __('Prix de revient', 'yv-shop'), [
            'type' => 'number', 'step' => '0.01', 'min' => '0',
            'value' => $product->cost_price !== null ? (string) $product->cost_price : '',
            'suffix' => $sym,
            'hint' => __('Ce que te coûte le produit (achat, fabrication...). Sert aux calculs de marge. Jamais visible par le client.', 'yv-shop'),
        ]);
        echo '</div>';

        echo '<div class="yv-row-2col">';
        self::field('sale_price', __('Prix promo', 'yv-shop'), [
            'type' => 'number', 'step' => '0.01', 'min' => '0',
            'value' => $product->sale_price !== null ? (string) $product->sale_price : '',
            'suffix' => $sym,
            'hint' => __('Si renseigné, un bandeau "Promo" s\'affiche et le prix est barré. Laisse vide pour désactiver la promo.', 'yv-shop'),
        ]);
        echo '<div></div>';
        echo '</div>';

        echo '<div class="yv-row-2col">';
        self::field('sale_from', __('Début de la promo', 'yv-shop'), [
            'type' => 'datetime-local',
            'value' => $product->sale_from ? str_replace(' ', 'T', substr((string) $product->sale_from, 0, 16)) : '',
            'hint' => __('Optionnel. La promo ne s\'applique qu\'à partir de cette date.', 'yv-shop'),
        ]);
        self::field('sale_to', __('Fin de la promo', 'yv-shop'), [
            'type' => 'datetime-local',
            'value' => $product->sale_to ? str_replace(' ', 'T', substr((string) $product->sale_to, 0, 16)) : '',
            'hint' => __('Optionnel. Après cette date, le prix normal revient automatiquement.', 'yv-shop'),
        ]);
        echo '</div>';

        echo '<hr style="margin:20px 0;border:0;border-top:1px solid var(--yv-admin-border)">';

        echo '<div class="yv-row-2col">';
        self::field('tax_status', __('TVA', 'yv-shop'), [
            'type' => 'select',
            'value' => $product->tax_status,
            'options' => [
                'taxable' => __('Soumis à TVA (20% par défaut)', 'yv-shop'),
                'none' => __('Exonéré de TVA', 'yv-shop'),
            ],
            'hint' => __('La TVA est ajoutée au prix lors du checkout si "Soumis à TVA".', 'yv-shop'),
        ]);
        self::field('tax_class', __('Taux de TVA', 'yv-shop'), [
            'type' => 'select',
            'value' => $product->tax_class,
            'options' => [
                'standard' => __('Taux normal (20%)', 'yv-shop'),
                'reduced' => __('Taux réduit (10%)', 'yv-shop'),
                'super-reduced' => __('Taux super réduit (5.5%)', 'yv-shop'),
                'zero' => __('Taux zéro (0%)', 'yv-shop'),
            ],
            'hint' => __('À adapter selon le type de produit (alimentaire, livre, etc.).', 'yv-shop'),
        ]);
        echo '</div>';

        echo '</div></div>';
        echo '</div>';
    }

    private static function panelStock(Product $product): void
    {
        echo '<div class="yv-tab-panel" data-panel="stock">';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Gestion du stock', 'yv-shop') . '</h2></div><div class="yv-card__body">';

        echo '<div class="yv-infobox">' . wp_kses(__('<strong>Décoche</strong> "Gérer le stock" si tu as toujours le produit en quantité illimitée (dropshipping, services, fichiers numériques). <strong>Coche</strong> si tu dois suivre une quantité précise (la boutique refuse les commandes quand stock = 0).', 'yv-shop'), ['strong' => []]) . '</div>';

        self::switchField('manage_stock', __('Gérer le stock de ce produit', 'yv-shop'), $product->manage_stock, __('Si activé, la quantité est décrémentée à chaque commande payée.', 'yv-shop'));

        echo '<div class="yv-row-2col">';
        self::field('stock_qty', __('Quantité en stock', 'yv-shop'), [
            'type' => 'number', 'min' => '0', 'step' => '1',
            'value' => (string) $product->stock_qty,
            'hint' => __('Nombre d\'unités disponibles. Ignoré si "Gérer le stock" est décoché.', 'yv-shop'),
        ]);
        self::field('stock_status', __('Statut du stock', 'yv-shop'), [
            'type' => 'select',
            'value' => $product->stock_status,
            'options' => [
                'instock' => __('En stock', 'yv-shop'),
                'outofstock' => __('Rupture de stock', 'yv-shop'),
                'onbackorder' => __('Sur commande (délai)', 'yv-shop'),
            ],
            'hint' => __('Affiche un libellé au client. "Sur commande" permet d\'accepter des commandes même sans stock.', 'yv-shop'),
        ]);
        echo '</div>';

        echo '<div class="yv-row-2col">';
        self::field('backorders', __('Précommandes', 'yv-shop'), [
            'type' => 'select',
            'value' => $product->backorders,
            'options' => [
                'no' => __('Non autorisées', 'yv-shop'),
                'notify' => __('Autorisées, notifier le client', 'yv-shop'),
                'yes' => __('Autorisées sans notification', 'yv-shop'),
            ],
            'hint' => __('Permet au client de commander même si le stock est à 0.', 'yv-shop'),
        ]);
        echo '</div>';

        echo '</div></div>';
        echo '</div>';
    }

    private static function panelMedia(Product $product): void
    {
        echo '<div class="yv-tab-panel" data-panel="media">';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Image principale', 'yv-shop') . '</h2><span class="yv-card__hint">' . esc_html__('Format carré recommandé, 1200×1200px ou plus.', 'yv-shop') . '</span></div><div class="yv-card__body">';
        self::imagePicker('image_id', $product->image_id);
        echo '</div></div>';

        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Galerie (images additionnelles)', 'yv-shop') . '</h2><span class="yv-card__hint">' . esc_html__('Jusqu\'à 8 images. Affichées sous forme de miniatures cliquables.', 'yv-shop') . '</span></div><div class="yv-card__body">';
        self::galleryPicker('gallery_ids', $product->gallery_ids);
        echo '</div></div>';
        echo '</div>';
    }

    private static function panelTaxonomy(Product $product): void
    {
        echo '<div class="yv-tab-panel" data-panel="taxonomy">';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Catégories', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<p class="yv-field__hint" style="margin-top:0;margin-bottom:12px">' . esc_html__('Un produit peut appartenir à plusieurs catégories. Gère les catégories via le menu Produits → Catégories.', 'yv-shop') . '</p>';
        self::checklist('categories', $product->category_ids, 'yv_category');
        echo '</div></div>';
        echo '</div>';
    }

    private static function panelVariations(Product $product): void
    {
        echo '<div class="yv-tab-panel" data-panel="variations">';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Variations (tailles, couleurs...)', 'yv-shop') . '</h2><span class="yv-card__hint">' . esc_html__('Chaque variation a son propre prix / stock / image.', 'yv-shop') . '</span></div><div class="yv-card__body">';

        echo '<div class="yv-infobox">' . esc_html__('Exemple : un t-shirt existe en S/M/L et en rouge/bleu. Crée une variation par combinaison avec SKU, prix et stock propres. Laisse vide si ton produit n\'a pas de déclinaison.', 'yv-shop') . '</div>';

        $variations = [];
        if ($product->id) {
            $variations = (new VariationRepository())->forProduct((int) $product->id, false);
        }

        echo '<div data-repeater class="yv-repeater-wrap">';
        echo '<div class="yv-repeater" data-repeater-body>';
        echo '<div class="yv-repeater__row" style="display:grid;grid-template-columns:1fr 140px 140px 100px 40px;font-size:11px;color:var(--yv-admin-text-muted);text-transform:uppercase;letter-spacing:0.04em;padding-left:12px;padding-right:12px;background:transparent;border:0">';
        echo '<div>' . esc_html__('Nom / attribut (ex. Taille M)', 'yv-shop') . '</div>';
        echo '<div>' . esc_html__('SKU', 'yv-shop') . '</div>';
        echo '<div>' . esc_html__('Prix', 'yv-shop') . '</div>';
        echo '<div>' . esc_html__('Stock', 'yv-shop') . '</div>';
        echo '<div></div>';
        echo '</div>';
        foreach ($variations as $v) {
            $lbl = (string) ($v->attributes['_label'] ?? self::labelFromAttrs($v->attributes));
            self::variationRow((int) $v->id, $lbl, $v->sku, $v->price, $v->stock_qty, $v->enabled);
        }
        echo '</div>';
        echo '<button type="button" class="yv-repeater__add" data-repeater-add><span class="dashicons dashicons-plus-alt2"></span>' . esc_html__('Ajouter une variation', 'yv-shop') . '</button>';

        echo '<template data-repeater-tpl>';
        self::variationRow(0, '', '', 0.0, 0, true, '__INDEX__');
        echo '</template>';
        echo '</div>';

        echo '</div></div>';
        echo '</div>';
    }

    private static function labelFromAttrs(array $attrs): string
    {
        unset($attrs['_label']);
        if (!$attrs) return '';
        $parts = [];
        foreach ($attrs as $k => $v) $parts[] = is_array($v) ? implode('/', $v) : (string) $v;
        return implode(' / ', $parts);
    }

    private static function variationRow(int $id, string $label, string $sku, float $price, int $stock, bool $enabled, $idx = null): void
    {
        $idx = $idx ?? (string) $id;
        $prefix = 'variations[' . $idx . ']';
        echo '<div class="yv-repeater__row" data-repeater-row>';
        echo '<input type="hidden" name="' . esc_attr($prefix . '[id]') . '" value="' . esc_attr((string) $id) . '">';
        echo '<input type="hidden" name="' . esc_attr($prefix . '[enabled]') . '" value="' . ($enabled ? '1' : '0') . '">';
        echo '<input type="text" name="' . esc_attr($prefix . '[label]') . '" value="' . esc_attr($label) . '" class="yv-input" placeholder="' . esc_attr__('Taille M / Bleu', 'yv-shop') . '">';
        echo '<input type="text" name="' . esc_attr($prefix . '[sku]') . '" value="' . esc_attr($sku) . '" class="yv-input" placeholder="SKU">';
        echo '<input type="number" step="0.01" min="0" name="' . esc_attr($prefix . '[price]') . '" value="' . esc_attr((string) $price) . '" class="yv-input" placeholder="' . esc_attr(Currency::symbol()) . '">';
        echo '<input type="number" min="0" step="1" name="' . esc_attr($prefix . '[stock]') . '" value="' . esc_attr((string) $stock) . '" class="yv-input" placeholder="0">';
        echo '<button type="button" class="yv-repeater__remove" data-repeater-remove title="' . esc_attr__('Supprimer', 'yv-shop') . '"><span class="dashicons dashicons-trash"></span></button>';
        echo '</div>';
    }

    private static function panelShipping(Product $product): void
    {
        echo '<div class="yv-tab-panel" data-panel="shipping">';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Livraison et dimensions', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<p class="yv-field__hint" style="margin-top:0;margin-bottom:16px">' . esc_html__('Utilisé pour calculer les frais de port basés sur le poids ou le volume. Laisse vide si non applicable.', 'yv-shop') . '</p>';

        echo '<div class="yv-row-2col">';
        self::field('weight', __('Poids', 'yv-shop'), [
            'type' => 'number', 'step' => '0.001', 'min' => '0',
            'value' => $product->weight !== null ? (string) $product->weight : '',
            'suffix' => 'kg',
            'hint' => __('Poids total emballé. Utilisé par certains modes de livraison.', 'yv-shop'),
        ]);
        echo '<div></div>';
        echo '</div>';

        echo '<div class="yv-row-3col">';
        self::field('length', __('Longueur', 'yv-shop'), [
            'type' => 'number', 'step' => '0.1', 'min' => '0',
            'value' => $product->length !== null ? (string) $product->length : '',
            'suffix' => 'cm',
        ]);
        self::field('width', __('Largeur', 'yv-shop'), [
            'type' => 'number', 'step' => '0.1', 'min' => '0',
            'value' => $product->width !== null ? (string) $product->width : '',
            'suffix' => 'cm',
        ]);
        self::field('height', __('Hauteur', 'yv-shop'), [
            'type' => 'number', 'step' => '0.1', 'min' => '0',
            'value' => $product->height !== null ? (string) $product->height : '',
            'suffix' => 'cm',
        ]);
        echo '</div>';
        echo '</div></div>';
        echo '</div>';
    }

    private static function panelSeo(Product $product): void
    {
        echo '<div class="yv-tab-panel" data-panel="seo">';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('SEO (Google, réseaux sociaux)', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<p class="yv-field__hint" style="margin-top:0;margin-bottom:16px">' . esc_html__('Ces champs remplacent le titre et la meta description générés automatiquement. Laisse vide pour utiliser le nom du produit et sa description courte.', 'yv-shop') . '</p>';

        $meta_title = (string) get_post_meta_first_or($product->id, '_yv_meta_title', '');
        $meta_desc = (string) get_post_meta_first_or($product->id, '_yv_meta_description', '');

        $site_name = (string) get_bloginfo('name');
        $fallback_title_base = $product->name !== '' ? $product->name : __('Nouveau produit', 'yv-shop');
        $fallback_title = $fallback_title_base . ($site_name !== '' ? ' - ' . $site_name : '');
        $desc_raw = (string) ($product->short_description ?: $product->description);
        $fallback_desc = trim(wp_strip_all_tags($desc_raw));
        if (mb_strlen($fallback_desc) > 155) {
            $fallback_desc = mb_substr($fallback_desc, 0, 152) . '...';
        }

        $slug = $product->slug !== '' ? $product->slug : sanitize_title($product->name);
        $product_base = trim((string) get_option('yv_shop_general_product_slug', 'produit'), '/');
        $url = home_url('/' . $product_base . '/' . $slug . '/');

        self::field('meta_title', __('Titre SEO', 'yv-shop'), [
            'type' => 'text', 'value' => $meta_title,
            'placeholder' => $fallback_title,
            'hint' => __('60 caractères max. S\'affiche dans l\'onglet du navigateur et dans les résultats Google. Laisse vide pour utiliser le nom du produit suivi du nom de la boutique.', 'yv-shop'),
            'attrs' => ['data-seo-title-input' => '', 'maxlength' => '70'],
        ]);
        self::field('meta_description', __('Meta description', 'yv-shop'), [
            'type' => 'textarea', 'rows' => 3, 'value' => $meta_desc,
            'placeholder' => $fallback_desc !== '' ? $fallback_desc : __('Décris ton produit en 1 à 2 phrases...', 'yv-shop'),
            'hint' => __('155 caractères max. S\'affiche sous le titre dans les résultats Google. Laisse vide pour utiliser la description courte du produit.', 'yv-shop'),
            'attrs' => ['data-seo-desc-input' => '', 'maxlength' => '200'],
        ]);

        // Live Google SERP preview
        echo '<div class="yv-seo-preview" data-seo-preview';
        echo ' data-fallback-title="' . esc_attr($fallback_title) . '"';
        echo ' data-fallback-desc="' . esc_attr($fallback_desc) . '"';
        echo ' data-url="' . esc_attr($url) . '">';
        echo '<div class="yv-seo-preview__label">' . esc_html__('Aperçu dans Google', 'yv-shop') . '</div>';
        echo '<div class="yv-seo-preview__card">';
        echo '<div class="yv-seo-preview__url">' . esc_html($url) . '</div>';
        echo '<div class="yv-seo-preview__title" data-seo-title>' . esc_html($meta_title !== '' ? $meta_title : $fallback_title) . '</div>';
        echo '<div class="yv-seo-preview__desc" data-seo-desc>' . esc_html($meta_desc !== '' ? $meta_desc : $fallback_desc) . '</div>';
        echo '</div></div>';

        echo '</div></div>';
        echo '</div>';
    }

    private static function renderSidebar(Product $product): void
    {
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Publication', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        self::field('status', __('Statut', 'yv-shop'), [
            'type' => 'select',
            'value' => $product->status,
            'options' => [
                'draft' => __('Brouillon (invisible)', 'yv-shop'),
                'published' => __('Publié (visible)', 'yv-shop'),
                'private' => __('Privé (connectés uniquement)', 'yv-shop'),
            ],
            'hint' => __('Seuls les produits "Publié" apparaissent sur la boutique.', 'yv-shop'),
        ]);
        self::switchField('featured', __('Produit mis en avant', 'yv-shop'), $product->featured, __('Les produits mis en avant peuvent être affichés en carrousel d\'accueil.', 'yv-shop'));
        self::field('sort_order', __('Ordre d\'affichage', 'yv-shop'), [
            'type' => 'number', 'step' => '1',
            'value' => (string) $product->sort_order,
            'hint' => __('Plus petit = apparaît en premier. Laisse à 0 si pas d\'ordre particulier.', 'yv-shop'),
        ]);
        echo '<button type="submit" class="yv-btn yv-btn--primary" style="width:100%;justify-content:center;margin-top:6px">' . esc_html($product->id ? __('Enregistrer les modifications', 'yv-shop') : __('Créer le produit', 'yv-shop')) . '</button>';
        if ($product->id) {
            echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=yv-shop-products&action=delete&id=' . (int) $product->id), 'yv_shop_delete_product_' . (int) $product->id)) . '" class="yv-btn yv-btn--danger" data-confirm="' . esc_attr__('Supprimer définitivement ce produit ?', 'yv-shop') . '" style="width:100%;justify-content:center;margin-top:8px">' . esc_html__('Supprimer le produit', 'yv-shop') . '</a>';
        }
        echo '</div></div>';

        if ($product->id) {
            echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Statistiques', 'yv-shop') . '</h2></div><div class="yv-card__body">';
            echo '<div class="yv-field"><div class="yv-field__label">' . esc_html__('Ventes', 'yv-shop') . '</div><div style="font-size:20px;font-weight:700">' . (int) $product->sales_count . '</div></div>';
            echo '<div class="yv-field"><div class="yv-field__label">' . esc_html__('Note moyenne', 'yv-shop') . '</div><div style="font-size:15px"><span class="yv-stars">' . str_repeat('★', (int) round($product->rating_avg)) . str_repeat('☆', 5 - (int) round($product->rating_avg)) . '</span> <small>(' . (int) $product->rating_count . ')</small></div></div>';
            echo '</div></div>';
        }
    }

    // ---- Field renderers ----

    private static function field(string $name, string $label, array $opts): void
    {
        $type = $opts['type'] ?? 'text';
        $value = (string) ($opts['value'] ?? '');
        $required = !empty($opts['required']);
        $hint = $opts['hint'] ?? '';
        $placeholder = (string) ($opts['placeholder'] ?? '');
        $attrs = $opts['attrs'] ?? [];
        $attrStr = '';
        foreach ($attrs as $k => $v) $attrStr .= ' ' . $k . '="' . esc_attr((string) $v) . '"';
        $phAttr = $placeholder !== '' ? ' placeholder="' . esc_attr($placeholder) . '"' : '';

        echo '<div class="yv-field">';
        echo '<label class="yv-field__label" for="yv_' . esc_attr($name) . '">' . esc_html($label) . ($required ? ' <span class="yv-required">*</span>' : '') . '</label>';

        $prefix = $opts['prefix'] ?? null;
        $suffix = $opts['suffix'] ?? null;

        if ($type === 'textarea') {
            echo '<textarea id="yv_' . esc_attr($name) . '" name="' . esc_attr($name) . '" rows="' . (int) ($opts['rows'] ?? 4) . '" class="yv-textarea"' . ($required ? ' required' : '') . $phAttr . $attrStr . '>' . esc_textarea($value) . '</textarea>';
        } elseif ($type === 'select') {
            echo '<select id="yv_' . esc_attr($name) . '" name="' . esc_attr($name) . '" class="yv-select"' . $attrStr . '>';
            foreach (($opts['options'] ?? []) as $k => $v) {
                echo '<option value="' . esc_attr((string) $k) . '"' . ($value === (string) $k ? ' selected' : '') . '>' . esc_html($v) . '</option>';
            }
            echo '</select>';
        } else {
            $stepAttr = isset($opts['step']) ? ' step="' . esc_attr($opts['step']) . '"' : '';
            $minAttr = isset($opts['min']) ? ' min="' . esc_attr($opts['min']) . '"' : '';
            $input = '<input type="' . esc_attr($type) . '" id="yv_' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="yv-input"' . $stepAttr . $minAttr . ($required ? ' required' : '') . $phAttr . $attrStr . '>';
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

    private static function imagePicker(string $name, ?int $image_id): void
    {
        $url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
        echo '<div class="yv-image-picker" data-image-picker>';
        echo '<div class="yv-image-picker__preview" data-preview data-empty-label="' . esc_attr__('Aucune image', 'yv-shop') . '">';
        if ($url) echo '<img src="' . esc_url($url) . '" alt="">';
        else echo esc_html__('Aucune image', 'yv-shop');
        echo '</div>';
        echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) ($image_id ?? '')) . '">';
        echo '<div class="yv-image-picker__actions">';
        echo '<button type="button" class="yv-btn yv-btn--secondary" data-pick>' . esc_html__('Choisir une image', 'yv-shop') . '</button>';
        echo '<button type="button" class="yv-btn yv-btn--ghost" data-clear>' . esc_html__('Retirer', 'yv-shop') . '</button>';
        echo '</div></div>';
    }

    private static function galleryPicker(string $name, array $ids): void
    {
        echo '<div class="yv-gallery-wrap" data-gallery-picker>';
        echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr(implode(',', array_map('intval', $ids))) . '">';
        echo '<div class="yv-gallery" data-gallery-grid>';
        foreach ($ids as $id) {
            $src = wp_get_attachment_image_url((int) $id, 'thumbnail');
            if ($src) {
                echo '<div class="yv-gallery__item" data-id="' . (int) $id . '"><img src="' . esc_url($src) . '" alt=""><button type="button" data-remove>&times;</button></div>';
            }
        }
        echo '<button type="button" class="yv-gallery__add" data-gallery-add>+</button>';
        echo '</div></div>';
    }

    private static function checklist(string $name, array $selected, string $taxonomy): void
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        echo '<div class="yv-checklist">';
        if (is_wp_error($terms) || empty($terms)) {
            echo '<em style="color:var(--yv-admin-text-muted);font-size:13px">' . esc_html__('Aucune catégorie. Crée-en via Produits → Catégories.', 'yv-shop') . '</em>';
        } else {
            foreach ($terms as $t) {
                echo '<label><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . (int) $t->term_id . '"' . (in_array((int) $t->term_id, $selected, true) ? ' checked' : '') . '> ' . esc_html($t->name) . '</label>';
            }
        }
        echo '</div>';
    }

    // ---- Save ----

    private static function saveFromRequest(Product $product, ProductRepository $repo): void
    {
        $post = wp_unslash($_POST);
        $product->name = sanitize_text_field($post['name'] ?? '');
        $slug = sanitize_title($post['slug'] ?? '');
        $product->slug = $slug ?: sanitize_title($product->name);
        $product->sku = sanitize_text_field($post['sku'] ?? '');
        $product->status = sanitize_key($post['status'] ?? 'draft');
        $product->featured = !empty($post['featured']);
        $product->short_description = wp_kses_post($post['short_description'] ?? '');
        $product->description = wp_kses_post($post['description'] ?? '');
        $product->price = (float) ($post['price'] ?? 0);
        $product->sale_price = isset($post['sale_price']) && $post['sale_price'] !== '' ? (float) $post['sale_price'] : null;
        $product->sale_from = !empty($post['sale_from']) ? str_replace('T', ' ', (string) $post['sale_from']) . ':00' : null;
        $product->sale_to = !empty($post['sale_to']) ? str_replace('T', ' ', (string) $post['sale_to']) . ':00' : null;
        $product->cost_price = isset($post['cost_price']) && $post['cost_price'] !== '' ? (float) $post['cost_price'] : null;
        $product->tax_status = sanitize_key($post['tax_status'] ?? 'taxable');
        $product->tax_class = sanitize_key($post['tax_class'] ?? 'standard');
        $product->manage_stock = !empty($post['manage_stock']);
        $product->stock_qty = (int) ($post['stock_qty'] ?? 0);
        $product->stock_status = sanitize_key($post['stock_status'] ?? 'instock');
        $product->backorders = sanitize_key($post['backorders'] ?? 'no');
        $product->weight = isset($post['weight']) && $post['weight'] !== '' ? (float) $post['weight'] : null;
        $product->length = isset($post['length']) && $post['length'] !== '' ? (float) $post['length'] : null;
        $product->width = isset($post['width']) && $post['width'] !== '' ? (float) $post['width'] : null;
        $product->height = isset($post['height']) && $post['height'] !== '' ? (float) $post['height'] : null;
        $product->image_id = !empty($post['image_id']) ? (int) $post['image_id'] : null;
        $product->gallery_ids = !empty($post['gallery_ids']) ? array_filter(array_map('intval', explode(',', (string) $post['gallery_ids']))) : [];
        $product->sort_order = (int) ($post['sort_order'] ?? 0);
        $product->category_ids = !empty($post['categories']) ? array_map('intval', (array) $post['categories']) : [];

        $repo->save($product);

        // SEO meta
        if ($product->id) {
            update_post_meta($product->id, '_yv_meta_title', sanitize_text_field($post['meta_title'] ?? ''));
            update_post_meta($product->id, '_yv_meta_description', sanitize_textarea_field($post['meta_description'] ?? ''));

            // Variations
            $vrepo = new VariationRepository();
            $submittedIds = [];
            if (!empty($post['variations']) && is_array($post['variations'])) {
                foreach ($post['variations'] as $row) {
                    $label = sanitize_text_field($row['label'] ?? '');
                    if (!$label) continue;
                    $v = new Variation();
                    $v->id = !empty($row['id']) ? (int) $row['id'] : null;
                    $v->product_id = (int) $product->id;
                    $v->attributes = ['_label' => $label];
                    $v->sku = sanitize_text_field($row['sku'] ?? '');
                    $v->price = (float) ($row['price'] ?? 0);
                    $v->stock_qty = (int) ($row['stock'] ?? 0);
                    $v->enabled = !isset($row['enabled']) || (int) $row['enabled'] === 1;
                    $newId = $vrepo->save($v);
                    if ($newId) $submittedIds[] = $newId;
                }
            }
            // Remove variations not in submitted list
            $existing = $vrepo->forProduct((int) $product->id, false);
            foreach ($existing as $ev) {
                if (!in_array((int) $ev->id, $submittedIds, true)) {
                    $vrepo->delete((int) $ev->id);
                }
            }
        }
    }
}

// Tiny compatibility helper
if (!function_exists('get_post_meta_first_or')) {
    function get_post_meta_first_or(?int $id, string $key, $default = '') {
        if (!$id) return $default;
        $v = get_post_meta($id, $key, true);
        return $v !== '' && $v !== null ? $v : $default;
    }
}
