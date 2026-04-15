<?php
namespace Youvanna\Shop;

defined('ABSPATH') || exit;

final class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('yv_shop_cron_daily');
        wp_clear_scheduled_hook('yv_shop_cron_hourly');
        flush_rewrite_rules();
        do_action('yv_shop_deactivated');
    }
}
