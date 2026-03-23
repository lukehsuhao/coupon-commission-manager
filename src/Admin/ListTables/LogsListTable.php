<?php

namespace CouponCommissionManager\Admin\ListTables;

use CouponCommissionManager\Models\CommissionLog;
use CouponCommissionManager\Models\Partner;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class LogsListTable extends \WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'commission_log',
            'plural'   => 'commission_logs',
            'ajax'     => false,
        ] );
    }

    public function get_columns(): array {
        return [
            'cb'               => '<input type="checkbox" />',
            'created_at'       => __( '日期', 'ccm' ),
            'order_id'         => __( '訂單', 'ccm' ),
            'partner'          => __( '夥伴', 'ccm' ),
            'coupon_code'      => __( '折扣碼', 'ccm' ),
            'product_name'     => __( '商品', 'ccm' ),
            'quantity'         => __( '數量', 'ccm' ),
            'commission_total' => __( '分潤', 'ccm' ),
            'status'           => __( '狀態', 'ccm' ),
            'paid_at'          => __( '付款日', 'ccm' ),
            'actions'          => __( '操作', 'ccm' ),
        ];
    }

    public function get_sortable_columns(): array {
        return [
            'created_at'       => [ 'created_at', true ],
            'commission_total' => [ 'commission_total', false ],
            'status'           => [ 'status', false ],
        ];
    }

    public function get_bulk_actions(): array {
        return [
            'mark_paid' => __( '標記已付', 'ccm' ),
        ];
    }

    public function extra_filters(): void {
        $partners       = Partner::all( [ 'per_page' => 100 ] );
        $filter_partner = absint( $_GET['partner_id'] ?? 0 );
        $filter_status  = sanitize_text_field( $_GET['status'] ?? '' );
        $date_from      = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to        = sanitize_text_field( $_GET['date_to'] ?? '' );

        echo '<div class="ccm-filters" style="margin:12px 0;">';

        echo '<label>' . esc_html__( '日期：', 'ccm' ) . '</label>';
        echo '<input type="text" name="date_from" class="ccm-datepicker" value="' . esc_attr( $date_from ) . '" placeholder="起始日期" style="width:110px;"> ~ ';
        echo '<input type="text" name="date_to" class="ccm-datepicker" value="' . esc_attr( $date_to ) . '" placeholder="結束日期" style="width:110px;">';

        echo '&nbsp;&nbsp;<label>' . esc_html__( '夥伴：', 'ccm' ) . '</label>';
        echo '<select name="partner_id">';
        echo '<option value="">' . esc_html__( '全部', 'ccm' ) . '</option>';
        foreach ( $partners as $p ) {
            printf( '<option value="%d" %s>%s</option>', $p->id, selected( $filter_partner, $p->id, false ), esc_html( $p->name ) );
        }
        echo '</select>';

        echo '&nbsp;&nbsp;<label>' . esc_html__( '狀態：', 'ccm' ) . '</label>';
        echo '<select name="status">';
        echo '<option value="">' . esc_html__( '全部', 'ccm' ) . '</option>';
        echo '<option value="unpaid"' . selected( $filter_status, 'unpaid', false ) . '>' . esc_html__( '未付', 'ccm' ) . '</option>';
        echo '<option value="paid"' . selected( $filter_status, 'paid', false ) . '>' . esc_html__( '已付', 'ccm' ) . '</option>';
        echo '<option value="void"' . selected( $filter_status, 'void', false ) . '>' . esc_html__( '作廢', 'ccm' ) . '</option>';
        echo '</select>';

        submit_button( __( '篩選', 'ccm' ), 'secondary', 'filter_action', false );

        // Export button
        $export_url = add_query_arg( [
            'action'     => 'ccm_export_csv',
            'partner_id' => $filter_partner,
            'status'     => $filter_status,
            'date_from'  => $date_from,
            'date_to'    => $date_to,
            '_wpnonce'   => wp_create_nonce( 'ccm_export_csv' ),
        ], admin_url( 'admin-post.php' ) );

        echo '&nbsp;<a href="' . esc_url( $export_url ) . '" class="button">' . esc_html__( '匯出 CSV', 'ccm' ) . '</a>';

        echo '</div>';
    }

    public function prepare_items(): void {
        $per_page = 20;
        $page     = $this->get_pagenum();

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];

        $args = [
            'per_page'   => $per_page,
            'offset'     => ( $page - 1 ) * $per_page,
            'orderby'    => sanitize_text_field( $_GET['orderby'] ?? 'created_at' ),
            'order'      => sanitize_text_field( $_GET['order'] ?? 'DESC' ),
            'partner_id' => absint( $_GET['partner_id'] ?? 0 ) ?: '',
            'coupon_id'  => absint( $_GET['coupon_id'] ?? 0 ) ?: '',
            'status'     => sanitize_text_field( $_GET['status'] ?? '' ),
            'date_from'  => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to'    => sanitize_text_field( $_GET['date_to'] ?? '' ),
        ];

        $args = array_filter( $args, function ( $v ) { return '' !== $v; } );

        $this->items = CommissionLog::all( $args );
        $total       = CommissionLog::count( $args );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
        ] );
    }

    public function column_cb( $item ): string {
        if ( 'unpaid' !== $item->status ) {
            return '';
        }
        return sprintf( '<input type="checkbox" name="log_ids[]" value="%d" />', $item->id );
    }

    public function column_created_at( $item ): string {
        return esc_html( substr( $item->created_at, 0, 10 ) );
    }

    public function column_order_id( $item ): string {
        $order = wc_get_order( $item->order_id );
        if ( $order ) {
            $url = $order->get_edit_order_url();
        } else {
            $url = admin_url( 'post.php?post=' . $item->order_id . '&action=edit' );
        }
        return '<a href="' . esc_url( $url ) . '">#' . esc_html( $item->order_id ) . '</a>';
    }

    public function column_partner( $item ): string {
        static $cache = [];
        if ( ! isset( $cache[ $item->partner_id ] ) ) {
            $partner = Partner::find( $item->partner_id );
            $cache[ $item->partner_id ] = $partner ? $partner->name : __( '未知', 'ccm' );
        }
        return esc_html( $cache[ $item->partner_id ] );
    }

    public function column_coupon_code( $item ): string {
        return '<code>' . esc_html( $item->coupon_code ) . '</code>';
    }

    public function column_product_name( $item ): string {
        return esc_html( $item->product_name );
    }

    public function column_quantity( $item ): string {
        return esc_html( $item->quantity );
    }

    public function column_commission_total( $item ): string {
        return 'NT$' . number_format( (float) $item->commission_total, 0 );
    }

    public function column_status( $item ): string {
        switch ( $item->status ) {
            case 'unpaid':
                return '<span class="ccm-badge ccm-badge-unpaid">' . esc_html__( '未付', 'ccm' ) . '</span>';
            case 'paid':
                return '<span class="ccm-badge ccm-badge-paid">' . esc_html__( '已付', 'ccm' ) . '</span>';
            case 'void':
                return '<span class="ccm-badge ccm-badge-void">' . esc_html__( '作廢', 'ccm' ) . '</span>';
            default:
                return esc_html( $item->status );
        }
    }

    public function column_paid_at( $item ): string {
        return $item->paid_at ? esc_html( substr( $item->paid_at, 0, 10 ) ) : '—';
    }

    public function column_actions( $item ): string {
        if ( 'unpaid' !== $item->status ) {
            return '—';
        }

        $pay_url  = wp_nonce_url(
            admin_url( 'admin.php?page=ccm-logs&mark_paid=' . $item->id ),
            'ccm_mark_paid'
        );
        $void_url = wp_nonce_url(
            admin_url( 'admin.php?page=ccm-logs&mark_void=' . $item->id ),
            'ccm_mark_void'
        );

        return sprintf(
            '<a href="%s" class="button button-small">%s</a> <a href="%s" class="button button-small" onclick="return confirm(\'%s\')">%s</a>',
            esc_url( $pay_url ),
            esc_html__( '付款', 'ccm' ),
            esc_url( $void_url ),
            esc_js( __( '確定要作廢此筆分潤嗎？', 'ccm' ) ),
            esc_html__( '作廢', 'ccm' )
        );
    }

    public function column_default( $item, $column_name ): string {
        return esc_html( $item->$column_name ?? '' );
    }
}
