<?php
namespace Youvanna\Shop\Repositories;

use Youvanna\Shop\Models\Coupon;

defined('ABSPATH') || exit;

final class CouponRepository
{
    private \wpdb $wpdb;
    private string $table;
    private string $usages_table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'yv_coupons';
        $this->usages_table = $wpdb->prefix . 'yv_coupon_usages';
    }

    public function find(int $id): ?Coupon
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return $row ? Coupon::fromRow($row) : null;
    }

    public function findByCode(string $code): ?Coupon
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE code = %s LIMIT 1", strtoupper(trim($code))),
            ARRAY_A
        );
        return $row ? Coupon::fromRow($row) : null;
    }

    /** @return Coupon[] */
    public function all(int $limit = 200, int $offset = 0): array
    {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ), ARRAY_A);
        return array_map([Coupon::class, 'fromRow'], $rows ?: []);
    }

    public function countAll(): int
    {
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }

    public function save(Coupon $c): int
    {
        $data = [
            'code' => strtoupper(trim($c->code)),
            'type' => $c->type,
            'amount' => $c->amount,
            'description' => $c->description,
            'minimum_amount' => $c->minimum_amount,
            'maximum_amount' => $c->maximum_amount,
            'individual_use' => $c->individual_use ? 1 : 0,
            'exclude_sale_items' => $c->exclude_sale_items ? 1 : 0,
            'usage_limit' => $c->usage_limit,
            'usage_limit_per_user' => $c->usage_limit_per_user,
            'product_ids' => wp_json_encode($c->product_ids),
            'excluded_product_ids' => wp_json_encode($c->excluded_product_ids),
            'starts_at' => $c->starts_at,
            'expires_at' => $c->expires_at,
        ];
        if ($c->id) {
            $this->wpdb->update($this->table, $data, ['id' => $c->id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $this->wpdb->insert($this->table, $data);
            $c->id = (int) $this->wpdb->insert_id;
        }
        return (int) $c->id;
    }

    public function delete(int $id): bool
    {
        return (bool) $this->wpdb->delete($this->table, ['id' => $id]);
    }

    public function markUsed(int $coupon_id, int $order_id, ?string $email): void
    {
        $this->wpdb->insert($this->usages_table, [
            'coupon_id' => $coupon_id,
            'order_id' => $order_id,
            'customer_email' => $email,
            'used_at' => current_time('mysql'),
        ]);
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table} SET used_count = used_count + 1 WHERE id = %d",
            $coupon_id
        ));
    }

    public function usageCountForEmail(int $coupon_id, string $email): int
    {
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->usages_table} WHERE coupon_id = %d AND customer_email = %s",
            $coupon_id, $email
        ));
    }
}
