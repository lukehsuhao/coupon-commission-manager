<?php

namespace CouponCommissionManager\Admin;

class Assets {

    public static function register(): void {
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
    }

    public static function enqueue( string $hook ): void {
        // Only load on our plugin pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'ccm-' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'ccm-admin',
            CCM_PLUGIN_URL . 'assets/css/ccm-admin.css',
            [],
            CCM_VERSION
        );

        wp_enqueue_script(
            'ccm-admin',
            CCM_PLUGIN_URL . 'assets/js/ccm-admin.js',
            [ 'jquery', 'jquery-ui-datepicker' ],
            CCM_VERSION,
            true
        );

        wp_localize_script( 'ccm-admin', 'ccmAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ccm_admin_action' ),
        ] );

        // jQuery UI datepicker style
        wp_enqueue_style( 'jquery-ui-datepicker-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', [], '1.13.2' );
    }
}
