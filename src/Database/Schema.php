<?php

namespace CouponCommissionManager\Database;

class Schema {

    public static function get_table_name( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . 'ccm_' . $name;
    }

    public static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $partners_table    = self::get_table_name( 'partners' );
        $rules_table       = self::get_table_name( 'commission_rules' );
        $logs_table        = self::get_table_name( 'commission_logs' );

        $sql_partners = "CREATE TABLE {$partners_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            bank_name VARCHAR(255) DEFAULT NULL,
            bank_account VARCHAR(100) DEFAULT NULL,
            bank_account_name VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status)
        ) {$charset_collate};";

        $sql_rules = "CREATE TABLE {$rules_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            partner_id BIGINT(20) UNSIGNED NOT NULL,
            coupon_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            commission_amount DECIMAL(10,2) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_coupon_product (coupon_id, product_id),
            KEY idx_partner (partner_id)
        ) {$charset_collate};";

        $sql_logs = "CREATE TABLE {$logs_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            partner_id BIGINT(20) UNSIGNED NOT NULL,
            rule_id BIGINT(20) UNSIGNED DEFAULT NULL,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            order_item_id BIGINT(20) UNSIGNED NOT NULL,
            coupon_id BIGINT(20) UNSIGNED NOT NULL,
            coupon_code VARCHAR(255) NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            quantity INT(11) NOT NULL DEFAULT 1,
            commission_per_unit DECIMAL(10,2) NOT NULL,
            commission_total DECIMAL(10,2) NOT NULL,
            order_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            paid_at DATETIME DEFAULT NULL,
            paid_by BIGINT(20) UNSIGNED DEFAULT NULL,
            payment_note VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_order_item (order_id, order_item_id, coupon_id),
            KEY idx_partner_status (partner_id, status),
            KEY idx_order (order_id),
            KEY idx_coupon (coupon_id),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) {$charset_collate};";

        $applications_table = self::get_table_name( 'applications' );

        $sql_applications = "CREATE TABLE {$applications_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            company_name VARCHAR(255) DEFAULT NULL,
            tax_id VARCHAR(20) DEFAULT NULL,
            bank_name VARCHAR(255) DEFAULT NULL,
            bank_account VARCHAR(100) DEFAULT NULL,
            bank_account_name VARCHAR(255) DEFAULT NULL,
            desired_coupon_code VARCHAR(255) NOT NULL,
            notes TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            admin_note TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME DEFAULT NULL,
            reviewed_by BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_email (email),
            KEY idx_created (created_at)
        ) {$charset_collate};";

        $coupon_rules_table = self::get_table_name( 'coupon_rules' );

        $sql_coupon_rules = "CREATE TABLE {$coupon_rules_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            coupon_id BIGINT(20) UNSIGNED NOT NULL,
            target_type VARCHAR(20) NOT NULL DEFAULT 'all',
            target_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            discount_type VARCHAR(30) NOT NULL DEFAULT 'fixed',
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_coupon_target (coupon_id, target_type, target_id, discount_type),
            KEY idx_coupon (coupon_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_partners );
        dbDelta( $sql_rules );
        dbDelta( $sql_logs );
        dbDelta( $sql_applications );
        dbDelta( $sql_coupon_rules );
    }

    public static function drop_tables(): void {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS " . self::get_table_name( 'coupon_rules' ) );
        $wpdb->query( "DROP TABLE IF EXISTS " . self::get_table_name( 'applications' ) );
        $wpdb->query( "DROP TABLE IF EXISTS " . self::get_table_name( 'commission_logs' ) );
        $wpdb->query( "DROP TABLE IF EXISTS " . self::get_table_name( 'commission_rules' ) );
        $wpdb->query( "DROP TABLE IF EXISTS " . self::get_table_name( 'partners' ) );
    }
}
