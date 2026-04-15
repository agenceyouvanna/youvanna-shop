<?php
/**
 * Plugin Name: Youvanna Shop
 * Plugin URI: https://github.com/agenceyouvanna/youvanna-shop
 * Description: E-commerce léger, performant et réutilisable. Alternative ciblée à WooCommerce.
 * Version: 2.1.1
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * Author: Agence Youvanna
 * Author URI: https://youvanna.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yv-shop
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('YV_SHOP_VERSION', '2.1.1');
define('YV_SHOP_FILE', __FILE__);
define('YV_SHOP_DIR', __DIR__);
define('YV_SHOP_URL', plugin_dir_url(__FILE__));
define('YV_SHOP_BASENAME', plugin_basename(__FILE__));

require_once __DIR__ . '/src/autoload.php';

register_activation_hook(__FILE__, [\Youvanna\Shop\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\Youvanna\Shop\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    \Youvanna\Shop\Plugin::instance()->boot();
}, 5);
