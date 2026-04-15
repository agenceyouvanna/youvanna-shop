<?php
namespace Youvanna\Shop\Repositories;

use Youvanna\Shop\Models\Review;

defined('ABSPATH') || exit;

final class ReviewRepository
{
    private \wpdb $wpdb;
    private string $table;
    private string $products_table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'yv_product_reviews';
        $this->products_table = $wpdb->prefix . 'yv_products';
    }

    public function find(int $id): ?Review
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return $row ? Review::fromRow($row) : null;
    }

    /** @return Review[] */
    public function forProduct(int $product_id, string $status = 'approved', int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE product_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $product_id, $status, $limit, $offset), ARRAY_A);
        return array_map([Review::class, 'fromRow'], $rows ?: []);
    }

    public function countForProduct(int $product_id, string $status = 'approved'): int
    {
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE product_id = %d AND status = %s",
            $product_id, $status
        ));
    }

    /** @return Review[] */
    public function all(string $status = '', int $limit = 50, int $offset = 0): array
    {
        if ($status) {
            $rows = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $status, $limit, $offset
            ), ARRAY_A);
        } else {
            $rows = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit, $offset
            ), ARRAY_A);
        }
        return array_map([Review::class, 'fromRow'], $rows ?: []);
    }

    public function countAll(string $status = ''): int
    {
        if ($status) {
            return (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE status = %s", $status
            ));
        }
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }

    public function save(Review $r): int
    {
        $data = [
            'product_id' => $r->product_id,
            'user_id' => $r->user_id,
            'author_name' => $r->author_name,
            'author_email' => $r->author_email,
            'rating' => $r->rating,
            'title' => $r->title,
            'content' => $r->content,
            'status' => $r->status,
            'verified_purchase' => $r->verified_purchase ? 1 : 0,
        ];
        if ($r->id) {
            $this->wpdb->update($this->table, $data, ['id' => $r->id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $data['ip_address'] = $ip ? inet_pton($ip) : null;
            $this->wpdb->insert($this->table, $data);
            $r->id = (int) $this->wpdb->insert_id;
        }
        $this->recomputeProductRating($r->product_id);
        do_action('yv_shop_review_saved', $r);
        return (int) $r->id;
    }

    public function delete(int $id): bool
    {
        $r = $this->find($id);
        $ok = (bool) $this->wpdb->delete($this->table, ['id' => $id]);
        if ($ok && $r) $this->recomputeProductRating($r->product_id);
        return $ok;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $r = $this->find($id);
        $ok = (bool) $this->wpdb->update($this->table, ['status' => $status], ['id' => $id]);
        if ($ok && $r) $this->recomputeProductRating($r->product_id);
        return $ok;
    }

    public function recomputeProductRating(int $product_id): void
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM {$this->table} WHERE product_id = %d AND status = 'approved'",
            $product_id
        ), ARRAY_A);
        $avg = (float) ($row['avg_r'] ?? 0);
        $cnt = (int) ($row['cnt'] ?? 0);
        $this->wpdb->update(
            $this->products_table,
            ['rating_avg' => round($avg, 2), 'rating_count' => $cnt],
            ['id' => $product_id]
        );
    }
}
