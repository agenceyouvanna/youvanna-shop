<?php
namespace Youvanna\Shop\REST;

use Youvanna\Shop\Models\Coupon;
use Youvanna\Shop\Repositories\CouponRepository;
use Youvanna\Shop\Services\CouponEngine;

defined('ABSPATH') || exit;

final class CouponsController extends Controller
{
    public function register_routes(): void
    {
        // Public validate (during cart)
        register_rest_route($this->namespace, '/coupons/validate', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'validate'],
            'permission_callback' => [$this, 'permission_public'],
        ]);
        // Admin CRUD
        register_rest_route($this->namespace, '/coupons', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'permission_admin'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'permission_admin'],
            ],
        ]);
        register_rest_route($this->namespace, '/coupons/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'permission_admin'],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'permission_admin'],
            ],
        ]);
    }

    public function validate(\WP_REST_Request $req)
    {
        if (!$this->checkOrigin($req)) return $this->err('forbidden', 'Origine non autorisée', 403);
        if (!$this->rateLimit('coupon_val_' . $this->clientIp(), 30, 60)) return $this->err('rate_limited', 'Trop de tentatives', 429);
        $b = $req->get_json_params() ?: $req->get_params();
        $code = strtoupper(trim((string) ($b['code'] ?? '')));
        if (!$code) return $this->err('empty_code', 'Code vide', 400);
        $coupon = (new CouponRepository())->findByCode($code);
        if (!$coupon) return $this->err('not_found', 'Code invalide', 404);
        if (!$coupon->isActive()) return $this->err('inactive', 'Code expiré ou épuisé', 410);
        return rest_ensure_response([
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'description' => $coupon->description,
            'minimum_amount' => $coupon->minimum_amount,
            'exclude_sale_items' => $coupon->exclude_sale_items,
        ]);
    }

    public function index(\WP_REST_Request $req)
    {
        $page = max(1, (int) $req->get_param('page'));
        $per_page = min(100, max(1, (int) ($req->get_param('per_page') ?: 20)));
        $offset = ($page - 1) * $per_page;
        $repo = new CouponRepository();
        $items = $repo->all($per_page, $offset);
        return rest_ensure_response([
            'items' => array_map(static fn($c) => get_object_vars($c), $items),
            'total' => $repo->countAll(),
            'page' => $page,
        ]);
    }

    public function create(\WP_REST_Request $req)
    {
        $c = $this->hydrate(new Coupon(), $req);
        if (!$c->code || !$c->type || $c->amount < 0) return $this->err('invalid', 'Données invalides', 400);
        $id = (new CouponRepository())->save($c);
        return rest_ensure_response(['id' => $id]);
    }

    public function update(\WP_REST_Request $req)
    {
        $repo = new CouponRepository();
        $c = $repo->find((int) $req['id']);
        if (!$c) return $this->err('not_found', 'Coupon introuvable', 404);
        $c = $this->hydrate($c, $req);
        $repo->save($c);
        return rest_ensure_response(['id' => $c->id]);
    }

    public function delete(\WP_REST_Request $req)
    {
        $ok = (new CouponRepository())->delete((int) $req['id']);
        return rest_ensure_response(['deleted' => $ok]);
    }

    private function hydrate(Coupon $c, \WP_REST_Request $req): Coupon
    {
        $b = $req->get_json_params() ?: $req->get_params();
        if (isset($b['code'])) $c->code = strtoupper(sanitize_text_field((string) $b['code']));
        if (isset($b['type']) && in_array($b['type'], ['percent','fixed_cart','fixed_product','free_shipping'], true)) $c->type = $b['type'];
        if (isset($b['amount'])) $c->amount = (float) $b['amount'];
        if (array_key_exists('description', $b)) $c->description = $b['description'] ? sanitize_text_field((string) $b['description']) : null;
        if (array_key_exists('minimum_amount', $b)) $c->minimum_amount = $b['minimum_amount'] !== null && $b['minimum_amount'] !== '' ? (float) $b['minimum_amount'] : null;
        if (array_key_exists('maximum_amount', $b)) $c->maximum_amount = $b['maximum_amount'] !== null && $b['maximum_amount'] !== '' ? (float) $b['maximum_amount'] : null;
        if (isset($b['individual_use'])) $c->individual_use = (bool) $b['individual_use'];
        if (isset($b['exclude_sale_items'])) $c->exclude_sale_items = (bool) $b['exclude_sale_items'];
        if (array_key_exists('usage_limit', $b)) $c->usage_limit = $b['usage_limit'] !== null && $b['usage_limit'] !== '' ? (int) $b['usage_limit'] : null;
        if (array_key_exists('usage_limit_per_user', $b)) $c->usage_limit_per_user = $b['usage_limit_per_user'] !== null && $b['usage_limit_per_user'] !== '' ? (int) $b['usage_limit_per_user'] : null;
        if (isset($b['product_ids']) && is_array($b['product_ids'])) $c->product_ids = array_map('intval', $b['product_ids']);
        if (isset($b['excluded_product_ids']) && is_array($b['excluded_product_ids'])) $c->excluded_product_ids = array_map('intval', $b['excluded_product_ids']);
        if (array_key_exists('starts_at', $b)) $c->starts_at = $b['starts_at'] ?: null;
        if (array_key_exists('expires_at', $b)) $c->expires_at = $b['expires_at'] ?: null;
        return $c;
    }
}
