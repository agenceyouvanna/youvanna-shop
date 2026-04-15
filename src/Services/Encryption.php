<?php
namespace Youvanna\Shop\Services;

defined('ABSPATH') || exit;

/**
 * AES-256-GCM avec nonce aléatoire 12 bytes (jamais réutilisé).
 * Clé stockée dans yv_shop_encryption_key (générée à l'activation).
 */
final class Encryption
{
    public function encrypt(string $plaintext): string
    {
        $key = $this->key();
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return 'v1:' . base64_encode($nonce) . ':' . base64_encode($ciphertext) . ':' . base64_encode($tag);
    }

    public function decrypt(string $encoded): ?string
    {
        if (!str_starts_with($encoded, 'v1:')) {
            return null;
        }
        $parts = explode(':', $encoded);
        if (count($parts) !== 4) {
            return null;
        }
        $nonce = base64_decode($parts[1], true);
        $ciphertext = base64_decode($parts[2], true);
        $tag = base64_decode($parts[3], true);
        if ($nonce === false || $ciphertext === false || $tag === false) {
            return null;
        }
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag);
        return $plain === false ? null : $plain;
    }

    private function key(): string
    {
        $b64 = (string) get_option('yv_shop_encryption_key', '');
        $key = base64_decode($b64, true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('Encryption key missing or invalid');
        }
        return $key;
    }
}
