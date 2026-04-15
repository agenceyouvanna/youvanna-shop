<?php
namespace Youvanna\Shop\Helpers;

defined('ABSPATH') || exit;

final class Slug
{
    public static function unique(string $base, string $table, string $column = 'slug'): string
    {
        global $wpdb;
        $slug = sanitize_title($base);
        if ($slug === '') {
            $slug = 'produit';
        }
        $original = $slug;
        $i = 1;
        while ($wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$table} WHERE {$column} = %s LIMIT 1", $slug))) {
            $slug = $original . '-' . (++$i);
        }
        return $slug;
    }
}
