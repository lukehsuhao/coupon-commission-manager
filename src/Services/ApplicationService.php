<?php

namespace CouponCommissionManager\Services;

use CouponCommissionManager\Models\Application;
use CouponCommissionManager\Models\Partner;

class ApplicationService {

    public static function approve( object $application, string $admin_note = '' ): array {
        // 1. Determine coupon code
        $code           = strtoupper( trim( $application->desired_coupon_code ) );
        $coupon_modified = false;
        $existing        = wc_get_coupon_id_by_code( $code );

        if ( $existing ) {
            $code           .= '-' . substr( wp_generate_password( 4, false ), 0, 4 );
            $coupon_modified = true;
        }

        // 2. Create WooCommerce coupon
        $coupon = new \WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( 'fixed_cart' );
        $coupon->set_amount( 0 );
        $coupon->set_description( sprintf( '夥伴分潤折扣碼 — %s', $application->name ) );
        $coupon->save();

        // 3. Create Partner
        $partner_id = Partner::create( [
            'name'              => $application->name,
            'email'             => $application->email,
            'phone'             => $application->phone ?? '',
            'bank_name'         => $application->bank_name ?? '',
            'bank_account'      => $application->bank_account ?? '',
            'bank_account_name' => $application->bank_account_name ?? '',
            'notes'             => trim( implode( "\n", array_filter( [
                $application->company_name ? '公司：' . $application->company_name : '',
                $application->tax_id ? '統編：' . $application->tax_id : '',
                $application->notes ?? '',
            ] ) ) ),
            'status'            => 'active',
        ] );

        // 4. Update application
        Application::update( $application->id, [
            'status'      => 'approved',
            'admin_note'  => $admin_note,
            'reviewed_at' => current_time( 'mysql' ),
            'reviewed_by' => get_current_user_id(),
        ] );

        // 5. Send email
        self::send_approval_email( $application, $code, $coupon_modified );

        return [
            'partner_id'      => $partner_id,
            'coupon_code'     => $code,
            'coupon_modified' => $coupon_modified,
        ];
    }

    public static function reject( object $application, string $admin_note = '' ): void {
        Application::update( $application->id, [
            'status'      => 'rejected',
            'admin_note'  => $admin_note,
            'reviewed_at' => current_time( 'mysql' ),
            'reviewed_by' => get_current_user_id(),
        ] );

        self::send_rejection_email( $application );
    }

    private static function send_approval_email( object $application, string $coupon_code, bool $coupon_modified ): void {
        $settings = get_option( 'ccm_settings', [] );
        $template = $settings['email_approval_template'] ?? '';

        if ( empty( $template ) ) {
            $template = self::default_approval_template();
        }

        $body = self::replace_placeholders( $template, $application, $coupon_code );

        if ( $coupon_modified ) {
            $body .= "\n\n" . sprintf(
                '※ 您申請的折扣碼「%s」已被使用，我們為您調整為「%s」。',
                $application->desired_coupon_code,
                $coupon_code
            );
        }

        $subject = sprintf( '[%s] 合作夥伴申請已通過', get_bloginfo( 'name' ) );
        wp_mail( $application->email, $subject, $body );
    }

    private static function send_rejection_email( object $application ): void {
        $settings = get_option( 'ccm_settings', [] );
        $template = $settings['email_rejection_template'] ?? '';

        if ( empty( $template ) ) {
            $template = self::default_rejection_template();
        }

        $body    = self::replace_placeholders( $template, $application );
        $subject = sprintf( '[%s] 合作夥伴申請結果通知', get_bloginfo( 'name' ) );
        wp_mail( $application->email, $subject, $body );
    }

    private static function replace_placeholders( string $template, object $application, string $coupon_code = '' ): string {
        return str_replace(
            [ '{partner_name}', '{email}', '{coupon_code}', '{site_name}', '{site_url}' ],
            [ $application->name, $application->email, $coupon_code, get_bloginfo( 'name' ), home_url() ],
            $template
        );
    }

    public static function default_approval_template(): string {
        return "{partner_name} 您好，\n\n"
            . "感謝您申請成為 {site_name} 的合作夥伴！\n\n"
            . "您的申請已通過審核，以下是您的專屬折扣碼：\n\n"
            . "折扣碼：{coupon_code}\n\n"
            . "您可以將此折扣碼分享給您的客戶使用。我們會根據使用此折扣碼的訂單計算您的分潤。\n\n"
            . "如有任何問題，歡迎隨時與我們聯繫。\n\n"
            . "{site_name}\n"
            . "{site_url}";
    }

    public static function default_rejection_template(): string {
        return "{partner_name} 您好，\n\n"
            . "感謝您申請成為 {site_name} 的合作夥伴。\n\n"
            . "很抱歉，經過審核後，我們目前無法通過您的申請。\n\n"
            . "如有任何疑問，歡迎與我們聯繫。\n\n"
            . "{site_name}\n"
            . "{site_url}";
    }
}
