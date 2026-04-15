<?php
namespace Youvanna\Shop\Models;

defined('ABSPATH') || exit;

class OrderItem
{
    public ?int $id = null;
    public ?int $order_id = null;
    public int $product_id = 0;
    public ?int $variation_id = null;
    public string $name = '';
    public string $sku = '';
    public int $qty = 1;
    public float $price = 0.0;
    public float $line_subtotal = 0.0;
    public float $line_tax = 0.0;
    public float $line_total = 0.0;
    public string $tax_class = 'standard';
    public array $meta = [];

    public static function fromRow(array $row): self
    {
        $o = new self();
        $o->id = isset($row['id']) ? (int) $row['id'] : null;
        $o->order_id = isset($row['order_id']) ? (int) $row['order_id'] : null;
        $o->product_id = (int) ($row['product_id'] ?? 0);
        $o->variation_id = isset($row['variation_id']) ? (int) $row['variation_id'] : null;
        $o->name = (string) ($row['name'] ?? '');
        $o->sku = (string) ($row['sku'] ?? '');
        $o->qty = (int) ($row['qty'] ?? 1);
        $o->price = (float) ($row['price'] ?? 0);
        $o->line_subtotal = (float) ($row['line_subtotal'] ?? 0);
        $o->line_tax = (float) ($row['line_tax'] ?? 0);
        $o->line_total = (float) ($row['line_total'] ?? 0);
        $o->tax_class = (string) ($row['tax_class'] ?? 'standard');
        $meta = $row['meta'] ?? null;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $o->meta = is_array($decoded) ? $decoded : [];
        } elseif (is_array($meta)) {
            $o->meta = $meta;
        }
        return $o;
    }
}
