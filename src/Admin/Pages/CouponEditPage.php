<?php

namespace CouponCommissionManager\Admin\Pages;

use CouponCommissionManager\Models\CouponRule;

class CouponEditPage {

    public static function render(): void {
        $coupon_id = absint( $_GET['coupon_id'] ?? 0 );
        $is_edit   = $coupon_id > 0;
        $message   = '';
        $error     = '';

        // Handle save
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ccm_coupon_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['ccm_coupon_nonce'], 'ccm_save_coupon' ) ) {
                wp_die( '安全性驗證失敗' );
            }

            $result = self::save_coupon( $coupon_id );
            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $coupon_id = $result;
                $is_edit = true;
                $message = __( '折扣碼已儲存', 'ccm' );
            }
        }

        // Load coupon data
        $coupon = $is_edit ? new \WC_Coupon( $coupon_id ) : null;
        $coupon_rules = $is_edit ? CouponRule::find_by_coupon_grouped( $coupon_id ) : [
            'standard' => [], 'signup' => [], 'recurring' => [],
        ];
        $has_wcs = class_exists( 'WC_Subscriptions' );

        include CCM_PLUGIN_DIR . 'templates/admin/coupon-edit.php';
    }

    private static function save_coupon( int $coupon_id ): int|\WP_Error {
        $code = sanitize_text_field( strtoupper( $_POST['coupon_code'] ?? '' ) );
        if ( empty( $code ) ) {
            return new \WP_Error( 'empty_code', '請輸入折扣碼' );
        }

        // Check for duplicate code (excluding current coupon)
        $existing = wc_get_coupon_id_by_code( $code );
        if ( $existing && $existing !== $coupon_id ) {
            return new \WP_Error( 'duplicate', '此折扣碼已存在' );
        }

        // Create or update WC coupon
        $coupon = $coupon_id ? new \WC_Coupon( $coupon_id ) : new \WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_description( sanitize_textarea_field( $_POST['description'] ?? '' ) );

        // We set WC discount type to fixed_product as a base; our custom rules handle the actual amounts
        $coupon->set_discount_type( 'fixed_product' );
        $coupon->set_amount( 0 ); // Actual amounts from our rules

        // Restrictions
        $coupon->set_individual_use( ! empty( $_POST['individual_use'] ) );
        $coupon->set_usage_limit( absint( $_POST['usage_limit'] ?? 0 ) );
        $coupon->set_usage_limit_per_user( absint( $_POST['usage_limit_per_user'] ?? 0 ) );
        $coupon->set_minimum_amount( floatval( $_POST['minimum_amount'] ?? '' ) );
        $coupon->set_maximum_amount( floatval( $_POST['maximum_amount'] ?? '' ) );

        $emails = sanitize_textarea_field( $_POST['email_restrictions'] ?? '' );
        $coupon->set_email_restrictions( array_filter( array_map( 'trim', explode( ',', $emails ) ) ) );

        $expiry = sanitize_text_field( $_POST['date_expires'] ?? '' );
        if ( $expiry ) {
            $coupon->set_date_expires( strtotime( $expiry . ' 23:59:59' ) );
        } else {
            $coupon->set_date_expires( null );
        }

        // Mark as our custom coupon
        $coupon->save();
        $coupon_id = $coupon->get_id();
        update_post_meta( $coupon_id, '_ccm_custom_coupon', 'yes' );

        // Save discount rules
        $rules = self::parse_rules_from_post();
        CouponRule::save_for_coupon( $coupon_id, $rules );

        return $coupon_id;
    }

    private static function parse_rules_from_post(): array {
        $rules = [];
        $sections = [ 'standard', 'signup', 'recurring' ];

        foreach ( $sections as $section ) {
            $target_types = $_POST["rule_{$section}_target_type"] ?? [];
            $target_ids   = $_POST["rule_{$section}_target_id"] ?? [];
            $disc_types   = $_POST["rule_{$section}_discount_type"] ?? [];
            $amounts      = $_POST["rule_{$section}_amount"] ?? [];

            for ( $i = 0; $i < count( $target_types ); $i++ ) {
                $amount = floatval( $amounts[ $i ] ?? 0 );
                if ( $amount <= 0 ) continue;

                $rules[] = [
                    'target_type'     => sanitize_text_field( $target_types[ $i ] ),
                    'target_id'       => absint( $target_ids[ $i ] ?? 0 ),
                    'discount_type'   => sanitize_text_field( $disc_types[ $i ] ),
                    'discount_amount' => $amount,
                ];
            }
        }

        return $rules;
    }
}
