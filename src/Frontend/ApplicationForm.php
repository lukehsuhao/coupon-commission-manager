<?php

namespace CouponCommissionManager\Frontend;

use CouponCommissionManager\Models\Application;

class ApplicationForm {

    public static function register(): void {
        add_shortcode( 'ccm_partner_apply', [ self::class, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_styles' ] );

        // Handle form POST via admin-post.php (works for both logged-in and logged-out users)
        add_action( 'admin_post_ccm_submit_application', [ self::class, 'handle_submit' ] );
        add_action( 'admin_post_nopriv_ccm_submit_application', [ self::class, 'handle_submit' ] );
    }

    public static function enqueue_styles(): void {
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'ccm_partner_apply' ) ) {
            wp_enqueue_style(
                'ccm-frontend',
                CCM_PLUGIN_URL . 'assets/css/ccm-frontend.css',
                [],
                CCM_VERSION
            );
        }
    }

    /**
     * Handle form POST via admin-post.php, then redirect.
     */
    public static function handle_submit(): void {
        // Determine where to redirect back on error
        $referer = wp_get_referer() ?: home_url();

        // Nonce
        if ( ! isset( $_POST['ccm_apply_nonce'] ) || ! wp_verify_nonce( $_POST['ccm_apply_nonce'], 'ccm_partner_apply' ) ) {
            wp_redirect( add_query_arg( 'ccm_error', 'nonce', $referer ) );
            exit;
        }

        // Honeypot
        if ( ! empty( $_POST['website'] ) ) {
            wp_redirect( add_query_arg( 'ccm_error', 'spam', $referer ) );
            exit;
        }

        // Rate limit
        $ip_key = 'ccm_apply_limit_' . self::get_client_ip();
        if ( get_transient( $ip_key ) ) {
            wp_redirect( add_query_arg( 'ccm_error', 'rate', $referer ) );
            exit;
        }

        $data = self::sanitize_form_data();

        // Validate
        if ( empty( $data['name'] ) || empty( $data['email'] ) || ! is_email( $data['email'] ) || empty( $data['desired_coupon_code'] ) ) {
            wp_redirect( add_query_arg( 'ccm_error', 'required', $referer ) );
            exit;
        }

        if ( Application::has_pending_by_email( $data['email'] ) ) {
            wp_redirect( add_query_arg( 'ccm_error', 'duplicate', $referer ) );
            exit;
        }

        // Save
        Application::create( $data );
        set_transient( $ip_key, 1, 60 );

        // Notify admin
        self::send_admin_notification( $data );

        // Redirect to success page
        $settings     = get_option( 'ccm_settings', [] );
        $redirect_url = ! empty( $settings['apply_redirect_url'] ) ? $settings['apply_redirect_url'] : home_url();
        $redirect_url = add_query_arg( 'ccm_applied', '1', $redirect_url );

        wp_redirect( $redirect_url );
        exit;
    }

    public static function render( $atts = [] ): string {
        $success = isset( $_GET['ccm_applied'] );
        $error   = '';

        // Map error codes to messages
        if ( isset( $_GET['ccm_error'] ) ) {
            $errors = [
                'nonce'    => __( '安全性驗證失敗，請重新整理頁面後再試。', 'ccm' ),
                'spam'     => __( '送出失敗，請稍後再試。', 'ccm' ),
                'rate'     => __( '您送出的頻率太快，請稍後再試。', 'ccm' ),
                'required' => __( '請填寫所有必填欄位（姓名、Email、折扣碼）。', 'ccm' ),
                'duplicate' => __( '您已有一筆待審核的申請，請耐心等候。', 'ccm' ),
            ];
            $code  = sanitize_text_field( $_GET['ccm_error'] );
            $error = $errors[ $code ] ?? __( '送出失敗，請稍後再試。', 'ccm' );
        }

        ob_start();
        $form_data = [];
        include CCM_PLUGIN_DIR . 'templates/frontend/application-form.php';
        return ob_get_clean();
    }

    private static function sanitize_form_data(): array {
        return [
            'name'                => sanitize_text_field( $_POST['name'] ?? '' ),
            'email'               => sanitize_email( $_POST['email'] ?? '' ),
            'phone'               => sanitize_text_field( $_POST['phone'] ?? '' ),
            'company_name'        => sanitize_text_field( $_POST['company_name'] ?? '' ),
            'tax_id'              => sanitize_text_field( $_POST['tax_id'] ?? '' ),
            'bank_name'           => sanitize_text_field( $_POST['bank_name'] ?? '' ),
            'bank_account'        => sanitize_text_field( $_POST['bank_account'] ?? '' ),
            'bank_account_name'   => sanitize_text_field( $_POST['bank_account_name'] ?? '' ),
            'desired_coupon_code' => strtoupper( sanitize_text_field( $_POST['desired_coupon_code'] ?? '' ) ),
            'notes'               => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ];
    }

    private static function send_admin_notification( array $data ): void {
        $settings = get_option( 'ccm_settings', [] );
        $to       = ! empty( $settings['admin_notification_email'] ) ? $settings['admin_notification_email'] : get_option( 'admin_email' );

        $subject = sprintf( '[%s] 新的夥伴申請 — %s', get_bloginfo( 'name' ), $data['name'] );

        $body  = "收到一筆新的夥伴申請：\n\n";
        $body .= "姓名：{$data['name']}\n";
        $body .= "Email：{$data['email']}\n";
        if ( $data['phone'] )        $body .= "電話：{$data['phone']}\n";
        if ( $data['company_name'] ) $body .= "公司名稱：{$data['company_name']}\n";
        if ( $data['tax_id'] )       $body .= "統一編號：{$data['tax_id']}\n";
        $body .= "申請折扣碼：{$data['desired_coupon_code']}\n";
        if ( $data['notes'] )        $body .= "備註：{$data['notes']}\n";
        $body .= "\n前往審核：" . admin_url( 'admin.php?page=ccm-applications' ) . "\n";

        wp_mail( $to, $subject, $body );
    }

    private static function get_client_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return preg_replace( '/[^0-9a-f.:]/i', '', $ip );
    }
}
