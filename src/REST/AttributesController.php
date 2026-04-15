<?php
namespace Youvanna\Shop\REST;

defined('ABSPATH') || exit;

final class AttributesController extends Controller
{
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/attributes', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [$this, 'list'],
            'permission_callback' => [$this, 'permission_public'],
        ]);
    }

    public function list(\WP_REST_Request $req): \WP_REST_Response
    {
        $cached = wp_cache_get('yv_shop_attributes_all', 'yv_shop');
        if ($cached !== false) {
            $r = new \WP_REST_Response($cached);
            $r->header('Cache-Control', 'public, max-age=600, stale-while-revalidate=3600');
            return $r;
        }
        $registered = (array) get_option('yv_shop_attributes_registry', []);
        $out = [];
        foreach ($registered as $key => $label) {
            $tax = 'yv_attr_' . preg_replace('/[^a-z0-9_]/', '', (string) $key);
            $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
            $out[] = [
                'key'    => $key,
                'label'  => $label,
                'values' => is_array($terms) ? array_map(static fn($t) => [
                    'id'    => (int) $t->term_id,
                    'name'  => $t->name,
                    'slug'  => $t->slug,
                    'count' => (int) $t->count,
                ], $terms) : [],
            ];
        }
        wp_cache_set('yv_shop_attributes_all', $out, 'yv_shop', 600);
        $r = new \WP_REST_Response($out);
        $r->header('Cache-Control', 'public, max-age=600, stale-while-revalidate=3600');
        return $r;
    }
}
