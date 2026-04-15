<?php
namespace Youvanna\Shop\REST;

use Youvanna\Shop\Services\LensConfigurator;

defined('ABSPATH') || exit;

final class LensController extends Controller
{
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/lens/spec', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [$this, 'spec'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->namespace, '/lens/validate', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'validate'],
            'permission_callback' => [$this, 'permission_validate'],
        ]);
        register_rest_route($this->namespace, '/lens/upload-prescription', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'uploadPrescription'],
            'permission_callback' => [$this, 'permission_upload'],
        ]);
    }

    public function permission_validate(\WP_REST_Request $req): bool
    {
        if (!$this->checkOrigin($req)) return false;
        $ip = $this->clientIp();
        return $this->rateLimit('lens_validate_' . $ip, 120, 60);
    }

    public function permission_upload(\WP_REST_Request $req): bool
    {
        if (!$this->checkOrigin($req)) return false;
        $nonce = $req->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) return false;
        $ip = $this->clientIp();
        return $this->rateLimit('lens_upload_' . $ip, 10, 300);
    }

    public function spec(\WP_REST_Request $req)
    {
        return new \WP_REST_Response([
            'spec'     => LensConfigurator::spec(),
            'prices'   => LensConfigurator::prices(),
            'fittingbox' => [
                'pd_key'     => (string) get_option('yv_shop_fittingbox_pd_key', ''),
                'fitmix_key' => (string) get_option('yv_shop_fitmix_key', ''),
            ],
        ]);
    }

    public function validate(\WP_REST_Request $req)
    {
        $body = $req->get_json_params() ?: [];
        $config = isset($body['config']) && is_array($body['config']) ? $body['config'] : [];
        $result = LensConfigurator::validate($config);
        return new \WP_REST_Response($result);
    }

    public function uploadPrescription(\WP_REST_Request $req)
    {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_insert_attachment')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $files = $req->get_file_params();
        if (empty($files['file'])) {
            return $this->err('no_file', __('Aucun fichier envoyé', 'yv-shop'), 400);
        }
        $file = $files['file'];

        $max = 5 * 1024 * 1024;
        if (!empty($file['size']) && (int) $file['size'] > $max) {
            return $this->err('file_too_large', __('Le fichier dépasse 5 Mo', 'yv-shop'), 413);
        }

        $allowed = [
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'webp'     => 'image/webp',
            'pdf'      => 'application/pdf',
            'heic'     => 'image/heic',
        ];
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed);
        if (empty($check['type']) || !in_array($check['type'], $allowed, true)) {
            return $this->err('invalid_mime', __('Format non supporté. JPG, PNG, WEBP ou PDF uniquement.', 'yv-shop'), 415);
        }

        $_FILES = ['yv_lens_prescription' => $file];
        $attachment_id = media_handle_upload('yv_lens_prescription', 0, [
            'post_title'  => 'Ordonnance verres ' . gmdate('Y-m-d H:i:s'),
            'post_status' => 'private',
        ]);
        if (is_wp_error($attachment_id)) {
            return $this->err('upload_failed', $attachment_id->get_error_message(), 500);
        }
        update_post_meta((int) $attachment_id, '_yv_lens_prescription', 1);

        return new \WP_REST_Response([
            'attachment_id' => (int) $attachment_id,
            'url'           => (string) wp_get_attachment_url((int) $attachment_id),
        ]);
    }
}
