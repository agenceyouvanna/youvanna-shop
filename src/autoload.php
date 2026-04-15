<?php
/**
 * Autoloader PSR-4 manuel pour Youvanna\Shop\
 * Évite la dépendance Composer en V0.
 */

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Youvanna\\Shop\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
        require_once $path;
    }
});
