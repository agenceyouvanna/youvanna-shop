<?php
namespace Youvanna\Shop\REST;

use Youvanna\Shop\Repositories\ProductRepository;
use Youvanna\Shop\Repositories\WishlistRepository;

defined('ABSPATH') || exit;

final class WishlistController extends Controller
{
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/wishlist', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get'],
                'permission_callback' => [$this, 'permission_public'],
            ],
        ]);
        register_rest_route($this->namespace, '/wishlist/(?P<product_id>\d+)', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'add'],
                'permission_callback' => [$this, 'permission_public'],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'remove'],
                'permission_callback' => [$this, 'permission_public'],
            ],
        ]);
    }

    public function get(\WP_REST_Request $req)
    {
        if (!$this->checkOrigin($req)) return $this->err('forbidden', 'Origine non autorisée', 403);
        $uid = get_current_user_id() ?: null;
        $token = $uid ? null : $this->sessionToken();
        $repo = new WishlistRepository();
        $ids = $repo->productIds($uid, $token);
        $products = new ProductRepository();
        $out = [];
        foreach ($ids as $pid) {
            $p = $products->find($pid);
            if ($p && $p->status === 'published') $out[] = $p->toListDto();
        }
        return rest_ensure_response(['items' => $out, 'count' => count($out)]);
    }

    public function add(\WP_REST_Request $req)
    {
        if (!$this->checkOrigin($req)) return $this->err('forbidden', 'Origine non autorisée', 403);
        $uid = get_current_user_id() ?: null;
        $token = $uid ? null : $this->sessionToken();
        $ok = (new WishlistRepository())->add($uid, $token, (int) $req['product_id']);
        return rest_ensure_response(['added' => $ok]);
    }

    public function remove(\WP_REST_Request $req)
    {
        if (!$this->checkOrigin($req)) return $this->err('forbidden', 'Origine non autorisée', 403);
        $uid = get_current_user_id() ?: null;
        $token = $uid ? null : $this->sessionToken();
        $ok = (new WishlistRepository())->remove($uid, $token, (int) $req['product_id']);
        return rest_ensure_response(['removed' => $ok]);
    }
}
