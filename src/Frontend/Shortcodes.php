<?php
namespace Youvanna\Shop\Frontend;

defined('ABSPATH') || exit;

final class Shortcodes
{
    public function register(): void
    {
        add_shortcode('yv_shop', [$this, 'shop']);
        add_shortcode('yv_shop_cart', [$this, 'cart']);
        add_shortcode('yv_shop_checkout', [$this, 'checkout']);
        add_shortcode('yv_shop_mini_cart', [$this, 'miniCart']);
        add_shortcode('yv_shop_wishlist', [$this, 'wishlist']);
    }

    public function shop(array $atts = []): string
    {
        return TemplateLoader::get('archive.php', ['atts' => $atts]);
    }

    public function cart(array $atts = []): string
    {
        return TemplateLoader::get('cart.php', ['atts' => $atts]);
    }

    public function checkout(array $atts = []): string
    {
        return TemplateLoader::get('checkout.php', ['atts' => $atts]);
    }

    public function miniCart(array $atts = []): string
    {
        return TemplateLoader::get('parts/mini-cart.php', ['atts' => $atts]);
    }

    public function wishlist(array $atts = []): string
    {
        return TemplateLoader::get('wishlist.php', ['atts' => $atts]);
    }
}
