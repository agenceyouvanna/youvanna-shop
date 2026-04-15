<?php
namespace Youvanna\Shop\REST;

use Youvanna\Shop\Models\Variation;
use Youvanna\Shop\Repositories\VariationRepository;

defined('ABSPATH') || exit;

final class VariationsController extends Controller
{
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/products/(?P<id>\d+)/variations', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'list'],
                'permission_callback' => [$this, 'permission_public'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'permission_manage_products'],
            ],
        ]);
        register_rest_route($this->namespace, '/variations/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'permission_manage_products'],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'permission_manage_products'],
            ],
        ]);
    }

    public function list(\WP_REST_Request $req)
    {
        $product_id = (int) $req['id'];
        $repo = new VariationRepository();
        $vars = $repo->forProduct($product_id, true);
        return rest_ensure_response(array_map(static fn($v) => $v->toDto(), $vars));
    }

    public function create(\WP_REST_Request $req)
    {
        $product_id = (int) $req['id'];
        $v = $this->hydrate(new Variation(), $req, $product_id);
        $id = (new VariationRepository())->save($v);
        return rest_ensure_response(['id' => $id, 'variation' => $v->toDto()]);
    }

    public function update(\WP_REST_Request $req)
    {
        $repo = new VariationRepository();
        $v = $repo->find((int) $req['id']);
        if (!$v) return $this->err('not_found', 'Variation introuvable', 404);
        $v = $this->hydrate($v, $req, $v->product_id);
        $repo->save($v);
        return rest_ensure_response(['variation' => $v->toDto()]);
    }

    public function delete(\WP_REST_Request $req)
    {
        $ok = (new VariationRepository())->delete((int) $req['id']);
        return rest_ensure_response(['deleted' => $ok]);
    }

    private function hydrate(Variation $v, \WP_REST_Request $req, int $product_id): Variation
    {
        $b = $req->get_json_params() ?: $req->get_params();
        $v->product_id = $product_id;
        if (isset($b['sku'])) $v->sku = sanitize_text_field((string) $b['sku']);
        if (isset($b['price'])) $v->price = (float) $b['price'];
        if (array_key_exists('sale_price', $b)) $v->sale_price = $b['sale_price'] !== null && $b['sale_price'] !== '' ? (float) $b['sale_price'] : null;
        if (isset($b['stock_qty'])) $v->stock_qty = (int) $b['stock_qty'];
        if (isset($b['stock_status'])) $v->stock_status = in_array($b['stock_status'], ['instock','outofstock','onbackorder'], true) ? $b['stock_status'] : 'instock';
        if (array_key_exists('weight', $b)) $v->weight = $b['weight'] !== null && $b['weight'] !== '' ? (float) $b['weight'] : null;
        if (array_key_exists('image_id', $b)) $v->image_id = $b['image_id'] !== null && $b['image_id'] !== '' ? (int) $b['image_id'] : null;
        if (isset($b['attributes']) && is_array($b['attributes'])) $v->attributes = $b['attributes'];
        if (isset($b['sort_order'])) $v->sort_order = (int) $b['sort_order'];
        if (isset($b['enabled'])) $v->enabled = (bool) $b['enabled'];
        return $v;
    }
}
