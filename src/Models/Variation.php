<?php
namespace Youvanna\Shop\Models;

defined('ABSPATH') || exit;

class Variation
{
    public ?int $id = null;
    public int $product_id = 0;
    public string $sku = '';
    public float $price = 0.0;
    public ?float $sale_price = null;
    public int $stock_qty = 0;
    public string $stock_status = 'instock';
    public ?float $weight = null;
    public ?int $image_id = null;
    public array $attributes = [];
    public int $sort_order = 0;
    public bool $enabled = true;

    public static function fromRow(array $row): self
    {
        $v = new self();
        $v->id = isset($row['id']) ? (int) $row['id'] : null;
        $v->product_id = (int) ($row['product_id'] ?? 0);
        $v->sku = (string) ($row['sku'] ?? '');
        $v->price = (float) ($row['price'] ?? 0);
        $v->sale_price = isset($row['sale_price']) && $row['sale_price'] !== null ? (float) $row['sale_price'] : null;
        $v->stock_qty = (int) ($row['stock_qty'] ?? 0);
        $v->stock_status = (string) ($row['stock_status'] ?? 'instock');
        $v->weight = isset($row['weight']) ? (float) $row['weight'] : null;
        $v->image_id = isset($row['image_id']) && $row['image_id'] !== null ? (int) $row['image_id'] : null;
        $decoded = is_string($row['attributes'] ?? null) ? json_decode($row['attributes'], true) : ($row['attributes'] ?? []);
        $v->attributes = is_array($decoded) ? $decoded : [];
        $v->sort_order = (int) ($row['sort_order'] ?? 0);
        $v->enabled = !empty($row['enabled']);
        return $v;
    }

    public function activePrice(): float
    {
        if ($this->sale_price !== null && $this->sale_price > 0 && $this->sale_price < $this->price) {
            return (float) $this->sale_price;
        }
        return $this->price;
    }

    public function imageUrl(string $size = 'medium'): string
    {
        if (!$this->image_id) return '';
        $src = wp_get_attachment_image_src($this->image_id, $size);
        return $src ? (string) $src[0] : '';
    }

    public function toDto(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'active_price' => $this->activePrice(),
            'stock_qty' => $this->stock_qty,
            'stock_status' => $this->stock_status,
            'in_stock' => $this->stock_status !== 'outofstock',
            'image' => $this->image_id ? ['id' => $this->image_id, 'src' => $this->imageUrl('large'), 'thumb' => $this->imageUrl('medium')] : null,
            'attributes' => $this->attributes,
            'enabled' => $this->enabled,
        ];
    }
}
