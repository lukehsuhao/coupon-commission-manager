<?php

namespace CouponCommissionManager\Admin\ListTables;

use CouponCommissionManager\Models\CommissionRule;
use CouponCommissionManager\Models\Partner;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class RulesListTable extends \WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'rule_group',
            'plural'   => 'rule_groups',
            'ajax'     => false,
        ] );
    }

    public function get_columns(): array {
        return [
            'partner'     => __( '夥伴', 'ccm' ),
            'coupon'      => __( '折扣碼', 'ccm' ),
            'commissions' => __( '分潤設定', 'ccm' ),
            'actions'     => __( '操作', 'ccm' ),
        ];
    }

    public function extra_tablenav( $which ): void {
        if ( 'top' !== $which ) {
            return;
        }

        $partners       = Partner::all( [ 'status' => 'active', 'per_page' => 100 ] );
        $filter_partner = absint( $_GET['filter_partner'] ?? 0 );

        echo '<div class="alignleft actions">';
        echo '<select name="filter_partner">';
        echo '<option value="">' . esc_html__( '全部夥伴', 'ccm' ) . '</option>';
        foreach ( $partners as $p ) {
            printf(
                '<option value="%d" %s>%s</option>',
                $p->id,
                selected( $filter_partner, $p->id, false ),
                esc_html( $p->name )
            );
        }
        echo '</select>';
        submit_button( __( '篩選', 'ccm' ), '', 'filter_action', false );
        echo '</div>';
    }

    public function prepare_items(): void {
        global $wpdb;

        $this->_column_headers = [
            $this->get_columns(),
            [],
            [],
        ];

        $table          = CommissionRule::table();
        $filter_partner = absint( $_GET['filter_partner'] ?? 0 );

        $where  = '1=1';
        $params = [];
        if ( $filter_partner ) {
            $where   .= ' AND partner_id = %d';
            $params[] = $filter_partner;
        }

        // Get distinct partner+coupon groups
        $sql = "SELECT partner_id, coupon_id, MIN(id) AS first_id
                FROM {$table} WHERE {$where}
                GROUP BY partner_id, coupon_id
                ORDER BY partner_id ASC, coupon_id ASC";

        if ( ! empty( $params ) ) {
            $groups = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        } else {
            $groups = $wpdb->get_results( $sql );
        }

        // Attach all rules to each group
        foreach ( $groups as &$group ) {
            $group->rules = CommissionRule::find_by_coupon( (int) $group->coupon_id );
            // Filter to only this partner's rules for this coupon
            $group->rules = array_filter( $group->rules, function ( $r ) use ( $group ) {
                return (int) $r->partner_id === (int) $group->partner_id;
            } );
        }

        $this->items = $groups;

        $this->set_pagination_args( [
            'total_items' => count( $groups ),
            'per_page'    => 999,
        ] );
    }

    public function column_partner( $item ): string {
        $partner = Partner::find( $item->partner_id );
        return $partner ? '<strong>' . esc_html( $partner->name ) . '</strong>' : '<em>' . esc_html__( '[已刪除]', 'ccm' ) . '</em>';
    }

    public function column_coupon( $item ): string {
        $coupon = get_post( $item->coupon_id );
        if ( $coupon && 'trash' !== $coupon->post_status ) {
            return '<code>' . esc_html( strtoupper( $coupon->post_title ) ) . '</code>';
        }
        return '<em>[' . esc_html__( '已刪除', 'ccm' ) . '] #' . $item->coupon_id . '</em>';
    }

    public function column_commissions( $item ): string {
        if ( empty( $item->rules ) ) {
            return '—';
        }

        $rows = [];
        foreach ( $item->rules as $rule ) {
            if ( 0 === (int) $rule->product_id ) {
                $product_label = '<em>' . esc_html__( '其他商品（預設）', 'ccm' ) . '</em>';
            } else {
                $product = wc_get_product( $rule->product_id );
                $product_label = $product
                    ? esc_html( $product->get_name() )
                    : '<em>[' . esc_html__( '已刪除', 'ccm' ) . '] #' . $rule->product_id . '</em>';
            }

            $rows[] = sprintf(
                '<tr><td style="padding:2px 8px 2px 0;">%s</td><td style="padding:2px 0;font-weight:600;">NT$%s</td></tr>',
                $product_label,
                esc_html( number_format( (float) $rule->commission_amount, 0 ) )
            );
        }

        return '<table style="border-collapse:collapse;">' . implode( '', $rows ) . '</table>';
    }

    public function column_actions( $item ): string {
        $edit_url   = admin_url( 'admin.php?page=ccm-rules&action=edit&partner_id=' . $item->partner_id . '&coupon_id=' . $item->coupon_id );
        $copy_url   = admin_url( 'admin.php?page=ccm-rules&action=new&copy_from_partner=' . $item->partner_id . '&copy_from_coupon=' . $item->coupon_id );
        $delete_url = wp_nonce_url(
            admin_url( 'admin.php?page=ccm-rules&action=delete_group&partner_id=' . $item->partner_id . '&coupon_id=' . $item->coupon_id ),
            'ccm_delete_rule_group'
        );

        return sprintf(
            '<a href="%s" class="button button-small">%s</a> <a href="%s" class="button button-small">%s</a> <a href="%s" class="button button-small" onclick="return confirm(\'%s\')" style="color:#b32d2e;">%s</a>',
            esc_url( $edit_url ),
            esc_html__( '編輯', 'ccm' ),
            esc_url( $copy_url ),
            esc_html__( '複製', 'ccm' ),
            esc_url( $delete_url ),
            esc_js( __( '確定要刪除此折扣碼的所有分潤規則嗎？', 'ccm' ) ),
            esc_html__( '刪除', 'ccm' )
        );
    }

    public function column_default( $item, $column_name ): string {
        return '';
    }
}
