<?php

namespace CouponCommissionManager\Models;

use CouponCommissionManager\Database\Schema;

class Application {

    public static function table(): string {
        return Schema::get_table_name( 'applications' );
    }

    public static function find( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $id ) );
    }

    public static function all( array $args = [] ): array {
        global $wpdb;
        $table = self::table();

        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where   .= ' AND (name LIKE %s OR email LIKE %s OR desired_coupon_code LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $orderby = $args['orderby'] ?? 'id';
        $order   = ( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = [ 'id', 'name', 'email', 'status', 'created_at' ];
        if ( ! in_array( $orderby, $allowed, true ) ) {
            $orderby = 'id';
        }

        $limit  = absint( $args['per_page'] ?? 20 );
        $offset = absint( $args['offset'] ?? 0 );

        $sql      = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    public static function count( array $args = [] ): int {
        global $wpdb;
        $table = self::table();

        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where   .= ' AND (name LIKE %s OR email LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        if ( ! empty( $params ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    public static function count_by_status( string $status ): int {
        return self::count( [ 'status' => $status ] );
    }

    public static function has_pending_by_email( string $email ): bool {
        global $wpdb;
        $table = self::table();
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE email = %s AND status = 'pending'",
            $email
        ) );
    }

    public static function create( array $data ): int {
        global $wpdb;
        $wpdb->insert( self::table(), $data );
        return (int) $wpdb->insert_id;
    }

    public static function update( int $id, array $data ): bool {
        global $wpdb;
        return false !== $wpdb->update( self::table(), $data, [ 'id' => $id ] );
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        return false !== $wpdb->delete( self::table(), [ 'id' => $id ] );
    }
}
