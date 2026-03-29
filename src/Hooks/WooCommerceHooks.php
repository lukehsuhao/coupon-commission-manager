<?php

namespace CouponCommissionManager\Hooks;

use CouponCommissionManager\Services\CommissionCalculator;

class WooCommerceHooks {

    public static function register(): void {
        // Commission triggers
        add_action( 'woocommerce_order_status_processing', [ self::class, 'on_order_complete' ] );
        add_action( 'woocommerce_order_status_completed', [ self::class, 'on_order_complete' ] );
        add_action( 'woocommerce_order_status_cancelled', [ self::class, 'on_order_void' ] );
        add_action( 'woocommerce_order_status_refunded', [ self::class, 'on_order_void' ] );
        add_action( 'woocommerce_order_status_failed', [ self::class, 'on_order_void' ] );

        // AJAX search
        add_action( 'wp_ajax_ccm_search_products', [ self::class, 'ajax_search_products' ] );
        add_action( 'wp_ajax_ccm_search_coupons', [ self::class, 'ajax_search_coupons' ] );
        add_action( 'wp_ajax_ccm_create_coupon', [ self::class, 'ajax_create_coupon' ] );

        // Commission notification AJAX
        add_action( 'wp_ajax_ccm_preview_commission_email', [ self::class, 'ajax_preview_commission_email' ] );
        add_action( 'wp_ajax_ccm_send_commission_email', [ self::class, 'ajax_send_commission_email' ] );

        // Notification on new partner application
        add_action( 'ccm_partner_application_submitted', [ self::class, 'notify_admin_new_application' ] );
    }

    public static function on_order_complete( int $order_id ): void {
        CommissionCalculator::process_order( $order_id );
    }

    public static function on_order_void( int $order_id ): void {
        CommissionCalculator::void_order( $order_id );
    }

    public static function ajax_search_products(): void {
        check_ajax_referer( 'ccm_admin_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error();
        }

        $term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

        // Supported product types (including subscriptions)
        $product_types = [ 'product', 'product_variation' ];

        global $wpdb;

        if ( empty( $term ) ) {
            // No search term: return 10 most recent products + variations
            $results = $wpdb->get_results(
                "SELECT p.ID, p.post_title, p.post_type, p.post_parent
                 FROM {$wpdb->posts} p
                 WHERE p.post_type IN ('product', 'product_variation')
                 AND p.post_status = 'publish'
                 ORDER BY p.ID DESC
                 LIMIT 20"
            );
        } else {
            // Search by term: match product title or variation parent title
            $like = '%' . $wpdb->esc_like( $term ) . '%';
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT p.ID, p.post_title, p.post_type, p.post_parent
                 FROM {$wpdb->posts} p
                 WHERE p.post_type IN ('product', 'product_variation')
                 AND p.post_status = 'publish'
                 AND (
                    p.post_title LIKE %s
                    OR p.ID IN (
                        SELECT v.ID FROM {$wpdb->posts} v
                        WHERE v.post_type = 'product_variation'
                        AND v.post_parent IN (
                            SELECT pp.ID FROM {$wpdb->posts} pp
                            WHERE pp.post_title LIKE %s
                        )
                    )
                 )
                 ORDER BY p.post_type ASC, p.ID DESC
                 LIMIT 30",
                $like, $like
            ) );
        }

        $data = [];
        foreach ( $results as $row ) {
            $product = wc_get_product( $row->ID );
            if ( ! $product ) {
                continue;
            }

            $label = self::get_product_display_name( $product );

            // Skip parent variable/subscription products (only show their variations)
            if ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) ) {
                // Add child variations instead
                $children = $product->get_children();
                foreach ( $children as $child_id ) {
                    $child = wc_get_product( $child_id );
                    if ( $child ) {
                        $data[] = [
                            'id'    => (int) $child_id,
                            'label' => self::get_product_display_name( $child ),
                            'value' => self::get_product_display_name( $child ),
                        ];
                    }
                }
                // Also add the parent as an option (for "all variations" rule)
                $data[] = [
                    'id'    => (int) $product->get_id(),
                    'label' => $label . ' — 所有變化',
                    'value' => $label . ' — 所有變化',
                ];
                continue;
            }

            $data[] = [
                'id'    => (int) $product->get_id(),
                'label' => $label,
                'value' => $label,
            ];
        }

        // Remove duplicates by ID
        $seen = [];
        $unique = [];
        foreach ( $data as $item ) {
            if ( ! isset( $seen[ $item['id'] ] ) ) {
                $seen[ $item['id'] ] = true;
                $unique[] = $item;
            }
        }

        wp_send_json( array_values( array_slice( $unique, 0, 20 ) ) );
    }

    /**
     * Get a readable display name for a product or variation.
     */
    private static function get_product_display_name( $product ): string {
        if ( $product->is_type( 'variation' ) || $product->is_type( 'subscription_variation' ) ) {
            $parent = wc_get_product( $product->get_parent_id() );
            $parent_name = $parent ? $parent->get_name() : '#' . $product->get_parent_id();
            $attributes = $product->get_variation_attributes();
            $attr_parts = [];
            foreach ( $attributes as $key => $value ) {
                if ( $value ) {
                    $attr_parts[] = $value;
                }
            }
            $attr_str = implode( ', ', $attr_parts );
            return $parent_name . ( $attr_str ? ' — ' . $attr_str : ' — 變化 #' . $product->get_id() );
        }

        $type_label = '';
        if ( $product->is_type( 'subscription' ) ) {
            $type_label = ' [訂閱]';
        } elseif ( $product->is_type( 'variable-subscription' ) ) {
            $type_label = ' [可變訂閱]';
        }

        return $product->get_name() . $type_label;
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
                'label' => strtoupper( $row->post_title ),
                'value' => strtoupper( $row->post_title ),
            ];
        }

        // Allow creating new coupon
        $exact = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'shop_coupon' AND post_status = 'publish'
             AND post_title = %s",
            $term
        ) );

        if ( ! $exact && ! empty( $term ) ) {
            $data[] = [
                'id'       => 0,
                'label'    => '+ 建立新折扣碼「' . strtoupper( $term ) . '」',
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
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
        if ( empty( $code ) ) {
            wp_send_json_error( [ 'message' => 'No code provided' ] );
        }

        // Check if already exists
        $existing = wc_get_coupon_id_by_code( $code );
        if ( $existing ) {
            wp_send_json_success( [ 'id' => $existing, 'code' => strtoupper( $code ) ] );
            return;
        }

        $coupon = new \WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( 'percent' );
        $coupon->set_amount( 0 );
        $coupon->set_individual_use( false );
        $coupon->save();

        wp_send_json_success( [ 'id' => $coupon->get_id(), 'code' => strtoupper( $code ) ] );
    }

    /**
     * AJAX: Preview commission notification emails.
     */
    public static function ajax_preview_commission_email(): void {
        check_ajax_referer( 'ccm_admin_action', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => '權限不足' ] );
        }

        $log_ids = array_map( 'absint', $_POST['log_ids'] ?? [] );
        if ( empty( $log_ids ) ) {
            wp_send_json_error( [ 'message' => '未選擇任何分潤紀錄' ] );
        }

        $previews = \CouponCommissionManager\Services\CommissionNotificationService::preview( $log_ids );
        wp_send_json_success( [ 'partners' => $previews ] );
    }

    /**
     * AJAX: Send commission notification emails and mark as paid.
     */
    public static function ajax_send_commission_email(): void {
        check_ajax_referer( 'ccm_admin_action', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => '權限不足' ] );
        }

        $log_ids   = array_map( 'absint', $_POST['log_ids'] ?? [] );
        $overrides = [];

        // Parse per-partner overrides from the modal form
        if ( ! empty( $_POST['overrides'] ) && is_array( $_POST['overrides'] ) ) {
            foreach ( $_POST['overrides'] as $pid => $override ) {
                $overrides[ (int) $pid ] = [
                    'subject'   => sanitize_text_field( $override['subject'] ?? '' ),
                    'body_text' => wp_unslash( $override['body_text'] ?? '' ),
                    'email'     => sanitize_email( $override['email'] ?? '' ),
                ];
            }
        }

        if ( empty( $log_ids ) ) {
            wp_send_json_error( [ 'message' => '未選擇任何分潤紀錄' ] );
        }

        $results = \CouponCommissionManager\Services\CommissionNotificationService::send_and_mark_paid( $log_ids, $overrides );
        wp_send_json_success( [ 'results' => $results ] );
    }

    /**
     * Send notification email to admin when a new partner applies.
     */
    public static function notify_admin_new_application( array $data ): void {
        $settings = get_option( 'ccm_settings', [] );
        $admin_email = $settings['admin_notification_email'] ?? get_option( 'admin_email' );

        if ( empty( $admin_email ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: partner name */
            __( '[%s] 新的分潤夥伴申請', 'ccm' ),
            get_bloginfo( 'name' )
        );

        $body = sprintf(
            __( "有新的分潤夥伴申請：\n\n姓名：%s\nEmail：%s\n\n請至後台審核：%s", 'ccm' ),
            $data['name'] ?? '',
            $data['email'] ?? '',
            admin_url( 'admin.php?page=ccm-applications' )
        );

        wp_mail( $admin_email, $subject, $body );
    }
}
