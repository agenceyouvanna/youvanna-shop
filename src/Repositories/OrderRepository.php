<?php
namespace Youvanna\Shop\Repositories;

use Youvanna\Shop\Models\Order;
use Youvanna\Shop\Models\OrderItem;

defined('ABSPATH') || exit;

final class OrderRepository
{
    private \wpdb $wpdb;
    private string $table;
    private string $items_table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'yv_orders';
        $this->items_table = $wpdb->prefix . 'yv_order_items';
    }

    public function find(int $id): ?Order
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        if (!$row) {
            return null;
        }
        $order = Order::fromRow($row);
        $this->hydrateItems($order);
        return $order;
    }

    public function findByNumber(string $order_number): ?Order
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE order_number = %s LIMIT 1", $order_number),
            ARRAY_A
        );
        if (!$row) {
            return null;
        }
        $order = Order::fromRow($row);
        $this->hydrateItems($order);
        return $order;
    }

    public function findByPaymentReference(string $ref): ?Order
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE payment_reference = %s LIMIT 1", $ref),
            ARRAY_A
        );
        if (!$row) {
            return null;
        }
        $order = Order::fromRow($row);
        $this->hydrateItems($order);
        return $order;
    }

    public function save(Order $o): int
    {
        $now = current_time('mysql');
        if (!$o->order_number) {
            $o->order_number = Order::generateOrderNumber();
        }
        if (!$o->order_key) {
            $o->order_key = Order::generateOrderKey();
        }
        $data = [
            'order_number'      => $o->order_number,
            'order_key'         => $o->order_key,
            'status'            => $o->status,
            'customer_id'       => $o->customer_id,
            'customer_email'    => $o->customer_email,
            'customer_phone'    => $o->customer_phone,
            'customer_note'     => $o->customer_note,
            'billing'           => wp_json_encode($o->billing),
            'shipping'          => wp_json_encode($o->shipping),
            'subtotal'          => $o->subtotal,
            'discount_total'    => $o->discount_total,
            'shipping_total'    => $o->shipping_total,
            'tax_total'         => $o->tax_total,
            'total'             => $o->total,
            'currency'          => $o->currency,
            'payment_method'    => $o->payment_method,
            'payment_status'    => $o->payment_status,
            'payment_reference' => $o->payment_reference,
            'shipping_method'   => $o->shipping_method,
            'shipping_tracking' => $o->shipping_tracking,
            'coupon_codes'      => wp_json_encode($o->coupon_codes),
            'ip_address'        => $o->ip_address_bin,
            'user_agent'        => $o->user_agent ? mb_substr($o->user_agent, 0, 255) : null,
            'updated_at'        => $now,
            'paid_at'           => $o->paid_at,
            'shipped_at'        => $o->shipped_at,
        ];
        if ($o->id) {
            $this->wpdb->update($this->table, $data, ['id' => $o->id]);
        } else {
            $data['created_at'] = $now;
            $this->wpdb->insert($this->table, $data);
            $o->id = (int) $this->wpdb->insert_id;
        }
        $this->saveItems($o);
        return (int) $o->id;
    }

    private function saveItems(Order $o): void
    {
        if (!$o->id) {
            return;
        }
        $this->wpdb->delete($this->items_table, ['order_id' => $o->id]);
        foreach ($o->items as $item) {
            $this->wpdb->insert($this->items_table, [
                'order_id'      => $o->id,
                'product_id'    => $item->product_id,
                'variation_id'  => $item->variation_id,
                'name'          => $item->name,
                'sku'           => $item->sku,
                'qty'           => $item->qty,
                'price'         => $item->price,
                'line_subtotal' => $item->line_subtotal,
                'line_tax'      => $item->line_tax,
                'line_total'    => $item->line_total,
                'tax_class'     => $item->tax_class,
                'meta'          => wp_json_encode($item->meta),
            ]);
        }
    }

    private function hydrateItems(Order $o): void
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->items_table} WHERE order_id = %d", $o->id),
            ARRAY_A
        );
        $o->items = array_map([OrderItem::class, 'fromRow'], $rows ?: []);
    }

    public function updateStatus(int $id, string $new_status): void
    {
        $current = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT status FROM {$this->table} WHERE id = %d", $id)
        );
        if ($current === null) {
            return;
        }
        $this->wpdb->update($this->table, [
            'status' => $new_status,
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);
        $order = $this->find($id);
        if ($order) {
            do_action('yv_shop_order_status_changed', $order, $current, $new_status);
        }
    }

    public function listForAdmin(int $limit = 50, int $offset = 0, string $status = ''): array
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
        return array_map([Order::class, 'fromRow'], $rows ?: []);
    }
}
