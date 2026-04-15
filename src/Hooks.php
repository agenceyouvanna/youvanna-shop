<?php
namespace Youvanna\Shop;

defined('ABSPATH') || exit;

/**
 * Inventaire centralisé des hooks publics. Source de vérité pour la doc.
 *
 * Actions :
 *   yv_shop_booted ($plugin)
 *   yv_shop_activated ()
 *   yv_shop_deactivated ()
 *   yv_shop_product_saved (Product $product)
 *   yv_shop_product_deleted (int $id)
 *   yv_shop_order_created (Order $order)
 *   yv_shop_order_status_changed (Order $order, string $from, string $to)
 *   yv_shop_payment_succeeded (Order $order)
 *   yv_shop_payment_failed (Order $order, string $reason)
 *   yv_shop_order_shipped (Order $order)
 *   yv_shop_cart_synced (Cart $cart)
 *   yv_shop_cart_emptied (Cart $cart)
 *   yv_shop_before_archive ()
 *   yv_shop_archive_header ()
 *   yv_shop_archive_filters ()
 *   yv_shop_archive_grid ()
 *   yv_shop_archive_pagination ()
 *   yv_shop_after_archive ()
 *   yv_shop_before_single ()
 *   yv_shop_single_gallery (Product $product)
 *   yv_shop_single_summary (Product $product)
 *   yv_shop_single_tabs (Product $product)
 *   yv_shop_single_related (Product $product)
 *   yv_shop_after_single ()
 *
 * Filters :
 *   yv_shop_product_query (array $args)
 *   yv_shop_search_criteria (SearchCriteria $criteria)
 *   yv_shop_product_dto (array $dto, Product $product)
 *   yv_shop_price_html (string $html, Product|ProductVariation $product)
 *   yv_shop_add_to_cart_data (array $data, Product $product)
 *   yv_shop_cart_totals (array $totals, Cart $cart)
 *   yv_shop_cart_item_visible (bool $visible, array $item)
 *   yv_shop_email_recipient (string $email, string $type, Order $order)
 *   yv_shop_email_subject (string $subject, string $type, Order $order)
 *   yv_shop_payment_gateways (array $gateways)
 *   yv_shop_shipping_methods (array $methods)
 *   yv_shop_order_statuses (array $statuses)
 *   yv_shop_currencies (array $currencies)
 *   yv_shop_allowed_origins (array $origins)
 *   yv_shop_rate_limits (array $limits)
 */
final class Hooks
{
    public function register(): void
    {
        // Filter empty defaults so les hooks existent toujours.
        add_filter('yv_shop_currencies', [$this, 'defaultCurrencies'], 10, 1);
        add_filter('yv_shop_order_statuses', [$this, 'defaultOrderStatuses'], 10, 1);
    }

    public function defaultCurrencies(array $currencies): array
    {
        return $currencies + [
            'EUR' => ['symbol' => '€', 'name' => 'Euro'],
            'USD' => ['symbol' => '$', 'name' => 'US Dollar'],
            'GBP' => ['symbol' => '£', 'name' => 'British Pound'],
            'CHF' => ['symbol' => 'CHF', 'name' => 'Swiss Franc'],
        ];
    }

    public function defaultOrderStatuses(array $statuses): array
    {
        return $statuses + [
            'pending'    => __('En attente', 'yv-shop'),
            'processing' => __('En traitement', 'yv-shop'),
            'on-hold'    => __('En attente paiement', 'yv-shop'),
            'completed'  => __('Terminée', 'yv-shop'),
            'cancelled'  => __('Annulée', 'yv-shop'),
            'refunded'   => __('Remboursée', 'yv-shop'),
            'failed'     => __('Échouée', 'yv-shop'),
            'review'     => __('À vérifier', 'yv-shop'),
        ];
    }
}
