<?php
namespace Youvanna\Shop\Models;

defined('ABSPATH') || exit;

class Coupon
{
    public ?int $id = null;
    public string $code = '';
    public string $type = 'percent';
    public float $amount = 0.0;
    public ?string $description = null;
    public ?float $minimum_amount = null;
    public ?float $maximum_amount = null;
    public bool $individual_use = false;
    public bool $exclude_sale_items = false;
    public ?int $usage_limit = null;
    public ?int $usage_limit_per_user = null;
    public int $used_count = 0;
    public array $product_ids = [];
    public array $excluded_product_ids = [];
    public ?string $starts_at = null;
    public ?string $expires_at = null;
    public ?string $created_at = null;

    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id = isset($row['id']) ? (int) $row['id'] : null;
        $c->code = (string) ($row['code'] ?? '');
        $c->type = (string) ($row['type'] ?? 'percent');
        $c->amount = (float) ($row['amount'] ?? 0);
        $c->description = $row['description'] ?? null;
        $c->minimum_amount = isset($row['minimum_amount']) && $row['minimum_amount'] !== null ? (float) $row['minimum_amount'] : null;
        $c->maximum_amount = isset($row['maximum_amount']) && $row['maximum_amount'] !== null ? (float) $row['maximum_amount'] : null;
        $c->individual_use = !empty($row['individual_use']);
        $c->exclude_sale_items = !empty($row['exclude_sale_items']);
        $c->usage_limit = isset($row['usage_limit']) && $row['usage_limit'] !== null ? (int) $row['usage_limit'] : null;
        $c->usage_limit_per_user = isset($row['usage_limit_per_user']) && $row['usage_limit_per_user'] !== null ? (int) $row['usage_limit_per_user'] : null;
        $c->used_count = (int) ($row['used_count'] ?? 0);
        $c->product_ids = self::jsonArray($row['product_ids'] ?? null);
        $c->excluded_product_ids = self::jsonArray($row['excluded_product_ids'] ?? null);
        $c->starts_at = $row['starts_at'] ?? null;
        $c->expires_at = $row['expires_at'] ?? null;
        $c->created_at = $row['created_at'] ?? null;
        return $c;
    }

    public function isActive(): bool
    {
        $now = current_time('mysql');
        if ($this->starts_at && $this->starts_at > $now) return false;
        if ($this->expires_at && $this->expires_at < $now) return false;
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) return false;
        return true;
    }

    private static function jsonArray($val): array
    {
        if (is_array($val)) return $val;
        if (!is_string($val) || $val === '') return [];
        $d = json_decode($val, true);
        return is_array($d) ? $d : [];
    }
}
