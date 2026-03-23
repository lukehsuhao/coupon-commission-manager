<?php

namespace CouponCommissionManager\Admin\ListTables;

use CouponCommissionManager\Models\Application;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ApplicationsListTable extends \WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'application',
            'plural'   => 'applications',
            'ajax'     => false,
        ] );
    }

    public function get_columns(): array {
        return [
            'name'                => __( '姓名', 'ccm' ),
            'email'               => __( 'Email', 'ccm' ),
            'company_name'        => __( '公司名稱', 'ccm' ),
            'desired_coupon_code' => __( '申請折扣碼', 'ccm' ),
            'status'              => __( '狀態', 'ccm' ),
            'created_at'          => __( '申請時間', 'ccm' ),
            'actions'             => __( '操作', 'ccm' ),
        ];
    }

    public function get_sortable_columns(): array {
        return [
            'name'       => [ 'name', false ],
            'created_at' => [ 'created_at', true ],
            'status'     => [ 'status', false ],
        ];
    }

    protected function get_views(): array {
        $current = sanitize_text_field( $_GET['status'] ?? '' );
        $base    = admin_url( 'admin.php?page=ccm-applications' );

        $counts = [
            ''         => Application::count(),
            'pending'  => Application::count_by_status( 'pending' ),
            'approved' => Application::count_by_status( 'approved' ),
            'rejected' => Application::count_by_status( 'rejected' ),
        ];

        $labels = [
            ''         => __( '全部', 'ccm' ),
            'pending'  => __( '待審核', 'ccm' ),
            'approved' => __( '已通過', 'ccm' ),
            'rejected' => __( '已駁回', 'ccm' ),
        ];

        $views = [];
        foreach ( $labels as $status => $label ) {
            $url   = $status ? add_query_arg( 'status', $status, $base ) : $base;
            $class = $current === $status ? ' class="current"' : '';
            $views[ $status ?: 'all' ] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( $url ),
                $class,
                $label,
                $counts[ $status ]
            );
        }

        return $views;
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
            'per_page' => $per_page,
            'offset'   => ( $page - 1 ) * $per_page,
            'orderby'  => sanitize_text_field( $_GET['orderby'] ?? 'id' ),
            'order'    => sanitize_text_field( $_GET['order'] ?? 'DESC' ),
            'status'   => sanitize_text_field( $_GET['status'] ?? '' ),
            'search'   => sanitize_text_field( $_GET['s'] ?? '' ),
        ];

        $args = array_filter( $args, function ( $v ) { return '' !== $v; } );

        $this->items = Application::all( $args );
        $total       = Application::count( $args );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
        ] );
    }

    public function column_name( $item ): string {
        $view_url = admin_url( 'admin.php?page=ccm-applications&action=view&id=' . $item->id );
        return sprintf( '<strong><a href="%s">%s</a></strong>', esc_url( $view_url ), esc_html( $item->name ) );
    }

    public function column_email( $item ): string {
        return esc_html( $item->email );
    }

    public function column_company_name( $item ): string {
        return esc_html( $item->company_name ?: '—' );
    }

    public function column_desired_coupon_code( $item ): string {
        return '<code>' . esc_html( strtoupper( $item->desired_coupon_code ) ) . '</code>';
    }

    public function column_status( $item ): string {
        switch ( $item->status ) {
            case 'pending':
                return '<span class="ccm-badge ccm-badge-unpaid">' . esc_html__( '待審核', 'ccm' ) . '</span>';
            case 'approved':
                return '<span class="ccm-badge ccm-badge-paid">' . esc_html__( '已通過', 'ccm' ) . '</span>';
            case 'rejected':
                return '<span class="ccm-badge ccm-badge-void">' . esc_html__( '已駁回', 'ccm' ) . '</span>';
            default:
                return esc_html( $item->status );
        }
    }

    public function column_created_at( $item ): string {
        return esc_html( substr( $item->created_at, 0, 10 ) );
    }

    public function column_actions( $item ): string {
        $view_url = admin_url( 'admin.php?page=ccm-applications&action=view&id=' . $item->id );
        $buttons  = '<a href="' . esc_url( $view_url ) . '" class="button button-small">' . esc_html__( '查看', 'ccm' ) . '</a>';

        if ( 'pending' === $item->status ) {
            $approve_url = wp_nonce_url(
                admin_url( 'admin.php?page=ccm-applications&action=approve&id=' . $item->id ),
                'ccm_approve_application'
            );
            $reject_url = wp_nonce_url(
                admin_url( 'admin.php?page=ccm-applications&action=reject&id=' . $item->id ),
                'ccm_reject_application'
            );
            $buttons .= ' <a href="' . esc_url( $approve_url ) . '" class="button button-small button-primary">' . esc_html__( '通過', 'ccm' ) . '</a>';
            $buttons .= ' <a href="' . esc_url( $reject_url ) . '" class="button button-small" onclick="return confirm(\'' . esc_js( __( '確定要駁回此申請嗎？', 'ccm' ) ) . '\')" style="color:#b32d2e;">' . esc_html__( '駁回', 'ccm' ) . '</a>';
        }

        return $buttons;
    }

    public function column_default( $item, $column_name ): string {
        return esc_html( $item->$column_name ?? '' );
    }
}
