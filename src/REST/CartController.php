<?php
namespace Youvanna\Shop\REST;

use Youvanna\Shop\Models\Cart;
use Youvanna\Shop\Repositories\CartRepository;
use Youvanna\Shop\Services\CartHasher;
use Youvanna\Shop\Services\PriceCalculator;

defined('ABSPATH') || exit;

final class CartController extends Controller
{
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/cart/sync', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'sync'],
            'permission_callback' => [$this, 'permission_sync'],
        ]);
        register_rest_route($this->namespace, '/cart/(?P<hash>[a-f0-9]{64})', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get'],
            'permission_callback' => [$this, 'permission_public'],
        ]);
    }

    public function permission_sync(\WP_REST_Request $req): bool
    {
        if (!$this->checkOrigin($req)) {
            return false;
        }
        $ip = $this->clientIp();
        if (!$this->rateLimit('cart_sync_' . $ip, 60, 60)) {
            return false;
        }
        return true;
    }

    public function sync(\WP_REST_Request $req)
    {
        $hasher = new CartHasher();
        $session = $hasher->getOrCreateSessionToken();
        $body = $req->get_json_params() ?: [];
        $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];
        if (count($items) > 100) {
            return $this->err('too_many_items', __('Trop d\'articles dans le panier', 'yv-shop'), 400);
        }
        $context = [
            'country'         => $body['country'] ?? get_option('yv_shop_general_country', 'FR'),
            'shipping_method' => $body['shipping_method'] ?? null,
            'coupon_codes'    => $body['coupon_codes'] ?? [],
        ];
        $calc = new PriceCalculator();
        $computed = $calc->compute($items, $context);

        $payload_for_hash = [
            'items'    => array_map(static fn($i) => [
                'product_id'   => (int) $i['product_id'],
                'variation_id' => $i['variation_id'] ?? null,
                'qty'          => (int) $i['qty'],
                'price'        => (float) $i['price'],
            ], $computed['items']),
            'subtotal' => $computed['totals']['subtotal'],
            'total'    => $computed['totals']['total'],
            'currency' => $computed['totals']['currency'],
        ];
        $signed = $hasher->sign($payload_for_hash, $session, 600); // 10 min TTL

        // Optionally persist for cross-device or abandonment
        if (!empty($body['persist'])) {
            $cart = new Cart();
            $cart->hash = $signed['hash'];
            $cart->session_token = $session;
            $cart->customer_email = $body['customer_email'] ?? null;
            $cart->items = $computed['items'];
            $cart->totals = $computed['totals'];
            $cart->coupon_codes = $context['coupon_codes'];
            $cart->currency = $computed['totals']['currency'];
            (new CartRepository())->persist($cart);
            do_action('yv_shop_cart_synced', $cart);
        }

        return new \WP_REST_Response([
            'hash'        => $signed['hash'],
            'body'        => $signed['body'],
            'expires'     => $signed['expires'],
            'items'       => $computed['items'],
            'totals'      => $computed['totals'],
            'validations' => $computed['validations'],
        ]);
    }

    public function get(\WP_REST_Request $req)
    {
        $hash = (string) $req['hash'];
        $cart = (new CartRepository())->find($hash);
        if (!$cart) {
            return $this->err('not_found', __('Panier introuvable', 'yv-shop'), 404);
        }
        $hasher = new CartHasher();
        $session = $hasher->currentSessionToken();
        if (!$session || !hash_equals($cart->session_token, $session)) {
            return $this->err('forbidden', __('Accès refusé', 'yv-shop'), 403);
        }
        return new \WP_REST_Response([
            'items'  => $cart->items,
            'totals' => $cart->totals,
        ]);
    }
}
