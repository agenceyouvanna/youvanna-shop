<?php
namespace Youvanna\Shop\Services;

defined('ABSPATH') || exit;

/**
 * HMAC sur le panier serialisé pour empêcher la manipulation client.
 * Le hash est lié à un session_token (cookie HttpOnly) et a un TTL court.
 */
final class CartHasher
{
    private const COOKIE_NAME = 'yv_shop_session';
    private const COOKIE_TTL  = 30 * DAY_IN_SECONDS;

    public function getOrCreateSessionToken(): string
    {
        if (!empty($_COOKIE[self::COOKIE_NAME])) {
            $token = preg_replace('/[^a-f0-9]/i', '', (string) $_COOKIE[self::COOKIE_NAME]);
            if (strlen($token) === 64) {
                return $token;
            }
        }
        $token = bin2hex(random_bytes(32));
        if (!headers_sent()) {
            $secure = is_ssl();
            setcookie(self::COOKIE_NAME, $token, [
                'expires'  => time() + self::COOKIE_TTL,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[self::COOKIE_NAME] = $token;
        }
        return $token;
    }

    public function currentSessionToken(): ?string
    {
        if (empty($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        $token = preg_replace('/[^a-f0-9]/i', '', (string) $_COOKIE[self::COOKIE_NAME]);
        return strlen($token) === 64 ? $token : null;
    }

    public function sign(array $payload, string $session_token, int $ttl_seconds = 300): array
    {
        $expires = time() + $ttl_seconds;
        $body = wp_json_encode([
            'p' => $payload,
            's' => $session_token,
            'e' => $expires,
            'n' => bin2hex(random_bytes(8)),
        ]);
        $secret = $this->secret();
        $hash = hash_hmac('sha256', $body, $secret);
        return [
            'hash'    => $hash,
            'body'    => base64_encode($body),
            'expires' => $expires,
        ];
    }

    public function verify(string $hash, string $body_b64, string $session_token): ?array
    {
        $body = base64_decode($body_b64, true);
        if ($body === false) {
            return null;
        }
        $expected = hash_hmac('sha256', $body, $this->secret());
        if (!hash_equals($expected, $hash)) {
            return null;
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }
        if (empty($decoded['s']) || !hash_equals((string) $decoded['s'], $session_token)) {
            return null;
        }
        if (!isset($decoded['e']) || $decoded['e'] < time()) {
            return null;
        }
        return $decoded['p'] ?? null;
    }

    private function secret(): string
    {
        $b64 = (string) get_option('yv_shop_cart_hmac_secret', '');
        $secret = base64_decode($b64, true);
        if ($secret === false || strlen($secret) < 32) {
            // Regenerate if missing
            $secret = random_bytes(32);
            update_option('yv_shop_cart_hmac_secret', base64_encode($secret), false);
        }
        return $secret;
    }
}
