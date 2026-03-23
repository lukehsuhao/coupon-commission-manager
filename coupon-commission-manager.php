<?php
/**
 * Plugin Name: Coupon Commission Manager
 * Plugin URI:  https://developvi.com
 * Description: WooCommerce 折扣碼分潤管理 — 追蹤折扣碼分潤、管理夥伴付款狀態、匯出 CSV。
 * Version:     1.0.0
 * Author:      DevelopVI
 * Author URI:  https://developvi.com
 * Text Domain: ccm
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CCM_VERSION', '1.0.0' );
define( 'CCM_PLUGIN_FILE', __FILE__ );
define( 'CCM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader
spl_autoload_register( function ( $class ) {
    $prefix = 'CouponCommissionManager\\';
    $len    = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $relative_class = substr( $class, $len );
    $file           = CCM_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Activation / Deactivation
register_activation_hook( __FILE__, [ \CouponCommissionManager\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \CouponCommissionManager\Deactivator::class, 'deactivate' ] );

// Initialize plugin after WooCommerce loads
add_action( 'plugins_loaded', function () {
    \CouponCommissionManager\Plugin::getInstance();
}, 20 );
