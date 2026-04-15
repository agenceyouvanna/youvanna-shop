<?php
namespace Youvanna\Shop\Repositories;

defined('ABSPATH') || exit;

final class WishlistRepository
{
    private \wpdb $wpdb;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'yv_wishlists';
    }

    /**
     * Owner = [user_id] OR [session_token] (une seule des deux).
     */
    public function add(?int $user_id, ?string $session_token, int $product_id): bool
    {
        if (!$user_id && !$session_token) return false;
        $data = [
            'user_id' => $user_id,
            'session_token' => $user_id ? null : $session_token,
            'product_id' => $product_id,
            'created_at' => current_time('mysql'),
        ];
        $result = $this->wpdb->query($this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->table} (user_id, session_token, product_id, created_at) VALUES (%d, %s, %d, %s)",
            $user_id ?? 0,
            $user_id ? '' : (string) $session_token,
            $product_id,
            $data['created_at']
        ));
        return $result !== false;
    }

    public function remove(?int $user_id, ?string $session_token, int $product_id): bool
    {
        if ($user_id) {
            return (bool) $this->wpdb->delete($this->table, ['user_id' => $user_id, 'product_id' => $product_id]);
        }
        if ($session_token) {
            return (bool) $this->wpdb->delete($this->table, ['session_token' => $session_token, 'product_id' => $product_id]);
        }
        return false;
    }

    /** @return int[] */
    public function productIds(?int $user_id, ?string $session_token): array
    {
        if (!$user_id && !$session_token) return [];
        if ($user_id) {
            $rows = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT product_id FROM {$this->table} WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            ));
        } else {
            $rows = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT product_id FROM {$this->table} WHERE session_token = %s ORDER BY created_at DESC",
                $session_token
            ));
        }
        return array_map('intval', $rows ?: []);
    }

    public function mergeSessionToUser(string $session_token, int $user_id): void
    {
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE IGNORE {$this->table} SET user_id = %d, session_token = NULL WHERE session_token = %s",
            $user_id, $session_token
        ));
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table} WHERE session_token = %s",
            $session_token
        ));
    }
}
