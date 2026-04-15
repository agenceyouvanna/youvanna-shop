<?php
namespace Youvanna\Shop\Payments;

use Youvanna\Shop\Models\Order;
use Youvanna\Shop\Repositories\OrderRepository;

defined('ABSPATH') || exit;

final class BankTransferGateway implements GatewayInterface
{
    public function id(): string { return 'bank_transfer'; }
    public function label(): string { return __('Virement bancaire', 'yv-shop'); }
    public function description(): string
    {
        return (string) get_option('yv_shop_bank_transfer_description', __('Effectuer le virement avec le numéro de commande en référence.', 'yv-shop'));
    }
    public function icon(): string { return ''; }
    public function isAvailable(): bool { return true; }

    public function createIntent(Order $order): array
    {
        $order->status = 'on-hold';
        $order->payment_status = 'pending';
        (new OrderRepository())->save($order);
        $iban = (string) get_option('yv_shop_bank_iban', '');
        $bic = (string) get_option('yv_shop_bank_bic', '');
        $name = (string) get_option('yv_shop_bank_account_name', get_option('blogname'));
        $instructions = sprintf(
            __("Virement à effectuer sur le compte :\nBénéficiaire : %s\nIBAN : %s\nBIC : %s\nRéférence : %s", 'yv-shop'),
            $name, $iban, $bic, $order->order_number
        );
        return [
            'payment_reference' => $order->order_number,
            'instructions'      => $instructions,
            'redirect'          => add_query_arg([
                'order' => $order->order_number,
                'key'   => $order->order_key,
            ], get_permalink((int) get_option('yv_shop_checkout_page_id'))),
        ];
    }

    public function handleWebhook(\WP_REST_Request $req): \WP_REST_Response
    {
        return new \WP_REST_Response(['received' => true], 200);
    }
}
