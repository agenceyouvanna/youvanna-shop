<?php
namespace Youvanna\Shop\Admin;

use Youvanna\Shop\Models\Product;
use Youvanna\Shop\Models\SearchCriteria;
use Youvanna\Shop\Repositories\ProductRepository;

defined('ABSPATH') || exit;

final class ImportExportPage
{
    private const COLUMNS = [
        'sku' => 'SKU (référence unique du produit)',
        'name' => 'Nom du produit',
        'slug' => 'Slug (URL, ex: mon-produit). Laissé vide = auto.',
        'status' => 'Statut : published, draft, private',
        'type' => 'Type : simple (défaut) ou variable',
        'price' => 'Prix normal',
        'sale_price' => 'Prix soldé (optionnel)',
        'stock_qty' => 'Quantité en stock',
        'manage_stock' => '1 = gérer le stock, 0 = ne pas gérer',
        'stock_status' => 'instock, outofstock, onbackorder',
        'featured' => '1 = mis en avant, 0 = normal',
        'short_description' => 'Description courte (panier, miniatures)',
        'description' => 'Description longue (page produit)',
        'categories' => 'Catégories séparées par |  (ex: T-shirts|Homme)',
        'tags' => 'Tags séparés par | ',
        'image_url' => 'URL de l\'image principale (sera uploadée dans la médiathèque)',
    ];

    public static function render(): void
    {
        if (!current_user_can('yv_shop_manage_products')) {
            wp_die(esc_html__('Accès refusé', 'yv-shop'));
        }

        if (isset($_GET['export']) && check_admin_referer('yv_shop_export_products')) {
            self::exportCsv();
            exit;
        }

        $result = null;
        if (!empty($_POST['yv_shop_import_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yv_shop_import_nonce'])), 'yv_shop_import_products')) {
            if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
                $result = self::importCsv($_FILES['csv']['tmp_name']);
            }
        }

        echo '<div class="wrap yv-admin">';
        echo '<div class="yv-admin-header">';
        echo '<div class="yv-admin-header__title"><h1>' . esc_html__('Import / Export produits', 'yv-shop') . '</h1>';
        echo '<p class="yv-admin-header__subtitle">' . esc_html__('Ajoute ou mets à jour tes produits en masse depuis un fichier CSV. Export complet pour sauvegarde ou édition dans Excel / Google Sheets.', 'yv-shop') . '</p></div>';
        echo '</div>';

        if ($result) {
            $cls = $result['errors'] ? ' yv-admin-notice--error' : '';
            echo '<div class="yv-admin-notice' . $cls . '">';
            echo '<strong>' . esc_html(sprintf(__('Import terminé : %d créés, %d mis à jour, %d ignorés.', 'yv-shop'), $result['created'], $result['updated'], $result['skipped'])) . '</strong>';
            if (!empty($result['errors'])) {
                echo '<ul style="margin-top:8px;margin-bottom:0">';
                foreach (array_slice($result['errors'], 0, 10) as $err) {
                    echo '<li>' . esc_html($err) . '</li>';
                }
                if (count($result['errors']) > 10) {
                    echo '<li><em>' . esc_html(sprintf(__('... et %d autres erreurs.', 'yv-shop'), count($result['errors']) - 10)) . '</em></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }

        echo '<div class="yv-grid yv-grid--main-side">';

        // Main: Import
        echo '<div>';
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Importer un fichier CSV', 'yv-shop') . '</h2></div><div class="yv-card__body">';

        echo '<div class="yv-infobox">';
        echo '<strong>' . esc_html__('Comment ça marche ?', 'yv-shop') . '</strong>';
        echo '<p>' . esc_html__('Ton fichier CSV doit avoir une ligne d\'en-tête avec les noms de colonnes. L\'identifiant pour mettre à jour est le SKU : si un produit existe déjà avec ce SKU, il sera mis à jour. Sinon un nouveau produit est créé.', 'yv-shop') . '</p>';
        echo '<p>' . esc_html__('Séparateur : virgule (,) ou point-virgule (;). Encodage : UTF-8.', 'yv-shop') . '</p>';
        echo '</div>';

        echo '<form method="post" enctype="multipart/form-data" style="margin-top:16px">';
        wp_nonce_field('yv_shop_import_products', 'yv_shop_import_nonce');
        echo '<div class="yv-field">';
        echo '<label class="yv-field__label" for="yv_csv">' . esc_html__('Fichier CSV', 'yv-shop') . '</label>';
        echo '<input type="file" name="csv" id="yv_csv" accept=".csv,text/csv" required class="yv-input">';
        echo '<small class="yv-field__hint">' . esc_html__('Taille max conseillée : 5 Mo (≈ 5000 produits). Au-delà, découpe en plusieurs fichiers.', 'yv-shop') . '</small>';
        echo '</div>';
        echo '<button type="submit" class="yv-btn yv-btn--primary">' . esc_html__('Lancer l\'import', 'yv-shop') . '</button>';
        echo '</form>';

        echo '</div></div>';

        // Column reference card
        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Colonnes acceptées', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<p>' . esc_html__('Liste des colonnes que l\'import reconnaît. Aucune n\'est obligatoire sauf', 'yv-shop') . ' <code>sku</code> ' . esc_html__('et', 'yv-shop') . ' <code>name</code>.</p>';
        echo '<table class="yv-table"><thead><tr><th style="width:180px">' . esc_html__('Colonne', 'yv-shop') . '</th><th>' . esc_html__('Description', 'yv-shop') . '</th></tr></thead><tbody>';
        foreach (self::COLUMNS as $col => $desc) {
            echo '<tr><td><code>' . esc_html($col) . '</code></td><td>' . esc_html($desc) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div></div>';

        echo '</div>';

        // Sidebar: Export + template
        echo '<aside class="yv-sidebar">';

        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Exporter', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<p>' . esc_html__('Télécharge tous tes produits dans un fichier CSV. Utile pour une sauvegarde, ou pour modifier en masse puis réimporter.', 'yv-shop') . '</p>';
        $export_url = wp_nonce_url(admin_url('admin.php?page=yv-shop-import&export=1'), 'yv_shop_export_products');
        echo '<a href="' . esc_url($export_url) . '" class="yv-btn yv-btn--secondary" style="width:100%;justify-content:center"><span class="dashicons dashicons-download"></span>' . esc_html__('Exporter tous les produits', 'yv-shop') . '</a>';
        echo '</div></div>';

        echo '<div class="yv-card"><div class="yv-card__header"><h2>' . esc_html__('Modèle vide', 'yv-shop') . '</h2></div><div class="yv-card__body">';
        echo '<p>' . esc_html__('Télécharge un CSV vide avec juste les en-têtes pour partir de zéro.', 'yv-shop') . '</p>';
        $tpl_url = wp_nonce_url(admin_url('admin.php?page=yv-shop-import&export=1&template=1'), 'yv_shop_export_products');
        echo '<a href="' . esc_url($tpl_url) . '" class="yv-btn yv-btn--ghost" style="width:100%;justify-content:center">' . esc_html__('Télécharger le modèle', 'yv-shop') . '</a>';
        echo '</div></div>';

        echo '</aside>';

        echo '</div>';
        echo '</div>';
    }

    private static function exportCsv(): void
    {
        $template_only = !empty($_GET['template']);
        $filename = $template_only ? 'yv-shop-template.csv' : 'yv-shop-products-' . date('Y-m-d') . '.csv';
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $headers = array_keys(self::COLUMNS);
        fputcsv($out, $headers);

        if ($template_only) {
            fclose($out);
            return;
        }

        $repo = new ProductRepository();
        $page = 1;
        do {
            $c = new SearchCriteria();
            $c->page = $page;
            $c->per_page = 200;
            $c->statuses = ['published', 'draft', 'private'];
            $r = $repo->search($c);
            foreach ($r['items'] as $p) {
                fputcsv($out, self::productToRow($p));
            }
            $page++;
        } while ($page <= $r['total_pages']);

        fclose($out);
    }

    private static function productToRow(Product $p): array
    {
        $cats = self::termNames($p->category_ids);
        $tags = self::termNames($p->tag_ids);
        $img = $p->image_id ? wp_get_attachment_url($p->image_id) : '';
        return [
            $p->sku,
            $p->name,
            $p->slug,
            $p->status,
            $p->type,
            (string) $p->price,
            $p->sale_price !== null ? (string) $p->sale_price : '',
            (string) $p->stock_qty,
            $p->manage_stock ? '1' : '0',
            $p->stock_status,
            $p->featured ? '1' : '0',
            (string) $p->short_description,
            (string) $p->description,
            implode('|', $cats),
            implode('|', $tags),
            (string) $img,
        ];
    }

    private static function termNames(array $ids): array
    {
        $names = [];
        foreach ($ids as $tid) {
            $t = get_term((int) $tid);
            if ($t && !is_wp_error($t)) $names[] = $t->name;
        }
        return $names;
    }

    /** @return array{created:int, updated:int, skipped:int, errors:array<string>} */
    private static function importCsv(string $path): array
    {
        $created = $updated = $skipped = 0;
        $errors = [];

        $raw = file_get_contents($path);
        if ($raw === false) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [__('Impossible de lire le fichier.', 'yv-shop')]];
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $sep = substr_count(strtok($raw, "\n"), ';') > substr_count(strtok($raw, "\n"), ',') ? ';' : ',';

        $fh = fopen($path, 'r');
        if ($fh === false) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [__('Impossible d\'ouvrir le fichier.', 'yv-shop')]];
        }
        $first = fgetcsv($fh, 0, $sep);
        if (!$first) {
            fclose($fh);
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [__('Fichier vide.', 'yv-shop')]];
        }
        $first[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first[0]);
        $headers = array_map(static fn($h) => strtolower(trim((string) $h)), $first);

        $repo = new ProductRepository();
        $line = 1;
        while (($row = fgetcsv($fh, 0, $sep)) !== false) {
            $line++;
            if (count($row) === 1 && trim((string) $row[0]) === '') continue;
            $data = [];
            foreach ($headers as $i => $h) $data[$h] = $row[$i] ?? '';
            $sku = trim((string) ($data['sku'] ?? ''));
            $name = trim((string) ($data['name'] ?? ''));
            if (!$sku || !$name) {
                $skipped++;
                $errors[] = sprintf(__('Ligne %d : sku ou name manquant.', 'yv-shop'), $line);
                continue;
            }
            try {
                $existing = $repo->findBySku($sku);
                $p = $existing ?: new Product();
                $p->sku = $sku;
                $p->name = $name;
                if (isset($data['slug']) && $data['slug'] !== '') $p->slug = sanitize_title((string) $data['slug']);
                if (isset($data['status']) && $data['status'] !== '') $p->status = sanitize_key((string) $data['status']);
                if (isset($data['type']) && $data['type'] !== '') $p->type = sanitize_key((string) $data['type']);
                if (isset($data['price']) && $data['price'] !== '') $p->price = (float) str_replace(',', '.', (string) $data['price']);
                if (isset($data['sale_price']) && $data['sale_price'] !== '') $p->sale_price = (float) str_replace(',', '.', (string) $data['sale_price']);
                if (isset($data['stock_qty']) && $data['stock_qty'] !== '') $p->stock_qty = (int) $data['stock_qty'];
                if (isset($data['manage_stock']) && $data['manage_stock'] !== '') $p->manage_stock = (bool) (int) $data['manage_stock'];
                if (isset($data['stock_status']) && $data['stock_status'] !== '') $p->stock_status = sanitize_key((string) $data['stock_status']);
                if (isset($data['featured']) && $data['featured'] !== '') $p->featured = (bool) (int) $data['featured'];
                if (isset($data['short_description'])) $p->short_description = wp_kses_post((string) $data['short_description']);
                if (isset($data['description'])) $p->description = wp_kses_post((string) $data['description']);

                if (!empty($data['categories'])) {
                    $p->category_ids = self::resolveTerms((string) $data['categories'], 'yv_category');
                }
                if (!empty($data['tags'])) {
                    $p->tag_ids = self::resolveTerms((string) $data['tags'], 'yv_tag');
                }

                if (!empty($data['image_url']) && !$p->image_id) {
                    $att_id = self::sideloadImage(trim((string) $data['image_url']));
                    if ($att_id) $p->image_id = $att_id;
                }

                $repo->save($p);
                if ($existing) { $updated++; } else { $created++; }
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = sprintf(__('Ligne %d : %s', 'yv-shop'), $line, $e->getMessage());
            }
        }
        fclose($fh);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** @return int[] */
    private static function resolveTerms(string $joined, string $taxonomy): array
    {
        $ids = [];
        foreach (array_filter(array_map('trim', explode('|', $joined))) as $name) {
            $term = term_exists($name, $taxonomy);
            if (!$term) {
                $term = wp_insert_term($name, $taxonomy);
            }
            if (is_array($term) && !empty($term['term_id'])) {
                $ids[] = (int) $term['term_id'];
            }
        }
        return $ids;
    }

    private static function sideloadImage(string $url): ?int
    {
        if (!$url) return null;
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) return null;
        $file = ['name' => basename(parse_url($url, PHP_URL_PATH) ?: 'image.jpg'), 'tmp_name' => $tmp];
        $id = media_handle_sideload($file, 0);
        if (is_wp_error($id)) {
            @unlink($tmp);
            return null;
        }
        return (int) $id;
    }
}
