<?php
namespace Youvanna\Shop\REST;

defined('ABSPATH') || exit;

abstract class Controller
{
    protected string $namespace = Router::NAMESPACE;

    abstract public function register_routes(): void;

    protected function permission_public(): bool
    {
        return true;
    }

    protected function permission_admin(): bool
    {
        return current_user_can('yv_shop_manage_settings');
    }

    protected function permission_manage_products(): bool
    {
        return current_user_can('yv_shop_manage_products');
    }

    protected function permission_manage_orders(): bool
    {
        return current_user_can('yv_shop_manage_orders');
    }

    protected function checkOrigin(\WP_REST_Request $req): bool
    {
        $origin = $req->get_header('origin');
        if (!$origin) {
            return true; // same-origin without Origin header
        }
        $allowed = apply_filters('yv_shop_allowed_origins', [home_url()]);
        $origin_host = parse_url($origin, PHP_URL_HOST);
        foreach ((array) $allowed as $a) {
            $a_host = parse_url($a, PHP_URL_HOST);
            if ($a_host && $origin_host && strcasecmp($a_host, $origin_host) === 0) {
                return true;
            }
        }
        return false;
    }

    protected function rateLimit(string $key, int $max, int $window = 60): bool
    {
        $key = 'yv_shop_rl_' . md5($key);
        $count = (int) get_transient($key);
        if ($count >= $max) {
            return false;
        }
        set_transient($key, $count + 1, $window);
        return true;
    }

    protected function clientIp(): string
    {
        $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if ($cf && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($remote) ? $remote : '';
    }

    protected function err(string $code, string $message, int $status = 400): \WP_Error
    {
        return new \WP_Error($code, $message, ['status' => $status]);
    }
}
