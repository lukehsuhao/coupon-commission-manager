<?php

namespace CouponCommissionManager\Admin\ListTables;

use CouponCommissionManager\Models\Partner;
use CouponCommissionManager\Models\CommissionRule;
use CouponCommissionManager\Models\CommissionLog;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PartnersListTable extends \WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'partner',
            'plural'   => 'partners',
            'ajax'     => false,
        ] );
    }

    public function get_columns(): array {
        return [
            'cb'                => '<input type="checkbox" />',
            'name'              => __( '姓名', 'ccm' ),
            'company_info'      => __( '公司/統編', 'ccm' ),
            'email'             => __( 'Email', 'ccm' ),
            'bank_info'         => __( '銀行資訊', 'ccm' ),
            'status'            => __( '狀態', 'ccm' ),
            'coupons'           => __( '折扣碼數', 'ccm' ),
            'unpaid'            => __( '未結算', 'ccm' ),
            'paid'              => __( '已支付', 'ccm' ),
        ];
    }

    public function get_sortable_columns(): array {
        return [
            'name'       => [ 'name', false ],
            'status'     => [ 'status', false ],
            'created_at' => [ 'created_at', false ],
        ];
    }

    public function get_bulk_actions(): array {
        return [
            'activate'   => __( '啟用', 'ccm' ),
            'deactivate' => __( '停用', 'ccm' ),
        ];
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
            'search'   => sanitize_text_field( $_GET['s'] ?? '' ),
        ];

        $this->items = Partner::all( $args );
        $total       = Partner::count( $args );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
        ] );
    }

    public function column_cb( $item ): string {
        return sprintf( '<input type="checkbox" name="partner_ids[]" value="%d" />', $item->id );
    }

    public function column_name( $item ): string {
        $edit_url   = admin_url( 'admin.php?page=ccm-partners&action=edit&id=' . $item->id );
        $toggle_url = wp_nonce_url(
            admin_url( 'admin.php?page=ccm-partners&toggle=' . $item->id ),
            'ccm_toggle_partner'
        );
        $toggle_label = 'active' === $item->status ? __( '停用', 'ccm' ) : __( '啟用', 'ccm' );

        $actions = [
            'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . __( '編輯', 'ccm' ) . '</a>',
            'toggle' => '<a href="' . esc_url( $toggle_url ) . '">' . $toggle_label . '</a>',
        ];

        return sprintf( '<strong><a href="%s">%s</a></strong>%s',
            esc_url( $edit_url ),
            esc_html( $item->name ),
            $this->row_actions( $actions )
        );
    }

    public function column_company_info( $item ): string {
        $parts = [];
        if ( ! empty( $item->company_name ) ) {
            $parts[] = esc_html( $item->company_name );
        }
        if ( ! empty( $item->tax_id ) ) {
            $parts[] = '<span style="color:#666;font-size:12px;">' . esc_html( $item->tax_id ) . '</span>';
        }
        return $parts ? implode( '<br>', $parts ) : '—';
    }

    public function column_email( $item ): string {
        return esc_html( $item->email ?: '—' );
    }

    public function column_bank_info( $item ): string {
        if ( empty( $item->bank_account ) ) {
            return '—';
        }
        $parts = [];
        if ( $item->bank_name ) {
            $parts[] = esc_html( $item->bank_name );
        }
        $parts[] = esc_html( $item->bank_account );
        if ( $item->bank_account_name ) {
            $parts[] = '(' . esc_html( $item->bank_account_name ) . ')';
        }
        return implode( ' ', $parts );
    }

    public function column_notes( $item ): string {
        if ( empty( $item->notes ) ) {
            return '—';
        }
        return esc_html( mb_strimwidth( $item->notes, 0, 30, '...' ) );
    }

    public function column_status( $item ): string {
        if ( 'active' === $item->status ) {
            return '<span class="ccm-badge ccm-badge-active">' . esc_html__( '啟用', 'ccm' ) . '</span>';
        }
        return '<span class="ccm-badge ccm-badge-inactive">' . esc_html__( '停用', 'ccm' ) . '</span>';
    }

    public function column_coupons( $item ): string {
        return (string) CommissionRule::count_coupons_for_partner( $item->id );
    }

    public function column_unpaid( $item ): string {
        $total = CommissionLog::get_unpaid_total_for_partner( $item->id );
        return 'NT$' . number_format( $total, 0 );
    }

    public function column_paid( $item ): string {
        $total = CommissionLog::get_paid_total_for_partner( $item->id );
        return 'NT$' . number_format( $total, 0 );
    }

    public function column_default( $item, $column_name ): string {
        return esc_html( $item->$column_name ?? '' );
    }
}
