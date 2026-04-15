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
            return true;
        }
        $allowed = apply_filters('yv_shop_allowed_origins', [home_url()]);
        $origin_parts = parse_url($origin);
        if (!$origin_parts || empty($origin_parts['host'])) return false;
        $origin_scheme = strtolower($origin_parts['scheme'] ?? '');
        $origin_host = strtolower($origin_parts['host']);
        $origin_port = $origin_parts['port'] ?? null;
        foreach ((array) $allowed as $a) {
            $a_parts = parse_url((string) $a);
            if (!$a_parts || empty($a_parts['host'])) continue;
            if (strtolower($a_parts['scheme'] ?? '') !== $origin_scheme) continue;
            if (strtolower($a_parts['host']) !== $origin_host) continue;
            if (($a_parts['port'] ?? null) != $origin_port) continue;
            return true;
        }
        return false;
    }

    protected function sessionToken(): string
    {
        $cookie = $_COOKIE['yv_shop_session'] ?? '';
        if (!$cookie || strlen($cookie) !== 64) {
            $cookie = bin2hex(random_bytes(32));
            if (!headers_sent()) {
                setcookie('yv_shop_session', $cookie, [
                    'expires' => time() + 60 * 60 * 24 * 180,
                    'path' => '/',
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
            $_COOKIE['yv_shop_session'] = $cookie;
        }
        return $cookie;
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
