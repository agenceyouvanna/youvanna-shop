<?php
namespace Youvanna\Shop\REST;

defined('ABSPATH') || exit;

final class Router
{
    public const NAMESPACE = 'yv-shop/v1';

    public function register(): void
    {
        (new ProductsController())->register_routes();
        (new CategoriesController())->register_routes();
        (new AttributesController())->register_routes();
        (new CartController())->register_routes();
        (new CheckoutController())->register_routes();
        (new OrdersController())->register_routes();
        (new WebhooksController())->register_routes();
        (new VariationsController())->register_routes();
        (new CouponsController())->register_routes();
        (new ReviewsController())->register_routes();
        (new WishlistController())->register_routes();
        (new LensController())->register_routes();
    }
}
