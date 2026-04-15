<?php
namespace Youvanna\Shop\Services;

use Youvanna\Shop\Models\Product;
use Youvanna\Shop\Repositories\ProductRepository;

defined('ABSPATH') || exit;

/**
 * Calcule les totaux d'un panier en revérifiant TOUT côté serveur depuis la DB.
 * Ne fait JAMAIS confiance aux montants envoyés par le client.
 */
final class PriceCalculator
{
    private ProductRepository $products;
    private TaxResolver $tax;
    private CouponEngine $coupons;
    private ShippingResolver $shipping;

    public function __construct(?ProductRepository $products = null, ?TaxResolver $tax = null, ?CouponEngine $coupons = null, ?ShippingResolver $shipping = null)
    {
        $this->products = $products ?? new ProductRepository();
        $this->tax = $tax ?? new TaxResolver();
        $this->coupons = $coupons ?? new CouponEngine();
        $this->shipping = $shipping ?? new ShippingResolver();
    }

    /**
     * @param array<int,array{product_id:int,variation_id?:int|null,qty:int}> $items
     * @param array $context  ['country' => 'FR', 'postcode' => null, 'shipping_method' => null, 'coupon_codes' => []]
     * @return array{items: array, subtotal: float, discount_total: float, shipping_total: float, tax_total: float, total: float, currency: string, validations: array}
     */
    public function compute(array $items, array $context = []): array
    {
        $country = $context['country'] ?? get_option('yv_shop_general_country', 'FR');
        $tax_mode = get_option('yv_shop_tax_mode', 'incl'); // prices are entered TTC by default
        $currency = \Youvanna\Shop\Helpers\Currency::code();

        $resolved_items = [];
        $subtotal = 0.0;
        $tax_total = 0.0;
        $validations = [];

        foreach ($items as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $vid = isset($item['variation_id']) ? (int) $item['variation_id'] : null;
            $qty = max(1, (int) ($item['qty'] ?? 1));
            if (!$pid) {
                continue;
            }
            $product = $this->products->find($pid);
            if (!$product || $product->status !== 'published') {
                $validations[] = ['type' => 'unavailable', 'product_id' => $pid];
                continue;
            }
            $price = $product->activePrice();
            // Stock check
            if ($product->manage_stock && $product->stock_qty < $qty) {
                $qty = max(0, $product->stock_qty);
                $validations[] = ['type' => 'stock_adjusted', 'product_id' => $pid, 'qty' => $qty];
                if ($qty === 0) {
                    continue;
                }
            }
            $line_subtotal = round($price * $qty, 2);
            $line_tax = $this->tax->computeLineTax($product, $line_subtotal, $country, $tax_mode);
            $resolved_items[] = [
                'product_id'    => $pid,
                'variation_id'  => $vid,
                'name'          => $product->name,
                'sku'           => $product->sku,
                'image'         => $product->imageUrl('medium'),
                'permalink'     => $product->permalink(),
                'qty'           => $qty,
                'price'         => $price,
                'line_subtotal' => $line_subtotal,
                'line_tax'      => $line_tax,
                'line_total'    => $line_subtotal,
                'tax_class'     => $product->tax_class,
                'on_sale'       => $product->isOnSale(),
            ];
            $subtotal += $line_subtotal;
            $tax_total += $line_tax;
        }

        // Discount via coupons
        $discount_total = 0.0;
        $coupon_codes = array_values(array_filter((array) ($context['coupon_codes'] ?? [])));
        $applied_coupons = [];
        $free_shipping = false;
        $coupon_errors = [];
        if (!empty($coupon_codes)) {
            $res = $this->coupons->apply($resolved_items, $coupon_codes, $context['customer_email'] ?? null, $subtotal);
            $discount_total = $res['discount'];
            $applied_coupons = $res['applied'];
            $free_shipping = $res['free_shipping'];
            $coupon_errors = $res['errors'];
        }

        // Shipping
        $shipping_total = 0.0;
        if (!empty($context['shipping_method']) && !$free_shipping) {
            $shipping_total = $this->shipping->resolve($context['shipping_method'], $subtotal, $country);
        }

        if ($tax_mode === 'incl') {
            $total = round($subtotal - $discount_total + $shipping_total, 2);
        } else {
            $total = round($subtotal - $discount_total + $shipping_total + $tax_total, 2);
        }

        $totals = [
            'subtotal'       => round($subtotal, 2),
            'discount_total' => round($discount_total, 2),
            'shipping_total' => round($shipping_total, 2),
            'tax_total'      => round($tax_total, 2),
            'total'          => $total,
            'currency'       => $currency,
            'tax_mode'       => $tax_mode,
        ];

        return apply_filters('yv_shop_cart_totals', [
            'items'       => $resolved_items,
            'totals'      => $totals,
            'validations' => $validations,
            'coupons'     => [
                'applied'       => $applied_coupons,
                'free_shipping' => $free_shipping,
                'errors'        => $coupon_errors,
            ],
        ], $items, $context);
    }
}
