<?php
namespace Youvanna\Shop\Repositories;

use Youvanna\Shop\Models\Variation;

defined('ABSPATH') || exit;

final class VariationRepository
{
    private \wpdb $wpdb;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'yv_product_variations';
    }

    public function find(int $id): ?Variation
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return $row ? Variation::fromRow($row) : null;
    }

    /** @return Variation[] */
    public function forProduct(int $product_id, bool $enabled_only = false): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE product_id = %d";
        if ($enabled_only) $sql .= " AND enabled = 1";
        $sql .= " ORDER BY sort_order ASC, id ASC";
        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $product_id), ARRAY_A);
        return array_map([Variation::class, 'fromRow'], $rows ?: []);
    }

    public function save(Variation $v): int
    {
        $data = [
            'product_id' => $v->product_id,
            'sku' => $v->sku,
            'price' => $v->price,
            'sale_price' => $v->sale_price,
            'stock_qty' => $v->stock_qty,
            'stock_status' => $v->stock_status,
            'weight' => $v->weight,
            'image_id' => $v->image_id,
            'attributes' => wp_json_encode($v->attributes),
            'sort_order' => $v->sort_order,
            'enabled' => $v->enabled ? 1 : 0,
        ];
        if ($v->id) {
            $this->wpdb->update($this->table, $data, ['id' => $v->id]);
        } else {
            $this->wpdb->insert($this->table, $data);
            $v->id = (int) $this->wpdb->insert_id;
        }
        do_action('yv_shop_variation_saved', $v);
        return (int) $v->id;
    }

    public function delete(int $id): bool
    {
        return (bool) $this->wpdb->delete($this->table, ['id' => $id]);
    }

    public function deleteForProduct(int $product_id): int
    {
        return (int) $this->wpdb->delete($this->table, ['product_id' => $product_id]);
    }

    public function decreaseStock(int $variation_id, int $qty): bool
    {
        $sql = "UPDATE {$this->table}
                SET stock_qty = stock_qty - %d
                WHERE id = %d AND stock_qty >= %d";
        return $this->wpdb->query($this->wpdb->prepare($sql, $qty, $variation_id, $qty)) === 1;
    }
}
