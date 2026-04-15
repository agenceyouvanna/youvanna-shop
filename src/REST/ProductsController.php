<?php
namespace Youvanna\Shop\REST;

use Youvanna\Shop\Models\SearchCriteria;
use Youvanna\Shop\Repositories\ProductRepository;

defined('ABSPATH') || exit;

final class ProductsController extends Controller
{
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/products', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [$this, 'list'],
            'permission_callback' => [$this, 'permission_public'],
        ]);
        register_rest_route($this->namespace, '/products/(?P<id>\d+)', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get'],
            'permission_callback' => [$this, 'permission_public'],
        ]);
        register_rest_route($this->namespace, '/products/slug/(?P<slug>[a-z0-9-]+)', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getBySlug'],
            'permission_callback' => [$this, 'permission_public'],
        ]);
        register_rest_route($this->namespace, '/products/facets', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [$this, 'facets'],
            'permission_callback' => [$this, 'permission_public'],
        ]);
    }

    public function list(\WP_REST_Request $req): \WP_REST_Response
    {
        $criteria = SearchCriteria::fromRequest($req);
        $repo = new ProductRepository();
        $result = $repo->search($criteria);
        $items = array_map(static fn($p) => $p->toListDto(), $result['items']);
        $response = new \WP_REST_Response([
            'items'       => $items,
            'total'       => $result['total'],
            'total_pages' => $result['total_pages'],
            'page'        => $result['page'],
            'per_page'    => $result['per_page'],
        ]);
        $response->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
        return $response;
    }

    public function get(\WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $repo = new ProductRepository();
        $product = $repo->find($id);
        if (!$product || $product->status !== 'published') {
            return $this->err('not_found', __('Produit introuvable', 'yv-shop'), 404);
        }
        $response = new \WP_REST_Response($product->toPublicDto());
        $response->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=3600');
        return $response;
    }

    public function getBySlug(\WP_REST_Request $req)
    {
        $slug = sanitize_title((string) $req['slug']);
        $repo = new ProductRepository();
        $product = $repo->findBySlug($slug);
        if (!$product) {
            return $this->err('not_found', __('Produit introuvable', 'yv-shop'), 404);
        }
        return new \WP_REST_Response($product->toPublicDto());
    }

    public function facets(\WP_REST_Request $req): \WP_REST_Response
    {
        $repo = new ProductRepository();
        $range = $repo->priceRange();
        // V0: facets simples (catégories root + range prix). On enrichira plus tard.
        $cats = get_terms([
            'taxonomy'   => 'yv_category',
            'hide_empty' => true,
        ]);
        $facets = [
            'price_range' => $range,
            'categories'  => is_array($cats) ? array_map(static fn($t) => [
                'id'    => (int) $t->term_id,
                'name'  => $t->name,
                'slug'  => $t->slug,
                'count' => (int) $t->count,
                'parent'=> (int) $t->parent,
            ], $cats) : [],
        ];
        $response = new \WP_REST_Response($facets);
        $response->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=3600');
        return $response;
    }
}
