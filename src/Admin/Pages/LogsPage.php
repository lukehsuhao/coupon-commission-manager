<?php

namespace CouponCommissionManager\Admin\Pages;

use CouponCommissionManager\Models\CommissionLog;
use CouponCommissionManager\Services\PaymentService;
use CouponCommissionManager\Admin\ListTables\LogsListTable;

class LogsPage {

    public static function render(): void {
        // Handle single mark paid
        if ( isset( $_GET['mark_paid'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'ccm_mark_paid' ) && current_user_can( 'manage_woocommerce' ) ) {
                PaymentService::mark_paid( absint( $_GET['mark_paid'] ) );
            }
        }

        // Handle single mark void
        if ( isset( $_GET['mark_void'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'ccm_mark_void' ) && current_user_can( 'manage_woocommerce' ) ) {
                PaymentService::mark_void( absint( $_GET['mark_void'] ) );
            }
        }

        // Handle bulk actions
        if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bulk-commission_logs' ) ) {
            $action = $_POST['action'] ?? '';
            $ids    = array_map( 'absint', $_POST['log_ids'] ?? [] );
            if ( ! empty( $ids ) && 'mark_paid' === $action ) {
                PaymentService::batch_mark_paid( $ids );
            }
        }

        $list_table = new LogsListTable();
        $list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( '分潤紀錄', 'ccm' ) . '</h1>';

        // Filters
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ccm-logs">';
        $list_table->extra_filters();
        echo '</form>';

        echo '<form method="post">';
        $list_table->display();
        echo '</form>';
        echo '</div>';
    }
}
