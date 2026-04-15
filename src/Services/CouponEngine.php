<?php
namespace Youvanna\Shop\Services;

use Youvanna\Shop\Models\Coupon;
use Youvanna\Shop\Repositories\CouponRepository;

defined('ABSPATH') || exit;

/**
 * Applique les coupons sur un panier.
 * - percent : % de réduction sur le subtotal éligible
 * - fixed_cart : montant fixe sur le subtotal global
 * - fixed_product : montant fixe par unité de produits éligibles
 * - free_shipping : flag used by ShippingResolver
 */
final class CouponEngine
{
    private CouponRepository $repo;

    public function __construct(?CouponRepository $repo = null)
    {
        $this->repo = $repo ?? new CouponRepository();
    }

    /**
     * @param array $items resolved items (product_id, qty, price, line_subtotal, on_sale?)
     * @param array $codes ['CODE1', 'CODE2']
     * @param string|null $customer_email
     * @return array{discount: float, applied: array<int,array{code:string, amount:float, type:string, label:string}>, free_shipping: bool, errors: array<int,array{code:string,reason:string}>}
     */
    public function apply(array $items, array $codes, ?string $customer_email = null, float $subtotal = 0.0): array
    {
        $discount = 0.0;
        $applied = [];
        $free_shipping = false;
        $errors = [];

        if (empty($codes)) {
            return ['discount' => 0.0, 'applied' => [], 'free_shipping' => false, 'errors' => []];
        }

        foreach ($codes as $raw) {
            $code = strtoupper(trim((string) $raw));
            if (!$code) continue;
            $coupon = $this->repo->findByCode($code);
            if (!$coupon) {
                $errors[] = ['code' => $code, 'reason' => 'not_found'];
                continue;
            }
            if (!$coupon->isActive()) {
                $errors[] = ['code' => $code, 'reason' => 'expired_or_depleted'];
                continue;
            }
            if ($coupon->minimum_amount !== null && $subtotal < $coupon->minimum_amount) {
                $errors[] = ['code' => $code, 'reason' => 'below_minimum'];
                continue;
            }
            if ($coupon->maximum_amount !== null && $subtotal > $coupon->maximum_amount) {
                $errors[] = ['code' => $code, 'reason' => 'above_maximum'];
                continue;
            }
            if ($customer_email && $coupon->usage_limit_per_user !== null) {
                $used = $this->repo->usageCountForEmail((int) $coupon->id, $customer_email);
                if ($used >= $coupon->usage_limit_per_user) {
                    $errors[] = ['code' => $code, 'reason' => 'limit_per_user'];
                    continue;
                }
            }
            if ($coupon->individual_use && !empty($applied)) {
                $errors[] = ['code' => $code, 'reason' => 'individual_use'];
                continue;
            }

            $eligible = $this->eligibleItems($items, $coupon);
            if (empty($eligible) && $coupon->type !== 'free_shipping') {
                $errors[] = ['code' => $code, 'reason' => 'no_eligible_products'];
                continue;
            }
            $eligible_subtotal = array_sum(array_map(static fn($i) => (float) $i['line_subtotal'], $eligible));

            $amount = 0.0;
            switch ($coupon->type) {
                case 'percent':
                    $amount = round($eligible_subtotal * ($coupon->amount / 100), 2);
                    break;
                case 'fixed_cart':
                    $amount = min($coupon->amount, $eligible_subtotal);
                    break;
                case 'fixed_product':
                    $units = 0;
                    foreach ($eligible as $i) $units += (int) $i['qty'];
                    $amount = min($coupon->amount * $units, $eligible_subtotal);
                    break;
                case 'free_shipping':
                    $free_shipping = true;
                    break;
            }
            if ($amount > 0) {
                $discount += $amount;
                $applied[] = [
                    'code' => $coupon->code,
                    'amount' => $amount,
                    'type' => $coupon->type,
                    'label' => $coupon->description ?: $coupon->code,
                ];
            } elseif ($coupon->type === 'free_shipping') {
                $applied[] = [
                    'code' => $coupon->code,
                    'amount' => 0.0,
                    'type' => $coupon->type,
                    'label' => $coupon->description ?: $coupon->code,
                ];
            }

            if ($coupon->individual_use) break;
        }

        return [
            'discount' => round($discount, 2),
            'applied' => $applied,
            'free_shipping' => $free_shipping,
            'errors' => $errors,
        ];
    }

    /** @return array */
    private function eligibleItems(array $items, Coupon $coupon): array
    {
        $allow = !empty($coupon->product_ids);
        $deny = !empty($coupon->excluded_product_ids);
        $out = [];
        foreach ($items as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            if ($coupon->exclude_sale_items && !empty($item['on_sale'])) continue;
            if ($allow && !in_array($pid, array_map('intval', $coupon->product_ids), true)) continue;
            if ($deny && in_array($pid, array_map('intval', $coupon->excluded_product_ids), true)) continue;
            $out[] = $item;
        }
        return $out;
    }
}
