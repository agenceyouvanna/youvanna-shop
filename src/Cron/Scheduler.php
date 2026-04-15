<?php
namespace Youvanna\Shop\Cron;

defined('ABSPATH') || exit;

final class Scheduler
{
    public function register(): void
    {
        add_action('init', [$this, 'maybeSchedule']);
        add_action('yv_shop_cron_daily', [$this, 'runDaily']);
        add_action('yv_shop_cron_hourly', [$this, 'runHourly']);
    }

    public function maybeSchedule(): void
    {
        if (!wp_next_scheduled('yv_shop_cron_daily')) {
            wp_schedule_event(time() + 60, 'daily', 'yv_shop_cron_daily');
        }
        if (!wp_next_scheduled('yv_shop_cron_hourly')) {
            wp_schedule_event(time() + 60, 'hourly', 'yv_shop_cron_hourly');
        }
    }

    public function runDaily(): void
    {
        global $wpdb;
        // Cleanup expired carts
        $wpdb->query("DELETE FROM {$wpdb->prefix}yv_carts WHERE expires_at < NOW()");
        do_action('yv_shop_cron_daily_ran');
    }

    public function runHourly(): void
    {
        global $wpdb;
        // Release expired stock reservations
        $wpdb->query("DELETE FROM {$wpdb->prefix}yv_stock_reservations WHERE expires_at < NOW()");
        do_action('yv_shop_cron_hourly_ran');
    }
}
