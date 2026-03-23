<?php

namespace CouponCommissionManager\Hooks;

use CouponCommissionManager\Services\CommissionCalculator;

class WooCommerceHooks {

    public static function register(): void {
        add_action( 'woocommerce_order_status_completed', [ self::class, 'on_order_completed' ] );
        add_action( 'woocommerce_order_status_processing', [ self::class, 'on_order_processing' ] );
        add_action( 'woocommerce_order_status_refunded', [ self::class, 'on_order_refunded' ] );
        add_action( 'woocommerce_order_status_cancelled', [ self::class, 'on_order_cancelled' ] );
        add_action( 'woocommerce_order_status_changed', [ self::class, 'on_order_status_changed' ], 10, 3 );

        // Show commission info on order edit page
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ self::class, 'display_order_commissions' ] );

        // AJAX endpoints
        add_action( 'wp_ajax_ccm_search_coupons', [ self::class, 'ajax_search_coupons' ] );
        add_action( 'wp_ajax_ccm_search_products', [ self::class, 'ajax_search_products' ] );
        add_action( 'wp_ajax_ccm_create_coupon', [ self::class, 'ajax_create_coupon' ] );
    }

    public static function on_order_completed( int $order_id ): void {
        CommissionCalculator::process_order( $order_id );
    }

    public static function on_order_processing( int $order_id ): void {
        $settings = get_option( 'ccm_settings', [] );
        if ( ! empty( $settings['trigger_on_processing'] ) ) {
            CommissionCalculator::process_order( $order_id );
        }
    }

    public static function on_order_refunded( int $order_id ): void {
        CommissionCalculator::void_order( $order_id );
    }

    public static function on_order_cancelled( int $order_id ): void {
        CommissionCalculator::void_order( $order_id );
    }

    public static function on_order_status_changed( int $order_id, string $old_status, string $new_status ): void {
        $trigger_statuses = [ 'completed' ];
        $settings         = get_option( 'ccm_settings', [] );
        if ( ! empty( $settings['trigger_on_processing'] ) ) {
            $trigger_statuses[] = 'processing';
        }

        $was_triggering = in_array( $old_status, $trigger_statuses, true );
        $is_triggering  = in_array( $new_status, $trigger_statuses, true );

        // Moving FROM a triggering status to a non-triggering one -> void
        if ( $was_triggering && ! $is_triggering && ! in_array( $new_status, [ 'refunded', 'cancelled' ], true ) ) {
            CommissionCalculator::void_order( $order_id );
        }
    }

    public static function display_order_commissions( $order ): void {
        $order_id = $order->get_id();
        $logs     = \CouponCommissionManager\Models\CommissionLog::find_by_order( $order_id );

        if ( empty( $logs ) ) {
            return;
        }

        $total_commission = 0;
        $partners         = [];
        foreach ( $logs as $log ) {
            if ( 'void' !== $log->status ) {
                $total_commission += (float) $log->commission_total;
            }
            if ( ! isset( $partners[ $log->partner_id ] ) ) {
                $partner = \CouponCommissionManager\Models\Partner::find( $log->partner_id );
                $partners[ $log->partner_id ] = $partner ? $partner->name : __( '未知夥伴', 'ccm' );
            }
        }

        echo '<div class="ccm-order-info" style="margin-top:12px;padding:8px 12px;background:#f8f9fa;border-left:4px solid #007cba;">';
        echo '<h4 style="margin:0 0 4px;">' . esc_html__( '分潤資訊', 'ccm' ) . '</h4>';
        echo '<p style="margin:0;">';
        echo esc_html__( '夥伴：', 'ccm' ) . esc_html( implode( ', ', $partners ) ) . '<br>';
        echo esc_html__( '分潤合計：', 'ccm' ) . 'NT$' . esc_html( number_format( $total_commission, 0 ) );
        echo '</p></div>';
    }

    public static function ajax_search_coupons(): void {
        check_ajax_referer( 'ccm_admin_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error();
        }

        $term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
        if ( empty( $term ) ) {
            wp_send_json( [] );
        }

        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type = 'shop_coupon' AND post_status = 'publish'
             AND post_title LIKE %s LIMIT 20",
            '%' . $wpdb->esc_like( $term ) . '%'
        ) );

        $data = [];
        foreach ( $results as $row ) {
            $data[] = [
                'id'    => (int) $row->ID,
                'label' => $row->post_title,
                'value' => $row->post_title,
            ];
        }

        // If no exact match found, offer to create a new coupon
        $exact_match = false;
        foreach ( $data as $d ) {
            if ( strtolower( $d['label'] ) === strtolower( $term ) ) {
                $exact_match = true;
                break;
            }
        }
        if ( ! $exact_match ) {
            $data[] = [
                'id'       => 0,
                'label'    => sprintf( '+ 建立新折扣碼「%s」', strtoupper( $term ) ),
                'value'    => strtoupper( $term ),
                'is_new'   => true,
                'new_code' => strtoupper( $term ),
            ];
        }

        wp_send_json( $data );
    }

    public static function ajax_create_coupon(): void {
        check_ajax_referer( 'ccm_admin_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error();
        }

        $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
        if ( empty( $code ) ) {
            wp_send_json_error( [ 'message' => '折扣碼不可為空' ] );
        }

        // Check duplicate
        $existing = wc_get_coupon_id_by_code( $code );
        if ( $existing ) {
            wp_send_json_success( [ 'id' => $existing, 'code' => $code ] );
            return;
        }

        $coupon = new \WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( 'fixed_cart' );
        $coupon->set_amount( 0 );
        $coupon->save();

        wp_send_json_success( [ 'id' => $coupon->get_id(), 'code' => $code ] );
    }

    public static function ajax_search_products(): void {
        check_ajax_referer( 'ccm_admin_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error();
        }

        $term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
        if ( empty( $term ) ) {
            wp_send_json( [] );
        }

        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type = 'product' AND post_status = 'publish'
             AND post_title LIKE %s LIMIT 20",
            '%' . $wpdb->esc_like( $term ) . '%'
        ) );

        $data = [];
        foreach ( $results as $row ) {
            $data[] = [
                'id'    => (int) $row->ID,
                'label' => $row->post_title,
                'value' => $row->post_title,
            ];
        }

        wp_send_json( $data );
    }
}
