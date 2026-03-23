<?php

namespace CouponCommissionManager\Models;

use CouponCommissionManager\Database\Schema;

class CommissionRule {

    public static function table(): string {
        return Schema::get_table_name( 'commission_rules' );
    }

    public static function find( int $id ): ?object {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    public static function find_by_coupon_product( int $coupon_id, int $product_id ): ?object {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE coupon_id = %d AND product_id = %d",
            $coupon_id,
            $product_id
        ) );
    }

    public static function find_by_coupon( int $coupon_id ): array {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE coupon_id = %d",
            $coupon_id
        ) );
    }

    public static function find_by_partner( int $partner_id ): array {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE partner_id = %d",
            $partner_id
        ) );
    }

    public static function all( array $args = [] ): array {
        global $wpdb;
        $table = self::table();

        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['partner_id'] ) ) {
            $where   .= ' AND partner_id = %d';
            $params[] = absint( $args['partner_id'] );
        }

        if ( ! empty( $args['coupon_id'] ) ) {
            $where   .= ' AND coupon_id = %d';
            $params[] = absint( $args['coupon_id'] );
        }

        $orderby = $args['orderby'] ?? 'id';
        $order   = ( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = [ 'id', 'partner_id', 'coupon_id', 'commission_amount', 'created_at' ];
        if ( ! in_array( $orderby, $allowed, true ) ) {
            $orderby = 'id';
        }

        $limit  = absint( $args['per_page'] ?? 20 );
        $offset = absint( $args['offset'] ?? 0 );

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        if ( ! empty( $params ) ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        }
        return $wpdb->get_results( $sql );
    }

    public static function count( array $args = [] ): int {
        global $wpdb;
        $table = self::table();

        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['partner_id'] ) ) {
            $where   .= ' AND partner_id = %d';
            $params[] = absint( $args['partner_id'] );
        }

        if ( ! empty( $args['coupon_id'] ) ) {
            $where   .= ' AND coupon_id = %d';
            $params[] = absint( $args['coupon_id'] );
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        if ( ! empty( $params ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    public static function count_all(): int {
        return self::count();
    }

    /**
     * @return int|false Insert ID on success, false on duplicate key.
     */
    public static function create( array $data ) {
        global $wpdb;
        $result = $wpdb->insert( self::table(), $data );
        if ( false === $result ) {
            return false;
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * @return bool|false True on success, false on duplicate key or error.
     */
    public static function update( int $id, array $data ) {
        global $wpdb;
        $result = $wpdb->update( self::table(), $data, [ 'id' => $id ] );
        return false !== $result;
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        return false !== $wpdb->delete( self::table(), [ 'id' => $id ] );
    }

    public static function delete_by_partner_coupon( int $partner_id, int $coupon_id ): int {
        global $wpdb;
        $table = self::table();
        return (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE partner_id = %d AND coupon_id = %d",
            $partner_id,
            $coupon_id
        ) );
    }

    /**
     * Count coupons linked to a partner.
     */
    public static function count_coupons_for_partner( int $partner_id ): int {
        global $wpdb;
        $table = self::table();
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT coupon_id) FROM {$table} WHERE partner_id = %d",
            $partner_id
        ) );
    }
}
