<?php
namespace Youvanna\Shop\Models;

defined('ABSPATH') || exit;

class Order
{
    public ?int $id = null;
    public string $order_number = '';
    public string $order_key = '';
    public string $status = 'pending';
    public ?int $customer_id = null;
    public string $customer_email = '';
    public ?string $customer_phone = null;
    public ?string $customer_note = null;
    public array $billing = [];
    public array $shipping = [];
    public float $subtotal = 0.0;
    public float $discount_total = 0.0;
    public float $shipping_total = 0.0;
    public float $tax_total = 0.0;
    public float $total = 0.0;
    public string $currency = 'EUR';
    public string $payment_method = '';
    public string $payment_status = 'pending';
    public ?string $payment_reference = null;
    public ?string $shipping_method = null;
    public ?string $shipping_tracking = null;
    public array $coupon_codes = [];
    public ?string $ip_address_bin = null;
    public ?string $user_agent = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $paid_at = null;
    public ?string $shipped_at = null;
    /** @var OrderItem[] */
    public array $items = [];

    public static function fromRow(array $row): self
    {
        $o = new self();
        $o->id = isset($row['id']) ? (int) $row['id'] : null;
        $o->order_number = (string) ($row['order_number'] ?? '');
        $o->order_key = (string) ($row['order_key'] ?? '');
        $o->status = (string) ($row['status'] ?? 'pending');
        $o->customer_id = isset($row['customer_id']) ? (int) $row['customer_id'] : null;
        $o->customer_email = (string) ($row['customer_email'] ?? '');
        $o->customer_phone = $row['customer_phone'] ?? null;
        $o->customer_note = $row['customer_note'] ?? null;
        $o->billing = self::dec($row['billing'] ?? '{}');
        $o->shipping = self::dec($row['shipping'] ?? '{}');
        $o->subtotal = (float) ($row['subtotal'] ?? 0);
        $o->discount_total = (float) ($row['discount_total'] ?? 0);
        $o->shipping_total = (float) ($row['shipping_total'] ?? 0);
        $o->tax_total = (float) ($row['tax_total'] ?? 0);
        $o->total = (float) ($row['total'] ?? 0);
        $o->currency = (string) ($row['currency'] ?? 'EUR');
        $o->payment_method = (string) ($row['payment_method'] ?? '');
        $o->payment_status = (string) ($row['payment_status'] ?? 'pending');
        $o->payment_reference = $row['payment_reference'] ?? null;
        $o->shipping_method = $row['shipping_method'] ?? null;
        $o->shipping_tracking = $row['shipping_tracking'] ?? null;
        $o->coupon_codes = self::dec($row['coupon_codes'] ?? '[]');
        $o->ip_address_bin = $row['ip_address'] ?? null;
        $o->user_agent = $row['user_agent'] ?? null;
        $o->created_at = $row['created_at'] ?? null;
        $o->updated_at = $row['updated_at'] ?? null;
        $o->paid_at = $row['paid_at'] ?? null;
        $o->shipped_at = $row['shipped_at'] ?? null;
        return $o;
    }

    private static function dec($v): array
    {
        if (is_array($v)) {
            return $v;
        }
        $d = json_decode((string) $v, true);
        return is_array($d) ? $d : [];
    }

    public static function generateOrderNumber(): string
    {
        $year = date('Y');
        $rand = strtoupper(bin2hex(random_bytes(4)));
        return "YV-{$year}-{$rand}";
    }

    public static function generateOrderKey(): string
    {
        return bin2hex(random_bytes(24));
    }
}
