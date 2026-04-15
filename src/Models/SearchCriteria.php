<?php
namespace Youvanna\Shop\Models;

defined('ABSPATH') || exit;

final class SearchCriteria
{
    /** @var int[] */
    public array $category_ids = [];
    /** @var int[] */
    public array $tag_ids = [];
    /** @var array<string,int[]> */
    public array $attributes = [];
    public ?float $min_price = null;
    public ?float $max_price = null;
    public ?bool $in_stock_only = null;
    public ?bool $on_sale_only = null;
    public ?bool $featured_only = null;
    public string $query = '';
    public string $orderby = 'menu_order';
    public string $order = 'asc';
    public int $page = 1;
    public int $per_page = 24;
    public ?string $cursor = null;
    /** @var string[] */
    public array $statuses = ['published'];

    public static function fromRequest(\WP_REST_Request $req): self
    {
        $c = new self();

        $cat = $req->get_param('category');
        if ($cat !== null && $cat !== '') {
            $c->category_ids = self::ints($cat);
        }
        $tag = $req->get_param('tag');
        if ($tag !== null && $tag !== '') {
            $c->tag_ids = self::ints($tag);
        }

        // Attributes : ?attr_color=red,blue
        foreach ($req->get_query_params() as $key => $value) {
            if (str_starts_with((string) $key, 'attr_') && $value !== '') {
                $attr = preg_replace('/[^a-z0-9_]/', '', substr($key, 5));
                if ($attr) {
                    $c->attributes[$attr] = self::ints($value);
                }
            }
        }

        $min = $req->get_param('min_price');
        if ($min !== null && $min !== '') {
            $c->min_price = (float) $min;
        }
        $max = $req->get_param('max_price');
        if ($max !== null && $max !== '') {
            $c->max_price = (float) $max;
        }

        if ($req->get_param('in_stock')) {
            $c->in_stock_only = (bool) $req->get_param('in_stock');
        }
        if ($req->get_param('on_sale')) {
            $c->on_sale_only = (bool) $req->get_param('on_sale');
        }
        if ($req->get_param('featured')) {
            $c->featured_only = (bool) $req->get_param('featured');
        }

        $q = trim((string) $req->get_param('q'));
        if ($q !== '') {
            $c->query = mb_substr($q, 0, 100);
        }

        $orderby = (string) $req->get_param('orderby');
        $allowed_orderby = ['menu_order', 'price', 'sales_count', 'created_at', 'rating_avg', 'name'];
        $c->orderby = in_array($orderby, $allowed_orderby, true) ? $orderby : 'menu_order';

        $order = strtolower((string) $req->get_param('order'));
        $c->order = in_array($order, ['asc', 'desc'], true) ? $order : 'asc';

        $page = max(1, (int) $req->get_param('page'));
        $c->page = min($page, 50); // cap
        $per_page = (int) $req->get_param('per_page');
        $c->per_page = $per_page > 0 ? min($per_page, 100) : (int) get_option('yv_shop_products_per_page', 24);

        $cursor = (string) $req->get_param('cursor');
        if ($cursor !== '') {
            $c->cursor = $cursor;
        }

        return apply_filters('yv_shop_search_criteria', $c, $req);
    }

    private static function ints($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }
        return array_values(array_filter(array_map('intval', explode(',', (string) $value))));
    }
}
