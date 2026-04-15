<?php
namespace Youvanna\Shop\Repositories;

use Youvanna\Shop\Helpers\Slug;
use Youvanna\Shop\Models\Product;
use Youvanna\Shop\Models\SearchCriteria;

defined('ABSPATH') || exit;

final class ProductRepository
{
    private \wpdb $wpdb;
    private string $table;
    private string $terms_table;
    private string $variations_table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'yv_products';
        $this->terms_table = $wpdb->prefix . 'yv_product_terms';
        $this->variations_table = $wpdb->prefix . 'yv_product_variations';
    }

    public function find(int $id): ?Product
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        if (!$row) {
            return null;
        }
        $product = Product::fromRow($row);
        $this->hydrateTerms([$product]);
        return $product;
    }

    public function findBySlug(string $slug): ?Product
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE slug = %s AND status = 'published'", $slug),
            ARRAY_A
        );
        if (!$row) {
            return null;
        }
        $product = Product::fromRow($row);
        $this->hydrateTerms([$product]);
        return $product;
    }

    public function findBySku(string $sku): ?Product
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE sku = %s LIMIT 1", $sku),
            ARRAY_A
        );
        return $row ? Product::fromRow($row) : null;
    }

    /**
     * @return array{items: Product[], total: int, total_pages: int, page: int, per_page: int}
     */
    public function search(SearchCriteria $c): array
    {
        [$where, $params, $joins] = $this->buildWhere($c);
        $orderby = $this->buildOrderBy($c);

        $offset = max(0, ($c->page - 1) * $c->per_page);
        $limit = $c->per_page;

        $params_with_pagination = array_merge($params, [$limit, $offset]);

        $sql_select = "SELECT DISTINCT p.* FROM {$this->table} p {$joins} WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $sql_count  = "SELECT COUNT(DISTINCT p.id) FROM {$this->table} p {$joins} WHERE {$where}";

        $rows = $this->wpdb->get_results(
            $params_with_pagination ? $this->wpdb->prepare($sql_select, $params_with_pagination) : $sql_select,
            ARRAY_A
        );
        $total = (int) ($params ? $this->wpdb->get_var($this->wpdb->prepare($sql_count, $params)) : $this->wpdb->get_var($sql_count));

        $items = array_map([Product::class, 'fromRow'], $rows ?: []);
        $this->hydrateTerms($items);

        return [
            'items'       => $items,
            'total'       => $total,
            'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
            'page'        => $c->page,
            'per_page'    => $c->per_page,
        ];
    }

    private function buildWhere(SearchCriteria $c): array
    {
        $clauses = [];
        $params = [];
        $joins = '';

        // Statuses
        if (!empty($c->statuses)) {
            $placeholders = implode(',', array_fill(0, count($c->statuses), '%s'));
            $clauses[] = "p.status IN ({$placeholders})";
            $params = array_merge($params, $c->statuses);
        }

        if ($c->in_stock_only) {
            $clauses[] = "p.stock_status IN ('instock','onbackorder')";
        }
        if ($c->featured_only) {
            $clauses[] = 'p.featured = 1';
        }
        if ($c->on_sale_only) {
            $clauses[] = '(p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price)';
        }

        if ($c->min_price !== null) {
            $clauses[] = 'p.price >= %f';
            $params[] = $c->min_price;
        }
        if ($c->max_price !== null) {
            $clauses[] = 'p.price <= %f';
            $params[] = $c->max_price;
        }

        if ($c->query !== '') {
            $clauses[] = '(MATCH(p.name, p.description, p.short_description) AGAINST(%s IN NATURAL LANGUAGE MODE) OR p.name LIKE %s)';
            $params[] = $c->query;
            $params[] = '%' . $this->wpdb->esc_like($c->query) . '%';
        }

        // Categories
        if (!empty($c->category_ids)) {
            $alias = 'pt_cat';
            $joins .= " INNER JOIN {$this->terms_table} {$alias} ON {$alias}.product_id = p.id AND {$alias}.taxonomy = 'yv_category'";
            $placeholders = implode(',', array_fill(0, count($c->category_ids), '%d'));
            $clauses[] = "{$alias}.term_id IN ({$placeholders})";
            $params = array_merge($params, $c->category_ids);
        }

        // Tags
        if (!empty($c->tag_ids)) {
            $alias = 'pt_tag';
            $joins .= " INNER JOIN {$this->terms_table} {$alias} ON {$alias}.product_id = p.id AND {$alias}.taxonomy = 'yv_tag'";
            $placeholders = implode(',', array_fill(0, count($c->tag_ids), '%d'));
            $clauses[] = "{$alias}.term_id IN ({$placeholders})";
            $params = array_merge($params, $c->tag_ids);
        }

        // Attributes (each attribute = INNER JOIN dedicated)
        $i = 0;
        foreach ($c->attributes as $attr_key => $term_ids) {
            if (empty($term_ids)) {
                continue;
            }
            $alias = 'pt_attr_' . $i++;
            $tax = 'yv_attr_' . $attr_key;
            $joins .= " INNER JOIN {$this->terms_table} {$alias} ON {$alias}.product_id = p.id AND {$alias}.taxonomy = %s";
            $params[] = $tax;
            $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
            $clauses[] = "{$alias}.term_id IN ({$placeholders})";
            $params = array_merge($params, $term_ids);
        }

        $where = empty($clauses) ? '1=1' : implode(' AND ', $clauses);
        return [$where, $params, $joins];
    }

    private function buildOrderBy(SearchCriteria $c): string
    {
        $col_map = [
            'menu_order'  => 'p.sort_order',
            'price'       => 'p.price',
            'sales_count' => 'p.sales_count',
            'created_at'  => 'p.created_at',
            'rating_avg'  => 'p.rating_avg',
            'name'        => 'p.name',
        ];
        $col = $col_map[$c->orderby] ?? 'p.sort_order';
        $dir = strtolower($c->order) === 'desc' ? 'DESC' : 'ASC';
        return "{$col} {$dir}, p.id ASC";
    }

    /** @param Product[] $products */
    private function hydrateTerms(array $products): void
    {
        if (empty($products)) {
            return;
        }
        $ids = array_filter(array_map(static fn($p) => $p->id, $products));
        if (empty($ids)) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT product_id, term_id, taxonomy FROM {$this->terms_table} WHERE product_id IN ({$placeholders})",
                $ids
            ),
            ARRAY_A
        );
        $by_product = [];
        foreach ($rows ?: [] as $r) {
            $by_product[(int) $r['product_id']][] = $r;
        }
        foreach ($products as $p) {
            $entries = $by_product[(int) $p->id] ?? [];
            $cats = $tags = [];
            $attrs = [];
            foreach ($entries as $e) {
                $tax = (string) $e['taxonomy'];
                $tid = (int) $e['term_id'];
                if ($tax === 'yv_category') {
                    $cats[] = $tid;
                } elseif ($tax === 'yv_tag') {
                    $tags[] = $tid;
                } elseif (str_starts_with($tax, 'yv_attr_')) {
                    $attrs[substr($tax, 8)][] = $tid;
                }
            }
            $p->category_ids = $cats;
            $p->tag_ids = $tags;
            $p->attributes = $attrs;
        }
    }

    public function save(Product $p): int
    {
        $now = current_time('mysql');
        if (!$p->slug) {
            $p->slug = Slug::unique($p->name, $this->table);
        }
        $data = [
            'sku'               => $p->sku,
            'name'              => $p->name,
            'slug'              => $p->slug,
            'type'              => $p->type,
            'status'            => $p->status,
            'featured'          => $p->featured ? 1 : 0,
            'description'       => $p->description,
            'short_description' => $p->short_description,
            'price'             => $p->price,
            'sale_price'        => $p->sale_price,
            'sale_from'         => $p->sale_from,
            'sale_to'           => $p->sale_to,
            'cost_price'        => $p->cost_price,
            'tax_class'         => $p->tax_class,
            'tax_status'        => $p->tax_status,
            'manage_stock'      => $p->manage_stock ? 1 : 0,
            'stock_qty'         => $p->stock_qty,
            'stock_status'      => $p->stock_status,
            'backorders'        => $p->backorders,
            'weight'            => $p->weight,
            'length'            => $p->length,
            'width'             => $p->width,
            'height'            => $p->height,
            'image_id'          => $p->image_id,
            'gallery_ids'       => wp_json_encode($p->gallery_ids),
            'sort_order'        => $p->sort_order,
            'language'          => $p->language,
            'updated_at'        => $now,
        ];
        if ($p->id) {
            $this->wpdb->update($this->table, $data, ['id' => $p->id]);
        } else {
            $data['created_at'] = $now;
            $this->wpdb->insert($this->table, $data);
            $p->id = (int) $this->wpdb->insert_id;
        }
        $this->saveTerms($p);
        do_action('yv_shop_product_saved', $p);
        return (int) $p->id;
    }

    private function saveTerms(Product $p): void
    {
        if (!$p->id) {
            return;
        }
        $this->wpdb->delete($this->terms_table, ['product_id' => $p->id]);
        $rows = [];
        foreach ($p->category_ids as $tid) {
            $rows[] = ['product_id' => $p->id, 'term_id' => (int) $tid, 'taxonomy' => 'yv_category'];
        }
        foreach ($p->tag_ids as $tid) {
            $rows[] = ['product_id' => $p->id, 'term_id' => (int) $tid, 'taxonomy' => 'yv_tag'];
        }
        foreach ($p->attributes as $key => $term_ids) {
            $tax = 'yv_attr_' . preg_replace('/[^a-z0-9_]/', '', (string) $key);
            foreach ((array) $term_ids as $tid) {
                $rows[] = ['product_id' => $p->id, 'term_id' => (int) $tid, 'taxonomy' => $tax];
            }
        }
        foreach ($rows as $row) {
            $this->wpdb->insert($this->terms_table, $row);
        }
    }

    public function delete(int $id): bool
    {
        $this->wpdb->delete($this->terms_table, ['product_id' => $id]);
        $this->wpdb->delete($this->variations_table, ['product_id' => $id]);
        $deleted = $this->wpdb->delete($this->table, ['id' => $id]);
        if ($deleted) {
            do_action('yv_shop_product_deleted', $id);
            return true;
        }
        return false;
    }

    /**
     * Atomic stock decrease. Returns true if stock was decreased.
     */
    public function decreaseStock(int $product_id, int $qty): bool
    {
        $sql = "UPDATE {$this->table}
                SET stock_qty = stock_qty - %d, updated_at = NOW()
                WHERE id = %d AND manage_stock = 1 AND stock_qty >= %d";
        $result = $this->wpdb->query($this->wpdb->prepare($sql, $qty, $product_id, $qty));
        return $result === 1;
    }

    public function increaseSalesCount(int $product_id, int $qty): void
    {
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table} SET sales_count = sales_count + %d WHERE id = %d",
            $qty,
            $product_id
        ));
    }

    /** @return array{min: float, max: float} */
    public function priceRange(?SearchCriteria $context = null): array
    {
        $row = $this->wpdb->get_row(
            "SELECT MIN(price) AS min_p, MAX(price) AS max_p FROM {$this->table} WHERE status = 'published'",
            ARRAY_A
        );
        return [
            'min' => (float) ($row['min_p'] ?? 0),
            'max' => (float) ($row['max_p'] ?? 0),
        ];
    }
}
