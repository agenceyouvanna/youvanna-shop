<?php
namespace Youvanna\Shop\Emails;

use Youvanna\Shop\Helpers\Currency;
use Youvanna\Shop\Models\Order;

defined('ABSPATH') || exit;

/**
 * Service email de base. Déclenché sur les hooks yv_shop_*.
 */
final class Mailer
{
    public function register(): void
    {
        add_action('yv_shop_order_created', [$this, 'onOrderCreated'], 10, 1);
        add_action('yv_shop_payment_succeeded', [$this, 'onPaymentSucceeded'], 10, 1);
        add_action('yv_shop_order_status_changed', [$this, 'onStatusChanged'], 10, 3);
    }

    public function onOrderCreated(Order $order): void
    {
        $this->sendCustomer($order, 'order_received',
            sprintf(__('Confirmation de commande %s', 'yv-shop'), $order->order_number),
            $this->render('order-received', $order)
        );
        $this->sendAdmin($order, 'admin_new_order',
            sprintf(__('Nouvelle commande %s', 'yv-shop'), $order->order_number),
            $this->render('admin-new-order', $order)
        );
    }

    public function onPaymentSucceeded(Order $order): void
    {
        $this->sendCustomer($order, 'payment_received',
            sprintf(__('Paiement reçu pour la commande %s', 'yv-shop'), $order->order_number),
            $this->render('payment-received', $order)
        );
    }

    public function onStatusChanged(Order $order, string $from, string $to): void
    {
        if ($to === 'completed' || $to === 'processing' && $from !== 'processing') {
            $this->sendCustomer($order, 'order_shipped',
                sprintf(__('Votre commande %s est expédiée', 'yv-shop'), $order->order_number),
                $this->render('order-shipped', $order)
            );
        }
    }

    private function sendCustomer(Order $order, string $type, string $subject, string $body): void
    {
        $to = apply_filters('yv_shop_email_recipient', $order->customer_email, $type, $order);
        if (!$to) return;
        $subject = apply_filters('yv_shop_email_subject', $subject, $type, $order);
        $this->send($to, $subject, $body);
    }

    private function sendAdmin(Order $order, string $type, string $subject, string $body): void
    {
        $to = apply_filters('yv_shop_email_recipient', get_option('admin_email'), $type, $order);
        if (!$to) return;
        $subject = apply_filters('yv_shop_email_subject', $subject, $type, $order);
        $this->send($to, $subject, $body);
    }

    private function send(string $to, string $subject, string $html): void
    {
        $from_name = (string) get_option('yv_shop_email_from_name', get_option('blogname'));
        $from_email = (string) get_option('yv_shop_email_from_address', get_option('admin_email'));
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];
        wp_mail($to, $subject, $html, $headers);
    }

    private function render(string $template, Order $order): string
    {
        $candidates = [
            get_stylesheet_directory() . '/yv-shop/emails/' . $template . '.php',
            YV_SHOP_DIR . '/templates/emails/' . $template . '.php',
        ];
        foreach ($candidates as $p) {
            if (file_exists($p)) {
                ob_start();
                $order_ref = $order;
                include $p;
                return (string) ob_get_clean();
            }
        }
        return $this->fallback($order);
    }

    private function fallback(Order $order): string
    {
        $items = '';
        foreach ($order->items as $it) {
            $items .= '<tr><td>' . esc_html($it->name) . ' × ' . (int) $it->qty . '</td>'
                . '<td style="text-align:right">' . esc_html(Currency::format($it->line_total)) . '</td></tr>';
        }
        return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto">'
            . '<h1>' . esc_html(sprintf(__('Commande %s', 'yv-shop'), $order->order_number)) . '</h1>'
            . '<p>' . esc_html__('Merci pour votre commande.', 'yv-shop') . '</p>'
            . '<table style="width:100%;border-collapse:collapse">' . $items
            . '<tr><td><strong>' . esc_html__('Total', 'yv-shop') . '</strong></td>'
            . '<td style="text-align:right"><strong>' . esc_html(Currency::format($order->total)) . '</strong></td></tr>'
            . '</table></div>';
    }
}
