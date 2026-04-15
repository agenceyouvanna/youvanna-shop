<?php
namespace Youvanna\Shop\CLI;

use Youvanna\Shop\Models\Product;
use Youvanna\Shop\Repositories\ProductRepository;

defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Commandes CLI Youvanna Shop.
 */
final class Commands
{
    /**
     * Importe des produits depuis un fichier CSV.
     *
     * ## OPTIONS
     *
     * <file>
     * : Chemin vers le fichier CSV.
     *
     * [--delimiter=<delimiter>]
     * : Délimiteur du CSV. Défaut ","
     *
     * [--dry-run]
     * : N'écrit rien, affiche juste ce qui serait importé.
     *
     * ## EXAMPLES
     *
     *     wp yv-shop import-products ./products.csv
     *     wp yv-shop import-products ./products.csv --dry-run
     *
     * CSV attendu : sku,name,slug,status,price,sale_price,stock_qty,manage_stock,short_description,description,categories,image_url
     * categories = liste pipe-delimitée (ex: "Femme|Robes").
     *
     * @when after_wp_load
     */
    public function import_products(array $args, array $assoc): void
    {
        [$file] = $args;
        if (!file_exists($file)) {
            \WP_CLI::error("Fichier introuvable : {$file}");
        }
        $delim = $assoc['delimiter'] ?? ',';
        $dry = !empty($assoc['dry-run']);

        $fh = fopen($file, 'r');
        if (!$fh) {
            \WP_CLI::error("Impossible d'ouvrir le fichier.");
        }
        $header = fgetcsv($fh, 0, $delim);
        if (!$header) {
            \WP_CLI::error("CSV vide.");
        }
        $header = array_map('trim', $header);

        $repo = new ProductRepository();
        $n = 0; $created = 0; $updated = 0; $errors = 0;

        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            $n++;
            $r = array_combine($header, $row);
            if (!$r) {
                $errors++;
                continue;
            }
            $sku = trim((string) ($r['sku'] ?? ''));
            $existing = $sku ? $repo->findBySku($sku) : null;
            $product = $existing ?? new Product();
            $product->sku = $sku;
            $product->name = (string) ($r['name'] ?? '');
            if (!empty($r['slug'])) {
                $product->slug = sanitize_title((string) $r['slug']);
            }
            $product->status = (string) ($r['status'] ?? 'published');
            $product->price = (float) ($r['price'] ?? 0);
            $product->sale_price = !empty($r['sale_price']) ? (float) $r['sale_price'] : null;
            $product->manage_stock = !empty($r['manage_stock']);
            $product->stock_qty = (int) ($r['stock_qty'] ?? 0);
            $product->stock_status = $product->stock_qty > 0 ? 'instock' : 'outofstock';
            $product->short_description = $r['short_description'] ?? null;
            $product->description = $r['description'] ?? null;

            if (!empty($r['categories'])) {
                $cat_names = array_filter(array_map('trim', explode('|', (string) $r['categories'])));
                $ids = [];
                foreach ($cat_names as $name) {
                    $term = get_term_by('name', $name, 'yv_category');
                    if (!$term) {
                        $new = wp_insert_term($name, 'yv_category');
                        if (!is_wp_error($new)) {
                            $ids[] = (int) $new['term_id'];
                        }
                    } else {
                        $ids[] = (int) $term->term_id;
                    }
                }
                $product->category_ids = $ids;
            }

            if (!empty($r['image_url']) && !$product->image_id) {
                $attach_id = $this->sideloadImage((string) $r['image_url'], $product->name);
                if ($attach_id) {
                    $product->image_id = $attach_id;
                }
            }

            if ($dry) {
                \WP_CLI::log("[DRY] " . ($existing ? 'UPDATE' : 'CREATE') . " : {$product->sku} - {$product->name}");
                if ($existing) { $updated++; } else { $created++; }
                continue;
            }

            $id = $repo->save($product);
            if ($id) {
                if ($existing) { $updated++; } else { $created++; }
                \WP_CLI::log(sprintf("#%d %s - %s (%s)", $n, $existing ? 'UPD' : 'NEW', $product->name, $product->sku));
            } else {
                $errors++;
            }
        }
        fclose($fh);

        \WP_CLI::success(sprintf("Terminé : %d créés, %d mis à jour, %d erreurs (total lignes: %d)", $created, $updated, $errors, $n));
    }

    /**
     * Diagnostics de santé du shop.
     *
     * @when after_wp_load
     */
    public function diagnose(array $args, array $assoc): void
    {
        global $wpdb;

        $checks = [];

        $tables = ['yv_products', 'yv_orders', 'yv_carts', 'yv_stock_reservations', 'yv_tax_rates'];
        foreach ($tables as $t) {
            $full = $wpdb->prefix . $t;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full}'");
            $checks[] = [$t, $exists ? 'OK' : 'MISSING'];
        }

        $checks[] = ['encryption_key', get_option('yv_shop_encryption_key') ? 'OK' : 'MISSING'];
        $checks[] = ['cart_hmac_secret', get_option('yv_shop_cart_hmac_secret') ? 'OK' : 'MISSING'];
        $checks[] = ['cart_page_id', get_option('yv_shop_cart_page_id') ? 'OK' : 'MISSING'];
        $checks[] = ['checkout_page_id', get_option('yv_shop_checkout_page_id') ? 'OK' : 'MISSING'];

        \WP_CLI\Utils\format_items('table', array_map(fn($c) => ['check' => $c[0], 'status' => $c[1]], $checks), ['check', 'status']);
    }

    /**
     * Purge les réservations de stock expirées (normalement fait par cron).
     *
     * @when after_wp_load
     */
    public function cleanup_reservations(array $args, array $assoc): void
    {
        global $wpdb;
        $deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}yv_stock_reservations WHERE expires_at < NOW()");
        \WP_CLI::success("{$deleted} réservations expirées supprimées.");
    }

    private function sideloadImage(string $url, string $desc): ?int
    {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $id = media_sideload_image($url, 0, $desc, 'id');
        return is_wp_error($id) ? null : (int) $id;
    }
}
