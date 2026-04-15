<?php
namespace Youvanna\Shop\Repositories;

use Youvanna\Shop\Models\Cart;

defined('ABSPATH') || exit;

final class CartRepository
{
    private \wpdb $wpdb;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'yv_carts';
    }

    public function find(string $hash): ?Cart
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE cart_hash = %s LIMIT 1", $hash),
            ARRAY_A
        );
        return $row ? Cart::fromRow($row) : null;
    }

    public function persist(Cart $cart): void
    {
        $now = current_time('mysql');
        $exists = (bool) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT 1 FROM {$this->table} WHERE cart_hash = %s", $cart->hash)
        );
        $data = [
            'cart_hash'      => $cart->hash,
            'session_token'  => $cart->session_token,
            'customer_id'    => $cart->customer_id,
            'customer_email' => $cart->customer_email,
            'items'          => wp_json_encode($cart->items),
            'totals'         => wp_json_encode($cart->totals),
            'coupon_codes'   => wp_json_encode($cart->coupon_codes),
            'currency'       => $cart->currency,
            'updated_at'     => $now,
            'expires_at'     => $cart->expires_at ?? gmdate('Y-m-d H:i:s', time() + (int) get_option('yv_shop_cart_ttl', 7 * DAY_IN_SECONDS)),
        ];
        if ($exists) {
            $this->wpdb->update($this->table, $data, ['cart_hash' => $cart->hash]);
        } else {
            $data['created_at'] = $now;
            $this->wpdb->insert($this->table, $data);
        }
    }

    public function delete(string $hash): void
    {
        $this->wpdb->delete($this->table, ['cart_hash' => $hash]);
    }
}
