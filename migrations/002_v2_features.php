<?php
/**
 * Migration 002 : tables V2 (reviews, wishlists) + alter existants.
 */
defined('ABSPATH') || exit;

return static function (\wpdb $wpdb): void {
    $charset = $wpdb->get_charset_collate();
    $p = $wpdb->prefix;

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_product_reviews (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        author_name VARCHAR(190) NOT NULL,
        author_email VARCHAR(190) NOT NULL,
        rating TINYINT UNSIGNED NOT NULL,
        title VARCHAR(190) NULL,
        content TEXT NOT NULL,
        status ENUM('pending','approved','spam','trash') NOT NULL DEFAULT 'pending',
        verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
        ip_address VARBINARY(16) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_product_status (product_id, status, created_at),
        KEY idx_user (user_id),
        KEY idx_email (author_email)
    ) ENGINE=InnoDB {$charset};");

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_wishlists (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        session_token CHAR(64) NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_user_product (user_id, product_id),
        UNIQUE KEY uniq_session_product (session_token, product_id),
        KEY idx_product (product_id)
    ) ENGINE=InnoDB {$charset};");

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_order_notes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        author VARCHAR(190) NOT NULL,
        note TEXT NOT NULL,
        customer_visible TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_order (order_id, created_at)
    ) ENGINE=InnoDB {$charset};");

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_order_refunds (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(12,4) NOT NULL,
        reason VARCHAR(255) NULL,
        gateway_refund_id VARCHAR(190) NULL,
        status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_order (order_id)
    ) ENGINE=InnoDB {$charset};");
};
