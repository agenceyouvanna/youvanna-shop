<?php
namespace Youvanna\Shop\Payments;

use Youvanna\Shop\Models\Order;
use Youvanna\Shop\Repositories\OrderRepository;
use Youvanna\Shop\Services\Encryption;

defined('ABSPATH') || exit;

final class StripeGateway implements GatewayInterface
{
    public function id(): string { return 'stripe'; }
    public function label(): string { return __('Carte bancaire', 'yv-shop'); }
    public function description(): string { return __('Paiement sécurisé par carte via Stripe', 'yv-shop'); }
    public function icon(): string { return YV_SHOP_URL . 'assets/dist/stripe.svg'; }

    public function isAvailable(): bool
    {
        return $this->publishableKey() !== '' && $this->secretKey() !== '';
    }

    public function publishableKey(): string
    {
        $mode = get_option('yv_shop_stripe_mode', 'test');
        return (string) get_option($mode === 'live' ? 'yv_shop_stripe_live_publishable' : 'yv_shop_stripe_test_publishable', '');
    }

    private function secretKey(): string
    {
        $mode = get_option('yv_shop_stripe_mode', 'test');
        $opt = $mode === 'live' ? 'yv_shop_stripe_live_secret' : 'yv_shop_stripe_test_secret';
        $encrypted = (string) get_option($opt, '');
        if (!$encrypted) {
            return '';
        }
        try {
            return (string) ((new Encryption())->decrypt($encrypted) ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function webhookSecret(): string
    {
        $mode = get_option('yv_shop_stripe_mode', 'test');
        $opt = $mode === 'live' ? 'yv_shop_stripe_live_webhook' : 'yv_shop_stripe_test_webhook';
        $encrypted = (string) get_option($opt, '');
        if (!$encrypted) {
            return '';
        }
        try {
            return (string) ((new Encryption())->decrypt($encrypted) ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function createIntent(Order $order): array
    {
        $secret = $this->secretKey();
        if (!$secret) {
            throw new \RuntimeException('Stripe secret key not configured');
        }
        $body = http_build_query([
            'amount'   => (int) round($order->total * 100),
            'currency' => strtolower($order->currency),
            'metadata[order_id]' => (string) $order->id,
            'metadata[order_number]' => $order->order_number,
            'automatic_payment_methods[enabled]' => 'true',
            'description' => sprintf('Commande %s', $order->order_number),
        ]);
        $resp = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => $body,
            'timeout' => 15,
        ]);
        if (is_wp_error($resp)) {
            throw new \RuntimeException($resp->get_error_message());
        }
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($data) || !empty($data['error'])) {
            $msg = $data['error']['message'] ?? 'Stripe error';
            throw new \RuntimeException($msg);
        }
        return [
            'client_secret'     => (string) ($data['client_secret'] ?? ''),
            'payment_reference' => (string) ($data['id'] ?? ''),
            'publishable_key'   => $this->publishableKey(),
        ];
    }

    public function handleWebhook(\WP_REST_Request $req): \WP_REST_Response
    {
        $payload = $req->get_body();
        $signature_header = $req->get_header('stripe_signature');
        $secret = $this->webhookSecret();
        if (!$secret || !$this->verifySignature($payload, $signature_header, $secret)) {
            return new \WP_REST_Response(['error' => 'invalid_signature'], 400);
        }
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return new \WP_REST_Response(['error' => 'invalid_payload'], 400);
        }
        $event_id = (string) ($event['id'] ?? '');
        if (!$event_id) {
            return new \WP_REST_Response(['error' => 'missing_id'], 400);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'yv_webhook_events';
        $exists = (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$table} WHERE event_id = %s", $event_id));
        if ($exists) {
            return new \WP_REST_Response(['received' => true, 'idempotent' => true], 200);
        }
        $wpdb->insert($table, [
            'event_id'    => $event_id,
            'gateway'     => 'stripe',
            'received_at' => current_time('mysql'),
            'status'      => 'received',
        ]);
        try {
            $this->processEvent($event);
            $wpdb->update($table, [
                'status'       => 'processed',
                'processed_at' => current_time('mysql'),
            ], ['event_id' => $event_id]);
        } catch (\Throwable $e) {
            $wpdb->update($table, [
                'status' => 'failed',
                'error'  => mb_substr($e->getMessage(), 0, 1000),
            ], ['event_id' => $event_id]);
        }
        return new \WP_REST_Response(['received' => true], 200);
    }

    private function processEvent(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? [];
        if (empty($object['id'])) {
            return;
        }
        $repo = new OrderRepository();
        $order = $repo->findByPaymentReference((string) $object['id']);
        if (!$order) {
            return;
        }
        if ($type === 'payment_intent.succeeded') {
            $amount_received = (int) ($object['amount_received'] ?? 0);
            $expected = (int) round($order->total * 100);
            $currency_match = strtolower((string) ($object['currency'] ?? '')) === strtolower($order->currency);
            if ($amount_received !== $expected || !$currency_match) {
                $order->status = 'review';
                $repo->save($order);
                return;
            }
            if ($order->payment_status !== 'paid') {
                $order->payment_status = 'paid';
                $order->status = 'processing';
                $order->paid_at = current_time('mysql');
                $repo->save($order);
                do_action('yv_shop_payment_succeeded', $order);
            }
        } elseif ($type === 'payment_intent.payment_failed') {
            $order->payment_status = 'failed';
            $order->status = 'failed';
            $repo->save($order);
            do_action('yv_shop_payment_failed', $order, (string) ($object['last_payment_error']['message'] ?? ''));
        } elseif ($type === 'charge.refunded') {
            $order->payment_status = 'refunded';
            $order->status = 'refunded';
            $repo->save($order);
        }
    }

    private function verifySignature(string $payload, ?string $header, string $secret): bool
    {
        if (!$header) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $p) {
            [$k, $v] = array_pad(explode('=', $p, 2), 2, '');
            $parts[$k] = $v;
        }
        $timestamp = (int) ($parts['t'] ?? 0);
        $sig = (string) ($parts['v1'] ?? '');
        if (!$timestamp || !$sig) {
            return false;
        }
        if (abs(time() - $timestamp) > 300) {
            return false;
        }
        $signed = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed, $secret);
        return hash_equals($expected, $sig);
    }
}
