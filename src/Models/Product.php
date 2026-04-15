<?php
namespace Youvanna\Shop\Models;

defined('ABSPATH') || exit;

class Product
{
    public ?int $id = null;
    public string $sku = '';
    public string $name = '';
    public string $slug = '';
    public string $type = 'simple';
    public string $status = 'draft';
    public bool $featured = false;
    public ?string $description = null;
    public ?string $short_description = null;
    public float $price = 0.0;
    public ?float $sale_price = null;
    public ?string $sale_from = null;
    public ?string $sale_to = null;
    public ?float $cost_price = null;
    public string $tax_class = 'standard';
    public string $tax_status = 'taxable';
    public bool $manage_stock = false;
    public int $stock_qty = 0;
    public string $stock_status = 'instock';
    public string $backorders = 'no';
    public ?float $weight = null;
    public ?float $length = null;
    public ?float $width = null;
    public ?float $height = null;
    public ?int $image_id = null;
    public array $gallery_ids = [];
    public int $sort_order = 0;
    public float $rating_avg = 0.0;
    public int $rating_count = 0;
    public int $sales_count = 0;
    public ?string $language = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /** @var int[] */
    public array $category_ids = [];
    /** @var int[] */
    public array $tag_ids = [];
    /** @var array<string,int[]> */
    public array $attributes = [];

    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id = isset($row['id']) ? (int) $row['id'] : null;
        $p->sku = (string) ($row['sku'] ?? '');
        $p->name = (string) ($row['name'] ?? '');
        $p->slug = (string) ($row['slug'] ?? '');
        $p->type = (string) ($row['type'] ?? 'simple');
        $p->status = (string) ($row['status'] ?? 'draft');
        $p->featured = !empty($row['featured']);
        $p->description = $row['description'] ?? null;
        $p->short_description = $row['short_description'] ?? null;
        $p->price = (float) ($row['price'] ?? 0);
        $p->sale_price = isset($row['sale_price']) && $row['sale_price'] !== null ? (float) $row['sale_price'] : null;
        $p->sale_from = $row['sale_from'] ?? null;
        $p->sale_to = $row['sale_to'] ?? null;
        $p->cost_price = isset($row['cost_price']) && $row['cost_price'] !== null ? (float) $row['cost_price'] : null;
        $p->tax_class = (string) ($row['tax_class'] ?? 'standard');
        $p->tax_status = (string) ($row['tax_status'] ?? 'taxable');
        $p->manage_stock = !empty($row['manage_stock']);
        $p->stock_qty = (int) ($row['stock_qty'] ?? 0);
        $p->stock_status = (string) ($row['stock_status'] ?? 'instock');
        $p->backorders = (string) ($row['backorders'] ?? 'no');
        $p->weight = isset($row['weight']) ? (float) $row['weight'] : null;
        $p->length = isset($row['length']) ? (float) $row['length'] : null;
        $p->width = isset($row['width']) ? (float) $row['width'] : null;
        $p->height = isset($row['height']) ? (float) $row['height'] : null;
        $p->image_id = isset($row['image_id']) && $row['image_id'] !== null ? (int) $row['image_id'] : null;
        $p->gallery_ids = self::decodeJsonArray($row['gallery_ids'] ?? null);
        $p->sort_order = (int) ($row['sort_order'] ?? 0);
        $p->rating_avg = (float) ($row['rating_avg'] ?? 0);
        $p->rating_count = (int) ($row['rating_count'] ?? 0);
        $p->sales_count = (int) ($row['sales_count'] ?? 0);
        $p->language = $row['language'] ?? null;
        $p->created_at = $row['created_at'] ?? null;
        $p->updated_at = $row['updated_at'] ?? null;
        return $p;
    }

    public function isOnSale(): bool
    {
        if ($this->sale_price === null || $this->sale_price <= 0) {
            return false;
        }
        $now = current_time('mysql');
        if ($this->sale_from && $this->sale_from > $now) {
            return false;
        }
        if ($this->sale_to && $this->sale_to < $now) {
            return false;
        }
        return $this->sale_price < $this->price;
    }

    public function activePrice(): float
    {
        return $this->isOnSale() ? (float) $this->sale_price : $this->price;
    }

    public function isInStock(): bool
    {
        return $this->stock_status === 'instock' || $this->stock_status === 'onbackorder';
    }

    public function permalink(): string
    {
        $base = trim((string) get_option('yv_shop_general_product_slug', 'produit'), '/');
        return home_url('/' . $base . '/' . $this->slug . '/');
    }

    public function imageUrl(string $size = 'medium'): string
    {
        if (!$this->image_id) {
            return '';
        }
        $src = wp_get_attachment_image_src($this->image_id, $size);
        return $src ? (string) $src[0] : '';
    }

    /** @return array<string,mixed> */
    public function toPublicDto(): array
    {
        $dto = [
            'id'                => $this->id,
            'sku'               => $this->sku,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'type'              => $this->type,
            'permalink'         => $this->permalink(),
            'description'       => $this->description ? wp_kses_post($this->description) : null,
            'short_description' => $this->short_description ? wp_kses_post($this->short_description) : null,
            'price'             => $this->price,
            'sale_price'        => $this->sale_price,
            'on_sale'           => $this->isOnSale(),
            'active_price'      => $this->activePrice(),
            'currency'          => \Youvanna\Shop\Helpers\Currency::code(),
            'image'             => $this->image_id ? [
                'id'    => $this->image_id,
                'src'   => $this->imageUrl('large'),
                'thumb' => $this->imageUrl('medium'),
                'alt'   => get_post_meta($this->image_id, '_wp_attachment_image_alt', true) ?: $this->name,
            ] : null,
            'gallery'           => array_values(array_filter(array_map(static function ($id) {
                $src = wp_get_attachment_image_src((int) $id, 'large');
                return $src ? ['id' => (int) $id, 'src' => $src[0]] : null;
            }, $this->gallery_ids))),
            'stock_status'      => $this->stock_status,
            'in_stock'          => $this->isInStock(),
            'stock_qty'         => $this->manage_stock ? $this->stock_qty : null,
            'featured'          => $this->featured,
            'rating'            => ['avg' => $this->rating_avg, 'count' => $this->rating_count],
            'categories'        => $this->category_ids,
            'tags'              => $this->tag_ids,
            'attributes'        => $this->attributes,
        ];
        return apply_filters('yv_shop_product_dto', $dto, $this);
    }

    /** @return array<string,mixed> */
    public function toListDto(): array
    {
        return [
            'id'           => $this->id,
            'sku'          => $this->sku,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'type'         => $this->type,
            'permalink'    => $this->permalink(),
            'price'        => $this->price,
            'sale_price'   => $this->sale_price,
            'on_sale'      => $this->isOnSale(),
            'active_price' => $this->activePrice(),
            'currency'     => \Youvanna\Shop\Helpers\Currency::code(),
            'image'        => $this->image_id ? [
                'thumb' => $this->imageUrl('medium'),
                'alt'   => get_post_meta($this->image_id, '_wp_attachment_image_alt', true) ?: $this->name,
            ] : null,
            'stock_status' => $this->stock_status,
            'in_stock'     => $this->isInStock(),
            'rating_avg'   => $this->rating_avg,
        ];
    }

    private static function decodeJsonArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
