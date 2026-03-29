<?php

namespace CouponCommissionManager\Models;

use CouponCommissionManager\Database\Schema;

class CouponRule {

    public static function table(): string {
        return Schema::get_table_name( 'coupon_rules' );
    }

    /**
     * Get all rules for a coupon.
     */
    public static function find_by_coupon( int $coupon_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM %i WHERE coupon_id = %d ORDER BY FIELD(target_type, 'variation', 'product', 'category', 'all'), id",
            self::table(), $coupon_id
        ) );
    }

    /**
     * Get rules for a coupon grouped by discount_type prefix (standard vs subscription).
     */
    public static function find_by_coupon_grouped( int $coupon_id ): array {
        $rules = self::find_by_coupon( $coupon_id );

        $grouped = [
            'standard'    => [], // fixed, percent
            'signup'      => [], // sign_up_fee, sign_up_percent
            'recurring'   => [], // recurring_fee, recurring_percent
        ];

        foreach ( $rules as $rule ) {
            if ( str_starts_with( $rule->discount_type, 'sign_up' ) ) {
                $grouped['signup'][] = $rule;
            } elseif ( str_starts_with( $rule->discount_type, 'recurring' ) ) {
                $grouped['recurring'][] = $rule;
            } else {
                $grouped['standard'][] = $rule;
            }
        }

        return $grouped;
    }

    /**
     * Save rules for a coupon (replace all).
     */
    public static function save_for_coupon( int $coupon_id, array $rules ): void {
        global $wpdb;
        $table = self::table();

        // Delete existing rules
        $wpdb->delete( $table, [ 'coupon_id' => $coupon_id ] );

        // Insert new rules
        foreach ( $rules as $rule ) {
            $wpdb->insert( $table, [
                'coupon_id'       => $coupon_id,
                'target_type'     => $rule['target_type'],
                'target_id'       => (int) ( $rule['target_id'] ?? 0 ),
                'discount_type'   => $rule['discount_type'],
                'discount_amount' => (float) $rule['discount_amount'],
            ] );
        }
    }

    /**
     * Delete all rules for a coupon.
     */
    public static function delete_by_coupon( int $coupon_id ): void {
        global $wpdb;
        $wpdb->delete( self::table(), [ 'coupon_id' => $coupon_id ] );
    }

    /**
     * Find the best matching rule for a product in the cart.
     * Priority: variation > product > category > all
     */
    public static function find_matching_rule( int $coupon_id, $product, string $type_prefix = '' ): ?object {
        global $wpdb;
        $table = self::table();

        $product_id   = $product->get_id();
        $parent_id    = $product->get_parent_id() ?: $product_id;
        $category_ids = $product->get_category_ids();
        if ( empty( $category_ids ) && $parent_id !== $product_id ) {
            $parent = wc_get_product( $parent_id );
            $category_ids = $parent ? $parent->get_category_ids() : [];
        }

        // Build discount type filter
        if ( $type_prefix === 'sign_up' ) {
            $type_condition = "AND discount_type IN ('sign_up_fee', 'sign_up_percent')";
        } elseif ( $type_prefix === 'recurring' ) {
            $type_condition = "AND discount_type IN ('recurring_fee', 'recurring_percent')";
        } else {
            $type_condition = "AND discount_type IN ('fixed', 'percent')";
        }

        // 1. Exact variation match
        if ( $parent_id !== $product_id ) {
            $rule = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE coupon_id = %d AND target_type = 'variation' AND target_id = %d {$type_condition} LIMIT 1",
                $coupon_id, $product_id
            ) );
            if ( $rule ) return $rule;
        }

        // 2. Parent product match
        $rule = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE coupon_id = %d AND target_type = 'product' AND target_id = %d {$type_condition} LIMIT 1",
            $coupon_id, $parent_id
        ) );
        if ( $rule ) return $rule;

        // 3. Category match (first matching category wins)
        if ( ! empty( $category_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
            $rule = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE coupon_id = %d AND target_type = 'category' AND target_id IN ({$placeholders}) {$type_condition} LIMIT 1",
                $coupon_id, ...$category_ids
            ) );
            if ( $rule ) return $rule;
        }

        // 4. Default (all)
        $rule = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE coupon_id = %d AND target_type = 'all' AND target_id = 0 {$type_condition} LIMIT 1",
            $coupon_id
        ) );

        return $rule;
    }

    /**
     * Check if a coupon has any custom rules.
     */
    public static function has_rules( int $coupon_id ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE coupon_id = %d",
            self::table(), $coupon_id
        ) );
    }

    /**
     * Get a summary of rules for display.
     */
    public static function get_summary( int $coupon_id ): string {
        $rules = self::find_by_coupon( $coupon_id );
        if ( empty( $rules ) ) return '';

        $parts = [];
        $has_subscription = false;

        foreach ( $rules as $rule ) {
            if ( str_starts_with( $rule->discount_type, 'sign_up' ) || str_starts_with( $rule->discount_type, 'recurring' ) ) {
                $has_subscription = true;
                continue;
            }

            $amount = $rule->discount_type === 'percent'
                ? $rule->discount_amount . '%'
                : 'NT$ ' . number_format( (float) $rule->discount_amount );

            if ( $rule->target_type === 'all' ) {
                $parts[] = "預設 {$amount}";
            }
        }

        $specific_count = count( array_filter( $rules, fn( $r ) => $r->target_type !== 'all' && ! str_starts_with( $r->discount_type, 'sign_up' ) && ! str_starts_with( $r->discount_type, 'recurring' ) ) );
        if ( $specific_count > 0 ) {
            $parts[] = "+{$specific_count} 項指定折扣";
        }
        if ( $has_subscription ) {
            $parts[] = '含訂閱折扣';
        }

        return implode( '、', $parts );
    }
}
