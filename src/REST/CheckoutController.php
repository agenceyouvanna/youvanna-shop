<?php
namespace Youvanna\Shop\REST;

use Youvanna\Shop\Models\Order;
use Youvanna\Shop\Models\OrderItem;
use Youvanna\Shop\Payments\GatewayRegistry;
use Youvanna\Shop\Repositories\OrderRepository;
use Youvanna\Shop\Repositories\ProductRepository;
use Youvanna\Shop\Services\CartHasher;
use Youvanna\Shop\Services\PriceCalculator;

defined('ABSPATH') || exit;

final class CheckoutController extends Controller
{
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/checkout/intent', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'intent'],
            'permission_callback' => [$this, 'permission_intent'],
        ]);
        register_rest_route($this->namespace, '/checkout/complete', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'complete'],
            'permission_callback' => [$this, 'permission_intent'],
        ]);
    }

    public function permission_intent(\WP_REST_Request $req): bool
    {
        if (!$this->checkOrigin($req)) {
            return false;
        }
        $ip = $this->clientIp();
        return $this->rateLimit('checkout_' . $ip, 10, 60);
    }

    public function intent(\WP_REST_Request $req)
    {
        $body = $req->get_json_params() ?: [];
        $hasher = new CartHasher();
        $session = $hasher->currentSessionToken();
        if (!$session) {
            return $this->err('no_session', __('Session manquante', 'yv-shop'), 401);
        }
        $verified = $hasher->verify((string) ($body['hash'] ?? ''), (string) ($body['body'] ?? ''), $session);
        if (!$verified) {
            return $this->err('invalid_cart', __('Panier invalide ou expiré, recharger la page', 'yv-shop'), 409);
        }

        // Re-validate prices server-side (don't trust hash totals)
        $items = $verified['items'] ?? [];
        $context = [
            'country'         => $body['billing']['country'] ?? get_option('yv_shop_general_country', 'FR'),
            'shipping_method' => $body['shipping_method'] ?? null,
        ];
        $calc = new PriceCalculator();
        $computed = $calc->compute($items, $context);

        // Compare hash totals vs recomputed
        if (abs(($verified['total'] ?? 0) - $computed['totals']['total']) > 0.01) {
            return new \WP_REST_Response([
                'code'    => 'totals_changed',
                'message' => __('Les totaux ont changé, vérifiez votre panier', 'yv-shop'),
                'totals'  => $computed['totals'],
                'items'   => $computed['items'],
            ], 409);
        }

        // Validate billing
        $billing = $this->sanitizeAddress($body['billing'] ?? []);
        $shipping = $this->sanitizeAddress($body['shipping'] ?? $billing);
        $email = sanitize_email($body['customer_email'] ?? ($billing['email'] ?? ''));
        if (!$email || !is_email($email)) {
            return $this->err('invalid_email', __('Email invalide', 'yv-shop'), 400);
        }

        $payment_method_id = sanitize_key($body['payment_method'] ?? '');
        $registry = new GatewayRegistry();
        $gateway = $registry->get($payment_method_id);
        if (!$gateway) {
            return $this->err('invalid_payment', __('Méthode de paiement invalide', 'yv-shop'), 400);
        }

        // Create pending order
        $order = new Order();
        $order->customer_email = $email;
        $order->customer_phone = isset($billing['phone']) ? sanitize_text_field($billing['phone']) : null;
        $order->customer_note = isset($body['customer_note']) ? wp_strip_all_tags($body['customer_note']) : null;
        $order->billing = $billing;
        $order->shipping = $shipping;
        $order->subtotal = (float) $computed['totals']['subtotal'];
        $order->discount_total = (float) $computed['totals']['discount_total'];
        $order->shipping_total = (float) $computed['totals']['shipping_total'];
        $order->tax_total = (float) $computed['totals']['tax_total'];
        $order->total = (float) $computed['totals']['total'];
        $order->currency = (string) $computed['totals']['currency'];
        $order->payment_method = $payment_method_id;
        $order->payment_status = 'pending';
        $order->shipping_method = $body['shipping_method'] ?? null;
        $order->status = 'pending';
        $order->ip_address_bin = inet_pton($this->clientIp()) ?: null;
        $order->user_agent = $req->get_header('user_agent');

        $order->items = [];
        foreach ($computed['items'] as $li) {
            $oi = new OrderItem();
            $oi->product_id = (int) $li['product_id'];
            $oi->variation_id = isset($li['variation_id']) ? (int) $li['variation_id'] : null;
            $oi->name = (string) $li['name'];
            $oi->sku = (string) $li['sku'];
            $oi->qty = (int) $li['qty'];
            $oi->price = (float) $li['price'];
            $oi->line_subtotal = (float) $li['line_subtotal'];
            $oi->line_tax = (float) $li['line_tax'];
            $oi->line_total = (float) $li['line_total'];
            $oi->tax_class = (string) ($li['tax_class'] ?? 'standard');
            $order->items[] = $oi;
        }

        $repo = new OrderRepository();
        $order_id = $repo->save($order);
        do_action('yv_shop_order_created', $order);

        // Gateway createIntent
        try {
            $intent = $gateway->createIntent($order);
        } catch (\Throwable $e) {
            return $this->err('gateway_error', $e->getMessage(), 502);
        }

        // Persist payment_reference if provided
        if (!empty($intent['payment_reference'])) {
            $order->payment_reference = (string) $intent['payment_reference'];
            $repo->save($order);
        }

        return new \WP_REST_Response([
            'order_id'     => $order_id,
            'order_number' => $order->order_number,
            'order_key'    => $order->order_key,
            'gateway'      => $intent,
            'redirect'     => $intent['redirect'] ?? null,
        ]);
    }

    public function complete(\WP_REST_Request $req)
    {
        $body = $req->get_json_params() ?: [];
        $order_id = (int) ($body['order_id'] ?? 0);
        $key = (string) ($body['order_key'] ?? '');
        $repo = new OrderRepository();
        $order = $repo->find($order_id);
        if (!$order || !hash_equals($order->order_key, $key)) {
            return $this->err('not_found', __('Commande introuvable', 'yv-shop'), 404);
        }
        // Don't trust client - actual payment confirmation happens in webhook
        $page_id = (int) get_option('yv_shop_checkout_page_id', 0);
        return new \WP_REST_Response([
            'order'     => [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'status'       => $order->status,
                'total'        => $order->total,
                'currency'     => $order->currency,
            ],
            'redirect_url' => $page_id ? add_query_arg([
                'order' => $order->order_number,
                'key'   => $order->order_key,
            ], get_permalink($page_id)) : home_url(),
        ]);
    }

    private function sanitizeAddress(array $address): array
    {
        $allowed = ['first_name','last_name','company','address_1','address_2','city','postcode','country','state','phone','email'];
        $out = [];
        foreach ($allowed as $k) {
            if (isset($address[$k])) {
                $out[$k] = sanitize_text_field((string) $address[$k]);
            }
        }
        if (isset($out['email'])) {
            $out['email'] = sanitize_email($out['email']);
        }
        return $out;
    }
}
