<?php

namespace CouponCommissionManager\Admin\ListTables;

use CouponCommissionManager\Models\CommissionRule;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CouponsListTable extends \WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'coupon',
            'plural'   => 'coupons',
            'ajax'     => false,
        ] );
    }

    public function get_columns(): array {
        return [
            'code'          => __( '折扣碼', 'ccm' ),
            'discount_type' => __( '折扣類型', 'ccm' ),
            'amount'        => __( '折扣金額', 'ccm' ),
            'description'   => __( '說明', 'ccm' ),
            'has_rules'     => __( '分潤規則', 'ccm' ),
            'usage_count'   => __( '已使用次數', 'ccm' ),
            'actions'       => __( '操作', 'ccm' ),
        ];
    }

    public function prepare_items(): void {
        $this->_column_headers = [
            $this->get_columns(),
            [],
            [],
        ];

        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $args = [
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $search = sanitize_text_field( $_GET['s'] ?? '' );
        if ( $search ) {
            $args['s'] = $search;
        }

        $query       = new \WP_Query( $args );
        $this->items = $query->posts;

        $this->set_pagination_args( [
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
        ] );
    }

    public function column_code( $item ): string {
        $edit_url = admin_url( 'post.php?post=' . $item->ID . '&action=edit' );
        return '<strong><a href="' . esc_url( $edit_url ) . '"><code>' . esc_html( $item->post_title ) . '</code></a></strong>';
    }

    public function column_discount_type( $item ): string {
        $type   = get_post_meta( $item->ID, 'discount_type', true );
        $labels = [
            'fixed_cart'    => __( '購物車固定折扣', 'ccm' ),
            'percent'       => __( '百分比折扣', 'ccm' ),
            'fixed_product' => __( '商品固定折扣', 'ccm' ),
        ];
        return esc_html( $labels[ $type ] ?? $type );
    }

    public function column_amount( $item ): string {
        $amount = get_post_meta( $item->ID, 'coupon_amount', true );
        $type   = get_post_meta( $item->ID, 'discount_type', true );
        if ( 'percent' === $type ) {
            return esc_html( $amount ) . '%';
        }
        return 'NT$' . esc_html( number_format( (float) $amount, 0 ) );
    }

    public function column_description( $item ): string {
        $desc = $item->post_excerpt;
        return $desc ? esc_html( mb_strimwidth( $desc, 0, 40, '...' ) ) : '—';
    }

    public function column_has_rules( $item ): string {
        $rules = CommissionRule::find_by_coupon( $item->ID );
        if ( empty( $rules ) ) {
            $new_url = admin_url( 'admin.php?page=ccm-rules&action=new' );
            return '<a href="' . esc_url( $new_url ) . '" style="color:#999;">' . esc_html__( '未設定 — 前往新增', 'ccm' ) . '</a>';
        }

        // Group by partner_id — link directly to edit page for each partner
        $by_partner = [];
        foreach ( $rules as $r ) {
            $by_partner[ (int) $r->partner_id ] = true;
        }

        $links = [];
        foreach ( array_keys( $by_partner ) as $pid ) {
            $partner = \CouponCommissionManager\Models\Partner::find( $pid );
            $name    = $partner ? $partner->name : '#' . $pid;
            $url     = admin_url( 'admin.php?page=ccm-rules&action=edit&partner_id=' . $pid . '&coupon_id=' . $item->ID );
            $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
        }

        return implode( '、', $links ) . ' (' . count( $rules ) . ')';
    }

    public function column_usage_count( $item ): string {
        $count = get_post_meta( $item->ID, 'usage_count', true );
        return esc_html( $count ?: '0' );
    }

    public function column_actions( $item ): string {
        $edit_url = admin_url( 'post.php?post=' . $item->ID . '&action=edit' );
        $rule_url = admin_url( 'admin.php?page=ccm-rules&action=new' );

        return '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( '編輯', 'ccm' ) . '</a> '
             . '<a href="' . esc_url( $rule_url ) . '" class="button button-small">' . esc_html__( '設定分潤', 'ccm' ) . '</a>';
    }

    public function column_default( $item, $column_name ): string {
        return '';
    }
}
