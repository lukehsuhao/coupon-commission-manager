<?php

namespace CouponCommissionManager\Admin\Pages;

class SettingsPage {

    public static function render(): void {
        // Handle save
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ccm_settings_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['ccm_settings_nonce'], 'ccm_save_settings' ) ) {
                wp_die( __( '安全性驗證失敗', 'ccm' ) );
            }

            $settings = [
                'trigger_on_processing'    => ! empty( $_POST['trigger_on_processing'] ),
                'skip_renewal_orders'      => ! empty( $_POST['skip_renewal_orders'] ),
                'email_approval_template'  => wp_kses_post( $_POST['email_approval_template'] ?? '' ),
                'email_rejection_template' => wp_kses_post( $_POST['email_rejection_template'] ?? '' ),
                'apply_redirect_url'       => esc_url_raw( $_POST['apply_redirect_url'] ?? '' ),
                'apply_button_color'       => sanitize_hex_color( $_POST['apply_button_color'] ?? '' ),
                'admin_notification_email' => sanitize_email( $_POST['admin_notification_email'] ?? '' ),
            ];
            update_option( 'ccm_settings', $settings );
            update_option( 'ccm_delete_data_on_uninstall', ! empty( $_POST['delete_data_on_uninstall'] ) );

            $message = __( '設定已儲存', 'ccm' );
        }

        $settings            = get_option( 'ccm_settings', [] );
        $delete_on_uninstall = get_option( 'ccm_delete_data_on_uninstall', false );

        include CCM_PLUGIN_DIR . 'templates/admin/settings.php';
    }
}
