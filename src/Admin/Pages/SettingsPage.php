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
                'trigger_on_processing'     => ! empty( $_POST['trigger_on_processing'] ),
                'skip_renewal_orders'       => ! empty( $_POST['skip_renewal_orders'] ),
                'email_approval_template'   => wp_kses_post( $_POST['email_approval_template'] ?? '' ),
                'email_rejection_template'  => wp_kses_post( $_POST['email_rejection_template'] ?? '' ),
                'email_commission_subject'  => sanitize_text_field( $_POST['email_commission_subject'] ?? '' ),
                'email_commission_template' => wp_kses_post( $_POST['email_commission_template'] ?? '' ),
                'apply_redirect_url'        => esc_url_raw( $_POST['apply_redirect_url'] ?? '' ),
                'apply_button_color'        => sanitize_hex_color( $_POST['apply_button_color'] ?? '' ),
                'admin_notification_email'  => sanitize_email( $_POST['admin_notification_email'] ?? '' ),
            ];
            update_option( 'ccm_settings', $settings );
            update_option( 'ccm_delete_data_on_uninstall', ! empty( $_POST['delete_data_on_uninstall'] ) );

            $message = __( '設定已儲存', 'ccm' );
        }

        $settings            = get_option( 'ccm_settings', [] );
        $delete_on_uninstall = get_option( 'ccm_delete_data_on_uninstall', false );

        include CCM_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Default commission notification email subject.
     */
    public static function default_commission_subject(): string {
        return '【{site_name}】分潤通知 — {partner_name}';
    }

    /**
     * Default commission notification email template.
     */
    public static function default_commission_template(): string {
        return <<<'TPL'
{partner_name} 您好，

以下是您的分潤明細：

{commission_details}

分潤總金額：NT$ {commission_total}

如有任何問題，請與我們聯繫。

{site_name}
{site_url}
TPL;
    }
}
