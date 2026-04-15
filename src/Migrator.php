<?php
namespace Youvanna\Shop;

defined('ABSPATH') || exit;

final class Migrator
{
    private string $table;
    private \wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'yv_shop_migrations';
    }

    public function run(): void
    {
        $this->ensureMigrationsTable();
        $applied = $this->appliedVersions();
        $files = $this->migrationFiles();
        foreach ($files as $version => $file) {
            if (in_array($version, $applied, true)) {
                continue;
            }
            $this->applyFile($version, $file);
        }
    }

    public function maybeMigrate(): void
    {
        $current = get_option('yv_shop_db_version');
        if ($current !== YV_SHOP_VERSION) {
            $this->run();
            update_option('yv_shop_db_version', YV_SHOP_VERSION);
        }
    }

    private function ensureMigrationsTable(): void
    {
        $charset = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            version VARCHAR(20) NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB {$charset};";
        $this->wpdb->query($sql);
    }

    /** @return string[] */
    private function appliedVersions(): array
    {
        $rows = $this->wpdb->get_col("SELECT version FROM {$this->table}");
        return array_map('strval', $rows ?: []);
    }

    /** @return array<string,string> */
    private function migrationFiles(): array
    {
        $dir = YV_SHOP_DIR . '/migrations';
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir . '/*.php') as $file) {
            $base = basename($file, '.php');
            // Format: 001_initial_schema.php -> version "001"
            $version = explode('_', $base)[0];
            $out[$version] = $file;
        }
        ksort($out);
        return $out;
    }

    private function applyFile(string $version, string $file): void
    {
        // Lock to prevent concurrent migrations
        $this->wpdb->query("SELECT GET_LOCK('yv_shop_migrate', 30)");
        try {
            $up = require $file;
            if (is_callable($up)) {
                $up($this->wpdb);
            }
            $this->wpdb->insert($this->table, [
                'version' => $version,
                'applied_at' => current_time('mysql'),
            ]);
        } finally {
            $this->wpdb->query("SELECT RELEASE_LOCK('yv_shop_migrate')");
        }
    }
}
