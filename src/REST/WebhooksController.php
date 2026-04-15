<?php
namespace Youvanna\Shop\REST;

use Youvanna\Shop\Payments\GatewayRegistry;

defined('ABSPATH') || exit;

final class WebhooksController extends Controller
{
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/webhooks/(?P<gateway>[a-z0-9_]+)', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true', // signature is the auth
        ]);
    }

    public function handle(\WP_REST_Request $req)
    {
        $gateway_id = sanitize_key($req['gateway']);
        $registry = new GatewayRegistry();
        $gateway = $registry->get($gateway_id);
        if (!$gateway) {
            return $this->err('invalid_gateway', 'Unknown gateway', 404);
        }
        try {
            return $gateway->handleWebhook($req);
        } catch (\Throwable $e) {
            return $this->err('webhook_error', $e->getMessage(), 400);
        }
    }
}
