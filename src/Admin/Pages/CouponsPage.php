<?php

namespace CouponCommissionManager\Admin\Pages;

use CouponCommissionManager\Admin\ListTables\CouponsListTable;

class CouponsPage {

    public static function render(): void {
        self::render_list();
    }

    private static function render_list(): void {
        $list_table = new CouponsListTable();
        $list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( '折扣碼', 'ccm' ) . '</h1>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=ccm-coupon-edit' ) ) . '" class="page-title-action">' . esc_html__( '新增折扣碼', 'ccm' ) . '</a>';
        echo '<hr class="wp-header-end">';
        $list_table->display();
        echo '</div>';
    }
}
