<?php

namespace CouponCommissionManager\Models;

use CouponCommissionManager\Database\Schema;

class CommissionLog {

    public static function table(): string {
        return Schema::get_table_name( 'commission_logs' );
    }

    public static function find( int $id ): ?object {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    public static function find_by_order( int $order_id ): array {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d",
            $order_id
        ) );
    }

    public static function create( array $data ): ?int {
        global $wpdb;

        // Suppress duplicate key errors (idempotent)
        $wpdb->suppress_errors( true );
        $result = $wpdb->insert( self::table(), $data );
        $wpdb->suppress_errors( false );

        if ( false === $result ) {
            return null;
        }
        return (int) $wpdb->insert_id;
    }

    public static function mark_paid( int $id, ?int $user_id = null, string $note = '' ): bool {
        global $wpdb;
        return false !== $wpdb->update(
            self::table(),
            [
                'status'       => 'paid',
                'paid_at'      => current_time( 'mysql' ),
                'paid_by'      => $user_id ?: get_current_user_id(),
                'payment_note' => $note,
            ],
            [ 'id' => $id, 'status' => 'unpaid' ]
        );
    }

    public static function mark_void( int $id ): bool {
        global $wpdb;
        return false !== $wpdb->update(
            self::table(),
            [ 'status' => 'void' ],
            [ 'id' => $id, 'status' => 'unpaid' ]
        );
    }

    public static function batch_mark_paid( array $ids, ?int $user_id = null, string $note = '' ): int {
        $count = 0;
        foreach ( $ids as $id ) {
            if ( self::mark_paid( $id, $user_id, $note ) ) {
                $count++;
            }
        }
        return $count;
    }

    public static function void_by_order( int $order_id ): int {
        global $wpdb;
        $table = self::table();
        return (int) $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET status = 'void' WHERE order_id = %d AND status = 'unpaid'",
            $order_id
        ) );
    }

    public static function get_dashboard_totals( string $date_from = '', string $date_to = '' ): object {
        global $wpdb;
        $table = self::table();

        $where  = "status != 'void'";
        $params = [];

        if ( $date_from ) {
            $where   .= ' AND created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        if ( $date_to ) {
            $where   .= ' AND created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $sql = "SELECT
            COALESCE(SUM(CASE WHEN status = 'unpaid' THEN commission_total ELSE 0 END), 0) AS total_unpaid,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_total ELSE 0 END), 0) AS total_paid
            FROM {$table} WHERE {$where}";

        if ( ! empty( $params ) ) {
            return $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
        }
        return $wpdb->get_row( $sql );
    }

    public static function get_partner_summaries( string $date_from = '', string $date_to = '' ): array {
        global $wpdb;
        $logs_table    = self::table();
        $partner_table = Schema::get_table_name( 'partners' );

        $where  = "l.status != 'void'";
        $params = [];

        if ( $date_from ) {
            $where   .= ' AND l.created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        if ( $date_to ) {
            $where   .= ' AND l.created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $sql = "SELECT
            p.id AS partner_id,
            p.name AS partner_name,
            COALESCE(SUM(CASE WHEN l.status = 'unpaid' THEN l.commission_total ELSE 0 END), 0) AS unpaid_total,
            COALESCE(SUM(CASE WHEN l.status = 'paid' THEN l.commission_total ELSE 0 END), 0) AS paid_total
            FROM {$partner_table} p
            LEFT JOIN {$logs_table} l ON p.id = l.partner_id AND {$where}
            WHERE p.status = 'active'
            GROUP BY p.id, p.name
            HAVING unpaid_total > 0 OR paid_total > 0
            ORDER BY unpaid_total DESC";

        if ( ! empty( $params ) ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        }
        return $wpdb->get_results( $sql );
    }

    public static function get_unpaid_total_for_partner( int $partner_id ): float {
        global $wpdb;
        $table = self::table();
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(commission_total), 0) FROM {$table} WHERE partner_id = %d AND status = 'unpaid'",
            $partner_id
        ) );
        return (float) $result;
    }

    public static function get_paid_total_for_partner( int $partner_id ): float {
        global $wpdb;
        $table = self::table();
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(commission_total), 0) FROM {$table} WHERE partner_id = %d AND status = 'paid'",
            $partner_id
        ) );
        return (float) $result;
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
        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where   .= ' AND created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where   .= ' AND created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $orderby = $args['orderby'] ?? 'id';
        $order   = ( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = [ 'id', 'order_id', 'partner_id', 'coupon_code', 'commission_total', 'status', 'created_at', 'paid_at' ];
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
        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where   .= ' AND created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where   .= ' AND created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        if ( ! empty( $params ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
        }
        return (int) $wpdb->get_var( $sql );
    }
}
