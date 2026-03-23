<?php

namespace CouponCommissionManager;

use CouponCommissionManager\Admin\Menu;
use CouponCommissionManager\Admin\Assets;
use CouponCommissionManager\Hooks\WooCommerceHooks;
use CouponCommissionManager\Services\CsvExporter;
use CouponCommissionManager\Frontend\ApplicationForm;
use CouponCommissionManager\Database\Schema;

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
        // Declare HPOS compatibility
        add_action( 'before_woocommerce_init', function () {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CCM_PLUGIN_FILE, true );
            }
        } );

        if ( ! $this->check_woocommerce() ) {
            return;
        }

        // DB migration for updates
        $db_version = get_option( 'ccm_db_version', '0' );
        if ( version_compare( $db_version, CCM_VERSION, '<' ) ) {
            Schema::create_tables();
            update_option( 'ccm_db_version', CCM_VERSION );
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

        // Frontend shortcode
        ApplicationForm::register();
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
