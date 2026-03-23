<?php

namespace CouponCommissionManager\Models;

use CouponCommissionManager\Database\Schema;

class Partner {

    public static function table(): string {
        return Schema::get_table_name( 'partners' );
    }

    public static function find( int $id ): ?object {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
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
            $where   .= ' AND (name LIKE %s OR email LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
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

    public static function count_active(): int {
        return self::count( [ 'status' => 'active' ] );
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

        // Check if partner has commission logs
        $logs_table = Schema::get_table_name( 'commission_logs' );
        $has_logs   = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$logs_table} WHERE partner_id = %d",
            $id
        ) );

        if ( $has_logs > 0 ) {
            return false; // Cannot hard delete partner with logs
        }

        return false !== $wpdb->delete( self::table(), [ 'id' => $id ] );
    }
}
