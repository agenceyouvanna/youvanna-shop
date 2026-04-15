<?php
namespace Youvanna\Shop;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?self $instance = null;
    private Container $container;
    private bool $booted = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->container = new Container();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        load_plugin_textdomain('yv-shop', false, dirname(YV_SHOP_BASENAME) . '/languages');

        // Migrations check (run if needed)
        $this->container->get(Migrator::class)->maybeMigrate();

        // REST API : toujours chargée (peut être hit n'importe quand)
        add_action('rest_api_init', function (): void {
            $this->container->get(REST\Router::class)->register();
        });

        // Admin
        if (is_admin()) {
            $this->container->get(Admin\Menu::class)->register();
            $this->container->get(Admin\Settings::class)->register();
            $this->container->get(Admin\LensSettingsPage::class)->register();
        }

        // CLI
        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('yv-shop', CLI\Commands::class);
        }

        // Taxonomies enregistrées partout (admin + frontend + REST) pour que edit-tags.php et REST categories/tags fonctionnent
        add_action('init', [$this->container->get(Frontend\Router::class), 'registerTaxonomies'], 5);

        // Frontend conditionnel
        if (!is_admin() && !wp_doing_ajax() && !wp_doing_cron()) {
            $this->container->get(Frontend\Router::class)->register();
        }

        // Cron
        $this->container->get(Cron\Scheduler::class)->register();

        // Hooks publics partout
        $this->container->get(Hooks::class)->register();

        // Emails (customer + admin notifications)
        $this->container->get(Emails\Mailer::class)->register();

        do_action('yv_shop_booted', $this);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }
}
