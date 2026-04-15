<?php
namespace Youvanna\Shop\Helpers;

defined('ABSPATH') || exit;

final class Currency
{
    public static function format(float $amount, ?string $currency = null): string
    {
        $currency = $currency ?? get_option('yv_shop_general_currency', 'EUR');
        $symbol   = get_option('yv_shop_general_currency_symbol', '€');
        $position = get_option('yv_shop_general_currency_position', 'right_space');
        $thousand = get_option('yv_shop_general_thousand_sep', ' ');
        $decimal  = get_option('yv_shop_general_decimal_sep', ',');
        $decimals = (int) get_option('yv_shop_general_decimals', 2);

        $formatted = number_format($amount, $decimals, $decimal, $thousand);

        return match ($position) {
            'left'        => $symbol . $formatted,
            'left_space'  => $symbol . ' ' . $formatted,
            'right'       => $formatted . $symbol,
            'right_space' => $formatted . ' ' . $symbol,
            default       => $formatted . ' ' . $symbol,
        };
    }

    public static function symbol(?string $currency = null): string
    {
        return get_option('yv_shop_general_currency_symbol', '€');
    }

    public static function code(): string
    {
        return get_option('yv_shop_general_currency', 'EUR');
    }
}
