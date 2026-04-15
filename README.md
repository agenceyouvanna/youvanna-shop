# Youvanna Shop

Extension WordPress e-commerce légère, custom, pensée pour remplacer WooCommerce quand ce dernier est trop lourd ou trop lent.

Conçue pour les sites de l'agence Youvanna, réutilisable tel quel sur n'importe quel site WordPress.

## Objectifs

- **Rapide** : zero postmeta, tables dédiées indexées, REST API avec cache-control, pas de fragments AJAX jQuery.
- **Léger** : aucune dépendance Composer à l'install, autoload PSR-4 natif, pas de SDK Stripe embarqué (HTTP direct).
- **Réutilisable** : tout se configure via options `yv_shop_*`, filtres et CSS variables. Zero hardcoding.
- **Sécurisé** : paniers signés HMAC-SHA256 + session cookie, prix recalculés côté serveur au checkout, secrets Stripe chiffrés AES-256-GCM, webhooks vérifiés + idempotence.

## Fonctionnalités V1

- Catalogue produits (simples), variations, catégories, étiquettes, attributs dynamiques.
- Stocks gérés (décrément atomique), réservations TTL.
- Filtres : catégories, prix, attributs, recherche plein texte.
- Panier localStorage + sync REST.
- Checkout : virement bancaire et Stripe (Payment Intents).
- Taxes par classe et pays, livraison par zone avec seuil gratuit.
- Emails transactionnels (confirmation, paiement, expédition).
- Admin : dashboard, CRUD produits, commandes, attributs, réglages.
- WP-CLI : import produits CSV, diagnose, purge reservations.
- Templates surchargeables via `theme/yv-shop/*.php`.

## Pré-requis

- WordPress 6.4+
- PHP 8.1+
- MySQL 5.7+ (pour les colonnes JSON)

## Installation

1. Cloner ou télécharger dans `wp-content/plugins/youvanna-shop/`.
2. Activer depuis l'admin WordPress.
3. Configurer dans **Youvanna Shop -> Réglages** : devise, slugs, TVA, moyens de paiement.
4. Placer les shortcodes `[yv_shop]`, `[yv_shop_cart]`, `[yv_shop_checkout]` sur leurs pages dédiées (créées automatiquement à l'activation).

## Architecture

```
src/
  Plugin.php              # Bootstrap
  Container.php           # DI léger
  Activator.php           # Install : tables, options, capabilities, pages
  Migrator.php            # Migrations versionnées avec GET_LOCK
  Models/                 # Product, Order, Cart, SearchCriteria
  Repositories/           # ProductRepository, OrderRepository, CartRepository
  Services/               # PriceCalculator, TaxResolver, ShippingResolver, CartHasher, Encryption
  REST/                   # Router + 7 controllers
  Payments/               # GatewayInterface, StripeGateway, BankTransferGateway
  Admin/                  # Menu, Dashboard, Settings, ProductsListTable, OrderEditor, etc.
  Frontend/               # Router, Shortcodes, TemplateLoader
  Emails/                 # Mailer
  CLI/                    # Commands
  Cron/                   # Scheduler
migrations/
  001_initial_schema.php  # 13 tables
templates/                # archive, single, cart, checkout, parts/*
assets/dist/              # shop.css, store.js, mini-cart.js, shop.js, product.js, cart.js, checkout.js
```

## REST API

Base : `/wp-json/yv-shop/v1/`

- `GET  /products` - liste avec filtres
- `GET  /products/{id}` - produit par ID
- `GET  /products/slug/{slug}` - produit par slug
- `GET  /products/facets` - facettes (catégories, prix, attributs)
- `GET  /categories`
- `GET  /attributes`
- `POST /cart/sync` - synchronise le panier et retourne un cart_hash signé HMAC
- `GET  /cart/{hash}` - lit un panier signé
- `POST /checkout/intent` - crée la commande et l'intention de paiement
- `GET  /orders/{number}/{key}` - lecture d'une commande
- `POST /webhooks/{gateway}` - webhook paiement (Stripe)

## Personnalisation

### Via options
Toutes les options commencent par `yv_shop_*` : `yv_shop_general_currency`, `yv_shop_general_shop_slug`, `yv_shop_tax_default_rate`, etc.

### Via hooks
Voir `src/Hooks.php` pour la liste complète. Exemples :
- `yv_shop_payment_gateways` (filter) : ajouter une passerelle.
- `yv_shop_shipping_methods` (filter) : ajouter une méthode de livraison.
- `yv_shop_product_dto` (filter) : enrichir le DTO produit exposé.
- `yv_shop_order_created` (action) : déclencher un webhook CRM.
- `yv_shop_email_recipient` / `yv_shop_email_subject` (filters).

### Via CSS variables
Toutes les couleurs et dimensions sont exposées. Dans le thème :
```css
:root {
    --yv-shop-color-primary: #c07f5a;
    --yv-shop-color-bg-soft: #fbf7f2;
    --yv-shop-cols-desktop: 3;
    --yv-shop-radius: 0;
}
```

### Via surcharge de templates
Copier `templates/archive.php` vers `theme/yv-shop/archive.php`. Même logique pour `single.php`, `cart.php`, `checkout.php`, `parts/*.php`, `emails/*.php`.

## WP-CLI

```bash
wp yv-shop import-products ./products.csv
wp yv-shop import-products ./products.csv --dry-run
wp yv-shop diagnose
wp yv-shop cleanup-reservations
```

CSV attendu : `sku,name,slug,status,price,sale_price,stock_qty,manage_stock,short_description,description,categories,image_url`

## Sécurité

- Panier signé HMAC-SHA256, lié au cookie de session (rotation possible).
- Prix et stock re-validés côté serveur à chaque checkout (réponse 409 si delta).
- Secrets Stripe chiffrés AES-256-GCM avec clé indépendante de AUTH_KEY.
- Webhooks Stripe vérifiés par signature, tolérance 5 min, idempotence via table dédiée.
- Rate limiting par IP sur endpoints mutants (cart/sync, checkout/intent).
- Origin/Referer check sur les endpoints POST.

## Roadmap V2

- Produits variables avancés, bundles.
- Coupons avec conditions complexes.
- Multi-devises, multi-langues natif.
- PWA offline, Web Push notifications.
- Dashboard stats temps réel.

## Licence

GPL-2.0-or-later. Voir `LICENSE`.

## Support

Issues : https://github.com/agenceyouvanna/youvanna-shop/issues
