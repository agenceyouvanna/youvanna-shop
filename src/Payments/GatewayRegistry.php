<?php
namespace Youvanna\Shop\Payments;

defined('ABSPATH') || exit;

final class GatewayRegistry
{
    /** @var GatewayInterface[]|null */
    private static ?array $cache = null;

    /** @return GatewayInterface[] */
    public function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = [
            new BankTransferGateway(),
            new StripeGateway(),
        ];
        $gateways = apply_filters('yv_shop_payment_gateways', $defaults);
        $enabled = (array) get_option('yv_shop_payment_methods_enabled', ['bank_transfer']);
        $out = [];
        foreach ($gateways as $g) {
            if ($g instanceof GatewayInterface && in_array($g->id(), $enabled, true) && $g->isAvailable()) {
                $out[$g->id()] = $g;
            }
        }
        return self::$cache = $out;
    }

    public function get(string $id): ?GatewayInterface
    {
        $all = $this->all();
        return $all[$id] ?? null;
    }
}
