<?php
namespace Youvanna\Shop\Services;

defined('ABSPATH') || exit;

final class ShippingResolver
{
    /** @return array<int,array{id:string,title:string,cost:float}> */
    public function methodsForCountry(string $country): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.id, m.method_id, m.title, m.cost, m.free_threshold, m.settings
                 FROM {$wpdb->prefix}yv_shipping_methods m
                 INNER JOIN {$wpdb->prefix}yv_shipping_zones z ON z.id = m.zone_id
                 WHERE m.enabled = 1 AND JSON_CONTAINS(z.countries, %s)",
                wp_json_encode($country)
            ),
            ARRAY_A
        );
        $out = [];
        foreach ($rows ?: [] as $r) {
            $out[] = [
                'id'             => $r['method_id'] . '_' . $r['id'],
                'method_id'      => $r['method_id'],
                'title'          => $r['title'],
                'cost'           => (float) $r['cost'],
                'free_threshold' => $r['free_threshold'] !== null ? (float) $r['free_threshold'] : null,
            ];
        }
        return $out;
    }

    public function resolve(string $method_key, float $subtotal, string $country): float
    {
        $methods = $this->methodsForCountry($country);
        foreach ($methods as $m) {
            if ($m['id'] === $method_key) {
                if ($m['free_threshold'] !== null && $subtotal >= $m['free_threshold']) {
                    return 0.0;
                }
                return $m['cost'];
            }
        }
        return 0.0;
    }
}
