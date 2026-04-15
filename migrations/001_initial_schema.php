<?php
/**
 * Migration 001: Initial schema for youvanna-shop.
 * Returns a callable that receives wpdb.
 */
defined('ABSPATH') || exit;

return static function (\wpdb $wpdb): void {
    $charset = $wpdb->get_charset_collate();
    $p = $wpdb->prefix;

    // Products
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_products (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sku VARCHAR(64) NOT NULL DEFAULT '',
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(200) NOT NULL,
        type ENUM('simple','variable','external','grouped') NOT NULL DEFAULT 'simple',
        status ENUM('draft','published','private','trash') NOT NULL DEFAULT 'draft',
        featured TINYINT(1) NOT NULL DEFAULT 0,
        description LONGTEXT NULL,
        short_description TEXT NULL,
        price DECIMAL(12,4) NOT NULL DEFAULT 0,
        sale_price DECIMAL(12,4) NULL,
        sale_from DATETIME NULL,
        sale_to DATETIME NULL,
        cost_price DECIMAL(12,4) NULL,
        tax_class VARCHAR(64) NOT NULL DEFAULT 'standard',
        tax_status ENUM('taxable','shipping','none') NOT NULL DEFAULT 'taxable',
        manage_stock TINYINT(1) NOT NULL DEFAULT 0,
        stock_qty INT NOT NULL DEFAULT 0,
        stock_status ENUM('instock','outofstock','onbackorder') NOT NULL DEFAULT 'instock',
        backorders ENUM('no','notify','yes') NOT NULL DEFAULT 'no',
        weight DECIMAL(8,3) NULL,
        length DECIMAL(8,3) NULL,
        width DECIMAL(8,3) NULL,
        height DECIMAL(8,3) NULL,
        image_id BIGINT UNSIGNED NULL,
        gallery_ids JSON NULL,
        sort_order INT NOT NULL DEFAULT 0,
        rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0,
        rating_count INT NOT NULL DEFAULT 0,
        sales_count INT NOT NULL DEFAULT 0,
        language VARCHAR(8) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_slug (slug),
        KEY idx_sku (sku),
        KEY idx_archive_sales (status, stock_status, sales_count, id),
        KEY idx_archive_price (status, stock_status, price, id),
        KEY idx_archive_created (status, stock_status, created_at, id),
        KEY idx_featured (featured, status),
        KEY idx_updated (updated_at),
        FULLTEXT KEY ft_search (name, description, short_description)
    ) ENGINE=InnoDB {$charset};");

    // Product variations
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_product_variations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT UNSIGNED NOT NULL,
        sku VARCHAR(64) NOT NULL DEFAULT '',
        price DECIMAL(12,4) NOT NULL DEFAULT 0,
        sale_price DECIMAL(12,4) NULL,
        stock_qty INT NOT NULL DEFAULT 0,
        stock_status ENUM('instock','outofstock','onbackorder') NOT NULL DEFAULT 'instock',
        weight DECIMAL(8,3) NULL,
        image_id BIGINT UNSIGNED NULL,
        attributes JSON NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_product (product_id, enabled),
        KEY idx_sku (sku),
        KEY idx_price (price)
    ) ENGINE=InnoDB {$charset};");

    // Product <-> terms (categories, tags, attributes)
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_product_terms (
        product_id BIGINT UNSIGNED NOT NULL,
        term_id BIGINT UNSIGNED NOT NULL,
        taxonomy VARCHAR(48) NOT NULL,
        PRIMARY KEY (product_id, term_id),
        KEY idx_term_tax (term_id, taxonomy),
        KEY idx_taxonomy (taxonomy)
    ) ENGINE=InnoDB {$charset};");

    // Orders
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_orders (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_number VARCHAR(32) NOT NULL,
        order_key VARCHAR(64) NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'pending',
        customer_id BIGINT UNSIGNED NULL,
        customer_email VARCHAR(190) NOT NULL,
        customer_phone VARCHAR(32) NULL,
        customer_note TEXT NULL,
        billing JSON NOT NULL,
        shipping JSON NULL,
        subtotal DECIMAL(12,4) NOT NULL DEFAULT 0,
        discount_total DECIMAL(12,4) NOT NULL DEFAULT 0,
        shipping_total DECIMAL(12,4) NOT NULL DEFAULT 0,
        tax_total DECIMAL(12,4) NOT NULL DEFAULT 0,
        total DECIMAL(12,4) NOT NULL DEFAULT 0,
        currency CHAR(3) NOT NULL DEFAULT 'EUR',
        payment_method VARCHAR(32) NOT NULL DEFAULT '',
        payment_status ENUM('pending','paid','failed','refunded','partial') NOT NULL DEFAULT 'pending',
        payment_reference VARCHAR(190) NULL,
        shipping_method VARCHAR(64) NULL,
        shipping_tracking VARCHAR(190) NULL,
        coupon_codes JSON NULL,
        ip_address VARBINARY(16) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        paid_at DATETIME NULL,
        shipped_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_order_number (order_number),
        UNIQUE KEY uniq_payment_reference (payment_reference),
        KEY idx_customer (customer_id),
        KEY idx_email (customer_email),
        KEY idx_status_created (status, created_at),
        KEY idx_payment_status (payment_status)
    ) ENGINE=InnoDB {$charset};");

    // Order items
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_order_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        variation_id BIGINT UNSIGNED NULL,
        name VARCHAR(255) NOT NULL,
        sku VARCHAR(64) NOT NULL DEFAULT '',
        qty INT NOT NULL DEFAULT 1,
        price DECIMAL(12,4) NOT NULL,
        line_subtotal DECIMAL(12,4) NOT NULL,
        line_tax DECIMAL(12,4) NOT NULL DEFAULT 0,
        line_total DECIMAL(12,4) NOT NULL,
        tax_class VARCHAR(64) NOT NULL DEFAULT 'standard',
        meta JSON NULL,
        PRIMARY KEY (id),
        KEY idx_order (order_id),
        KEY idx_product (product_id),
        KEY idx_variation (variation_id)
    ) ENGINE=InnoDB {$charset};");

    // Carts
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_carts (
        cart_hash CHAR(64) NOT NULL,
        session_token CHAR(64) NOT NULL,
        customer_id BIGINT UNSIGNED NULL,
        customer_email VARCHAR(190) NULL,
        items JSON NOT NULL,
        totals JSON NOT NULL,
        coupon_codes JSON NULL,
        currency CHAR(3) NOT NULL DEFAULT 'EUR',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        PRIMARY KEY (cart_hash),
        KEY idx_session (session_token),
        KEY idx_email (customer_email),
        KEY idx_expires (expires_at)
    ) ENGINE=InnoDB {$charset};");

    // Stock reservations
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_stock_reservations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT UNSIGNED NOT NULL,
        variation_id BIGINT UNSIGNED NULL,
        qty INT NOT NULL,
        order_id BIGINT UNSIGNED NULL,
        cart_hash CHAR(64) NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_product (product_id, variation_id),
        KEY idx_expires (expires_at),
        KEY idx_order (order_id)
    ) ENGINE=InnoDB {$charset};");

    // Coupons
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_coupons (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(64) NOT NULL,
        type ENUM('percent','fixed_cart','fixed_product','free_shipping') NOT NULL,
        amount DECIMAL(12,4) NOT NULL,
        description VARCHAR(255) NULL,
        minimum_amount DECIMAL(12,4) NULL,
        maximum_amount DECIMAL(12,4) NULL,
        individual_use TINYINT(1) NOT NULL DEFAULT 0,
        exclude_sale_items TINYINT(1) NOT NULL DEFAULT 0,
        usage_limit INT NULL,
        usage_limit_per_user INT NULL,
        used_count INT NOT NULL DEFAULT 0,
        product_ids JSON NULL,
        excluded_product_ids JSON NULL,
        starts_at DATETIME NULL,
        expires_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_code (code),
        KEY idx_expires (expires_at)
    ) ENGINE=InnoDB {$charset};");

    // Coupon usages (idempotency)
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_coupon_usages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        coupon_id BIGINT UNSIGNED NOT NULL,
        order_id BIGINT UNSIGNED NOT NULL,
        customer_email VARCHAR(190) NULL,
        used_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_coupon_order (coupon_id, order_id),
        KEY idx_email (customer_email)
    ) ENGINE=InnoDB {$charset};");

    // Webhook events idempotency
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_webhook_events (
        event_id VARCHAR(190) NOT NULL,
        gateway VARCHAR(32) NOT NULL,
        received_at DATETIME NOT NULL,
        processed_at DATETIME NULL,
        status ENUM('received','processed','failed') NOT NULL DEFAULT 'received',
        error TEXT NULL,
        PRIMARY KEY (event_id),
        KEY idx_gateway (gateway, received_at)
    ) ENGINE=InnoDB {$charset};");

    // Tax rates
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_tax_rates (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        country CHAR(2) NOT NULL DEFAULT '*',
        state VARCHAR(64) NOT NULL DEFAULT '*',
        postcode VARCHAR(32) NOT NULL DEFAULT '*',
        tax_class VARCHAR(64) NOT NULL DEFAULT 'standard',
        rate DECIMAL(7,4) NOT NULL,
        name VARCHAR(190) NOT NULL,
        priority INT NOT NULL DEFAULT 1,
        compound TINYINT(1) NOT NULL DEFAULT 0,
        shipping TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_class_country (tax_class, country)
    ) ENGINE=InnoDB {$charset};");

    // Shipping zones / methods
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_shipping_zones (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(190) NOT NULL,
        countries JSON NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB {$charset};");

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}yv_shipping_methods (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        zone_id BIGINT UNSIGNED NOT NULL,
        method_id VARCHAR(32) NOT NULL,
        title VARCHAR(190) NOT NULL,
        cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        free_threshold DECIMAL(12,4) NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        settings JSON NULL,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_zone (zone_id, enabled)
    ) ENGINE=InnoDB {$charset};");

    // Default tax rate (FR 20%)
    $wpdb->insert($p . 'yv_tax_rates', [
        'country'   => 'FR',
        'state'     => '*',
        'postcode'  => '*',
        'tax_class' => 'standard',
        'rate'      => 20.0,
        'name'      => 'TVA 20%',
        'priority'  => 1,
        'compound'  => 0,
        'shipping'  => 1,
    ]);

    // Default shipping zone (France)
    $wpdb->insert($p . 'yv_shipping_zones', [
        'name' => 'France',
        'countries' => wp_json_encode(['FR']),
    ]);
    $zone_id = $wpdb->insert_id;
    if ($zone_id) {
        $wpdb->insert($p . 'yv_shipping_methods', [
            'zone_id' => $zone_id,
            'method_id' => 'flat_rate',
            'title' => 'Livraison standard',
            'cost' => 6.00,
            'free_threshold' => 80.00,
            'enabled' => 1,
        ]);
    }
};
