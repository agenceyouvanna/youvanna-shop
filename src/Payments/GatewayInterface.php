<?php
namespace Youvanna\Shop\Payments;

use Youvanna\Shop\Models\Order;

defined('ABSPATH') || exit;

interface GatewayInterface
{
    public function id(): string;
    public function label(): string;
    public function description(): string;
    public function icon(): string;

    /** @return array{client_secret?:string, payment_reference?:string, redirect?:string, instructions?:string} */
    public function createIntent(Order $order): array;

    public function handleWebhook(\WP_REST_Request $req): \WP_REST_Response;

    public function isAvailable(): bool;
}
