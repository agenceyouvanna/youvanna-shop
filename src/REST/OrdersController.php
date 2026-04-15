<?php
namespace Youvanna\Shop\REST;

use Youvanna\Shop\Repositories\OrderRepository;

defined('ABSPATH') || exit;

final class OrdersController extends Controller
{
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/orders/(?P<number>YV-[A-Z0-9-]+)/(?P<key>[a-f0-9]{48})', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getByKey'],
            'permission_callback' => [$this, 'permission_public'],
        ]);
    }

    public function getByKey(\WP_REST_Request $req)
    {
        $number = (string) $req['number'];
        $key = (string) $req['key'];
        $ip = $this->clientIp();
        if (!$this->rateLimit('order_view_' . $ip, 30, 60)) {
            return $this->err('too_many', __('Trop de tentatives', 'yv-shop'), 429);
        }
        $repo = new OrderRepository();
        $order = $repo->findByNumber($number);
        if (!$order || !hash_equals($order->order_key, $key)) {
            return $this->err('not_found', __('Commande introuvable', 'yv-shop'), 404);
        }
        return new \WP_REST_Response([
            'order_number'   => $order->order_number,
            'status'         => $order->status,
            'payment_status' => $order->payment_status,
            'subtotal'       => $order->subtotal,
            'tax_total'      => $order->tax_total,
            'shipping_total' => $order->shipping_total,
            'total'          => $order->total,
            'currency'       => $order->currency,
            'created_at'     => $order->created_at,
            'items'          => array_map(static fn($i) => [
                'name'       => $i->name,
                'sku'        => $i->sku,
                'qty'        => $i->qty,
                'price'      => $i->price,
                'line_total' => $i->line_total,
            ], $order->items),
        ]);
    }
}
