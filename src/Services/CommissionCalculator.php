<?php

namespace CouponCommissionManager\Services;

use CouponCommissionManager\Models\CommissionRule;
use CouponCommissionManager\Models\CommissionLog;

class CommissionCalculator {

    public static function process_order( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Skip WooCommerce Subscriptions renewal orders if setting is enabled
        $settings = get_option( 'ccm_settings', [] );
        if ( ! empty( $settings['skip_renewal_orders'] ) && self::is_renewal_order( $order ) ) {
            return;
        }

        $coupons = $order->get_coupon_codes();
        if ( empty( $coupons ) ) {
            return;
        }

        $order_total = (float) $order->get_total();

        foreach ( $coupons as $coupon_code ) {
            $coupon_id = wc_get_coupon_id_by_code( $coupon_code );
            if ( ! $coupon_id ) {
                continue;
            }

            // Check if any rules exist for this coupon
            $rules = CommissionRule::find_by_coupon( $coupon_id );
            if ( empty( $rules ) ) {
                continue;
            }

            // Get partner_id from the first rule (all rules for a coupon belong to the same partner)
            $partner_id = (int) $rules[0]->partner_id;

            // Build rules lookup: product_id => rule
            $rules_map = [];
            foreach ( $rules as $rule ) {
                $rules_map[ (int) $rule->product_id ] = $rule;
            }

            foreach ( $order->get_items() as $item_id => $item ) {
                $product    = $item->get_product();
                if ( ! $product ) {
                    continue;
                }

                $variation_id = $product->get_id();
                $parent_id    = $product->get_parent_id() ?: $product->get_id();

                // Find matching rule priority:
                // 1. Exact variation ID match
                // 2. Parent product ID match
                // 3. Default (product_id=0)
                $rule = $rules_map[ $variation_id ] ?? $rules_map[ $parent_id ] ?? $rules_map[0] ?? null;
                $product_id = isset( $rules_map[ $variation_id ] ) ? $variation_id : $parent_id;
                if ( ! $rule ) {
                    continue;
                }

                $quantity         = $item->get_quantity();
                $commission_per   = (float) $rule->commission_amount;
                $commission_total = $commission_per * $quantity;

                // Skip logging if commission is 0 (e.g. default rule used to explicitly
                // exclude unmatched products from commission).
                if ( $commission_total <= 0 ) {
                    continue;
                }

                CommissionLog::create( [
                    'partner_id'          => $partner_id,
                    'rule_id'             => (int) $rule->id,
                    'order_id'            => $order_id,
                    'order_item_id'       => $item_id,
                    'coupon_id'           => $coupon_id,
                    'coupon_code'         => $coupon_code,
                    'product_id'          => $product_id,
                    'product_name'        => $item->get_name(),
                    'quantity'            => $quantity,
                    'commission_per_unit' => $commission_per,
                    'commission_total'    => $commission_total,
                    'order_total'         => $order_total,
                    'status'              => 'unpaid',
                ] );
            }
        }
    }

    public static function void_order( int $order_id ): void {
        CommissionLog::void_by_order( $order_id );
    }

    /**
     * Check if an order is a WooCommerce Subscriptions renewal order.
     */
    private static function is_renewal_order( $order ): bool {
        // Method 1: WooCommerce Subscriptions function (preferred)
        if ( function_exists( 'wcs_order_contains_renewal' ) ) {
            return wcs_order_contains_renewal( $order );
        }

        // Method 2: Check order meta (fallback)
        $renewal_meta = $order->get_meta( '_subscription_renewal' );
        if ( ! empty( $renewal_meta ) ) {
            return true;
        }

        // Method 3: Check if created via subscription
        if ( $order->get_created_via() === 'subscription' ) {
            return true;
        }

        return false;
    }
}
