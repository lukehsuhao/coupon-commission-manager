<?php

namespace CouponCommissionManager;

use CouponCommissionManager\Admin\Menu;
use CouponCommissionManager\Admin\Assets;
use CouponCommissionManager\Hooks\WooCommerceHooks;
use CouponCommissionManager\Services\CsvExporter;

class Plugin {

    private static ?Plugin $instance = null;

    public static function getInstance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init(): void {
        if ( ! $this->check_woocommerce() ) {
            return;
        }

        // Admin
        if ( is_admin() ) {
            Menu::register();
            Assets::register();
        }

        // WooCommerce hooks
        WooCommerceHooks::register();

        // CSV Export endpoint
        CsvExporter::register();
    }

    private function check_woocommerce(): bool {
        if ( class_exists( 'WooCommerce' ) ) {
            return true;
        }

        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( '「折扣碼分潤管理」需要 WooCommerce 才能運作。請先啟用 WooCommerce。', 'ccm' );
            echo '</p></div>';
        } );

        return false;
    }
}
