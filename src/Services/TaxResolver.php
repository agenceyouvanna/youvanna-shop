<?php
namespace Youvanna\Shop\Services;

use Youvanna\Shop\Models\Product;

defined('ABSPATH') || exit;

final class TaxResolver
{
    public function rateFor(string $tax_class, string $country): float
    {
        global $wpdb;
        $cache_key = "yv_shop_tax_rate:{$tax_class}:{$country}";
        $cached = wp_cache_get($cache_key, 'yv_shop_tax_rates');
        if ($cached !== false) {
            return (float) $cached;
        }
        $rate = $wpdb->get_var($wpdb->prepare(
            "SELECT rate FROM {$wpdb->prefix}yv_tax_rates
             WHERE tax_class = %s AND (country = %s OR country = '*')
             ORDER BY (country = %s) DESC, priority ASC LIMIT 1",
            $tax_class, $country, $country
        ));
        $rate = $rate !== null ? (float) $rate : (float) get_option('yv_shop_tax_default_rate', 20.0);
        wp_cache_set($cache_key, $rate, 'yv_shop_tax_rates', HOUR_IN_SECONDS);
        return $rate;
    }

    public function computeLineTax(Product $product, float $line_subtotal, string $country, string $mode): float
    {
        if ($product->tax_status !== 'taxable') {
            return 0.0;
        }
        $rate = $this->rateFor($product->tax_class, $country) / 100;
        if ($rate <= 0) {
            return 0.0;
        }
        if ($mode === 'incl') {
            // line_subtotal is TTC, extract tax part
            return round($line_subtotal - ($line_subtotal / (1 + $rate)), 2);
        }
        return round($line_subtotal * $rate, 2);
    }
}
