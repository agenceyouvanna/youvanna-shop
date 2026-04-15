<?php
namespace Youvanna\Shop\Models;

defined('ABSPATH') || exit;

final class Cart
{
    public string $hash = '';
    public string $session_token = '';
    public ?int $customer_id = null;
    public ?string $customer_email = null;
    public array $items = [];
    public array $totals = [];
    public array $coupon_codes = [];
    public string $currency = 'EUR';
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $expires_at = null;

    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->hash = (string) ($row['cart_hash'] ?? '');
        $c->session_token = (string) ($row['session_token'] ?? '');
        $c->customer_id = isset($row['customer_id']) ? (int) $row['customer_id'] : null;
        $c->customer_email = $row['customer_email'] ?? null;
        $c->items = self::decodeJson($row['items'] ?? '[]');
        $c->totals = self::decodeJson($row['totals'] ?? '[]');
        $c->coupon_codes = self::decodeJson($row['coupon_codes'] ?? '[]');
        $c->currency = (string) ($row['currency'] ?? 'EUR');
        $c->created_at = $row['created_at'] ?? null;
        $c->updated_at = $row['updated_at'] ?? null;
        $c->expires_at = $row['expires_at'] ?? null;
        return $c;
    }

    private static function decodeJson($v): array
    {
        if (is_array($v)) {
            return $v;
        }
        $d = json_decode((string) $v, true);
        return is_array($d) ? $d : [];
    }
}
