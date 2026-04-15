<?php
namespace Youvanna\Shop\Frontend;

defined('ABSPATH') || exit;

final class TemplateLoader
{
    public static function locate(string $name): ?string
    {
        $candidates = [
            get_stylesheet_directory() . '/yv-shop/' . $name,
            get_template_directory() . '/yv-shop/' . $name,
            YV_SHOP_DIR . '/templates/' . $name,
        ];
        foreach ($candidates as $p) {
            if (file_exists($p)) {
                return $p;
            }
        }
        return null;
    }

    public static function render(string $name, array $args = []): void
    {
        $located = self::locate($name);
        if (!$located) {
            return;
        }
        do_action('yv_shop_before_template_' . str_replace(['/', '.'], '_', $name), $args);
        include $located;
        do_action('yv_shop_after_template_' . str_replace(['/', '.'], '_', $name), $args);
    }

    public static function get(string $name, array $args = []): string
    {
        ob_start();
        self::render($name, $args);
        return (string) ob_get_clean();
    }
}
