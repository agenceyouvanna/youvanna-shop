<?php
namespace Youvanna\Shop\REST;

use Youvanna\Shop\Models\Review;
use Youvanna\Shop\Repositories\ReviewRepository;

defined('ABSPATH') || exit;

final class ReviewsController extends Controller
{
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/products/(?P<id>\d+)/reviews', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'list'],
                'permission_callback' => [$this, 'permission_public'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submit'],
                'permission_callback' => [$this, 'permission_public'],
            ],
        ]);
        register_rest_route($this->namespace, '/reviews', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'admin_list'],
            'permission_callback' => [$this, 'permission_manage_orders'],
        ]);
        register_rest_route($this->namespace, '/reviews/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'admin_update'],
                'permission_callback' => [$this, 'permission_manage_orders'],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'admin_delete'],
                'permission_callback' => [$this, 'permission_manage_orders'],
            ],
        ]);
    }

    public function list(\WP_REST_Request $req)
    {
        $pid = (int) $req['id'];
        $page = max(1, (int) $req->get_param('page'));
        $per_page = min(50, max(1, (int) ($req->get_param('per_page') ?: 10)));
        $offset = ($page - 1) * $per_page;
        $repo = new ReviewRepository();
        $items = $repo->forProduct($pid, 'approved', $per_page, $offset);
        return rest_ensure_response([
            'items' => array_map(static fn($r) => $r->toDto(), $items),
            'total' => $repo->countForProduct($pid, 'approved'),
            'page' => $page,
        ]);
    }

    public function submit(\WP_REST_Request $req)
    {
        if (!$this->checkOrigin($req)) return $this->err('forbidden', 'Origine non autorisée', 403);
        if (!$this->rateLimit('review_' . $this->clientIp(), 5, 3600)) return $this->err('rate_limited', 'Trop d\'avis postés', 429);
        $pid = (int) $req['id'];
        $b = $req->get_json_params() ?: $req->get_params();
        $name = sanitize_text_field((string) ($b['author_name'] ?? ''));
        $email = sanitize_email((string) ($b['author_email'] ?? ''));
        $rating = max(1, min(5, (int) ($b['rating'] ?? 0)));
        $content = wp_kses_post((string) ($b['content'] ?? ''));
        $title = isset($b['title']) ? sanitize_text_field((string) $b['title']) : null;
        if (!$name || !is_email($email) || !$rating || !$content || strlen($content) < 10) {
            return $this->err('invalid', 'Champs invalides (nom, email, note, contenu min 10 caractères)', 400);
        }
        $r = new Review();
        $r->product_id = $pid;
        $r->user_id = get_current_user_id() ?: null;
        $r->author_name = $name;
        $r->author_email = $email;
        $r->rating = $rating;
        $r->content = $content;
        $r->title = $title;
        $r->status = get_option('yv_shop_review_auto_approve', false) ? 'approved' : 'pending';
        $id = (new ReviewRepository())->save($r);
        return rest_ensure_response(['id' => $id, 'status' => $r->status]);
    }

    public function admin_list(\WP_REST_Request $req)
    {
        $status = (string) $req->get_param('status');
        $page = max(1, (int) $req->get_param('page'));
        $per_page = min(100, max(1, (int) ($req->get_param('per_page') ?: 20)));
        $offset = ($page - 1) * $per_page;
        $repo = new ReviewRepository();
        $items = $repo->all($status, $per_page, $offset);
        return rest_ensure_response([
            'items' => array_map(static fn($r) => get_object_vars($r), $items),
            'total' => $repo->countAll($status),
        ]);
    }

    public function admin_update(\WP_REST_Request $req)
    {
        $b = $req->get_json_params() ?: $req->get_params();
        $status = $b['status'] ?? null;
        if (!in_array($status, ['pending','approved','spam','trash'], true)) return $this->err('invalid', 'Statut invalide', 400);
        $ok = (new ReviewRepository())->updateStatus((int) $req['id'], $status);
        return rest_ensure_response(['updated' => $ok]);
    }

    public function admin_delete(\WP_REST_Request $req)
    {
        $ok = (new ReviewRepository())->delete((int) $req['id']);
        return rest_ensure_response(['deleted' => $ok]);
    }
}
