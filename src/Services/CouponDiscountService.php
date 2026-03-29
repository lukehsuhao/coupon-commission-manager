<?php

namespace CouponCommissionManager\Services;

use CouponCommissionManager\Models\CouponRule;

class CouponDiscountService {

    public static function register(): void {
        // Override WC discount calculation for our custom coupons
        add_filter( 'woocommerce_coupon_get_discount_amount', [ self::class, 'get_discount_amount' ], 20, 5 );

        // Register subscription coupon types if WCS active
        if ( class_exists( 'WC_Subscriptions' ) ) {
            add_filter( 'woocommerce_coupon_discount_types', [ self::class, 'add_subscription_types' ] );
        }
    }

    /**
     * Check if a coupon is managed by our system.
     */
    private static function is_ccm_coupon( $coupon ): bool {
        $coupon_id = $coupon->get_id();
        return get_post_meta( $coupon_id, '_ccm_custom_coupon', true ) === 'yes';
    }

    /**
     * Override WC discount amount calculation for our custom coupons.
     *
     * @param float      $discount   The calculated discount amount
     * @param float      $price      The price to discount
     * @param array|object $cart_item The cart item
     * @param bool       $single     Whether this is a single item
     * @param \WC_Coupon $coupon     The coupon object
     * @return float
     */
    public static function get_discount_amount( $discount, $price, $cart_item, $single, $coupon ): float {
        if ( ! self::is_ccm_coupon( $coupon ) ) {
            return $discount;
        }

        $coupon_id = $coupon->get_id();

        // Get the product from the cart item
        if ( is_array( $cart_item ) && isset( $cart_item['data'] ) ) {
            $product = $cart_item['data'];
        } elseif ( is_object( $cart_item ) && method_exists( $cart_item, 'get_product' ) ) {
            $product = $cart_item->get_product();
        } else {
            return $discount;
        }

        if ( ! $product ) {
            return $discount;
        }

        // Determine which type of discount to look for
        $type_prefix = '';

        // Check if this is a subscription renewal context
        if ( self::is_renewal_context() ) {
            $type_prefix = 'recurring';
        }

        // Find matching rule
        $rule = CouponRule::find_matching_rule( $coupon_id, $product, $type_prefix );

        if ( ! $rule ) {
            return 0; // No rule = no discount
        }

        // Calculate discount based on rule type
        return self::calculate_from_rule( $rule, $price );
    }

    /**
     * Calculate discount amount from a rule.
     */
    private static function calculate_from_rule( object $rule, float $price ): float {
        $amount = (float) $rule->discount_amount;
        $type   = $rule->discount_type;

        // Percentage types
        if ( in_array( $type, [ 'percent', 'sign_up_percent', 'recurring_percent' ], true ) ) {
            return round( $price * ( $amount / 100 ), wc_get_price_decimals() );
        }

        // Fixed types - don't exceed price
        return min( $amount, $price );
    }

    /**
     * Check if we're in a subscription renewal context.
     */
    private static function is_renewal_context(): bool {
        if ( ! class_exists( 'WC_Subscriptions' ) ) {
            return false;
        }

        // Check if WCS indicates this is a renewal
        if ( function_exists( 'wcs_cart_contains_renewal' ) && wcs_cart_contains_renewal() ) {
            return true;
        }

        return false;
    }

    /**
     * Add our subscription discount types to WC types list.
     */
    public static function add_subscription_types( array $types ): array {
        $types['sign_up_fee']        = __( '開通費固定折扣', 'ccm' );
        $types['sign_up_percent']    = __( '開通費百分比折扣', 'ccm' );
        $types['recurring_fee']      = __( '續訂費固定折扣', 'ccm' );
        $types['recurring_percent']  = __( '續訂費百分比折扣', 'ccm' );
        return $types;
    }
}
