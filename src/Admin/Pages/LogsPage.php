<?php

namespace CouponCommissionManager\Admin\Pages;

use CouponCommissionManager\Models\CommissionLog;
use CouponCommissionManager\Services\PaymentService;
use CouponCommissionManager\Admin\ListTables\LogsListTable;

class LogsPage {

    public static function render(): void {
        // Handle single mark void
        if ( isset( $_GET['mark_void'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'ccm_mark_void' ) && current_user_can( 'manage_woocommerce' ) ) {
                PaymentService::mark_void( absint( $_GET['mark_void'] ) );
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

        echo '<form method="post" id="ccm-logs-form">';
        $list_table->display();
        echo '</form>';

        // Commission email modal
        self::render_email_modal();

        echo '</div>';
    }

    private static function render_email_modal(): void {
        ?>
        <div id="ccm-email-modal" style="display:none;">
            <div class="ccm-modal-overlay"></div>
            <div class="ccm-modal-content">
                <div class="ccm-modal-header">
                    <h2><?php esc_html_e( '分潤通知信', 'ccm' ); ?></h2>
                    <button type="button" class="ccm-modal-close">&times;</button>
                </div>
                <div class="ccm-modal-body" id="ccm-email-modal-body">
                    <p class="ccm-modal-loading"><?php esc_html_e( '載入中...', 'ccm' ); ?></p>
                </div>
                <div class="ccm-modal-footer">
                    <button type="button" class="button" id="ccm-modal-cancel"><?php esc_html_e( '取消', 'ccm' ); ?></button>
                    <button type="button" class="button button-primary" id="ccm-modal-send">
                        <?php esc_html_e( '確認寄出並標記已付', 'ccm' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <style>
            #ccm-email-modal .ccm-modal-overlay {
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.5); z-index: 100000;
            }
            #ccm-email-modal .ccm-modal-content {
                position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
                background: #fff; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                z-index: 100001; width: 90%; max-width: 750px; max-height: 85vh;
                display: flex; flex-direction: column;
            }
            #ccm-email-modal .ccm-modal-header {
                display: flex; justify-content: space-between; align-items: center;
                padding: 16px 24px; border-bottom: 1px solid #ddd;
            }
            #ccm-email-modal .ccm-modal-header h2 { margin: 0; }
            #ccm-email-modal .ccm-modal-close {
                background: none; border: none; font-size: 24px; cursor: pointer; color: #666;
            }
            #ccm-email-modal .ccm-modal-body {
                padding: 24px; overflow-y: auto; flex: 1;
            }
            #ccm-email-modal .ccm-modal-footer {
                padding: 16px 24px; border-top: 1px solid #ddd;
                display: flex; justify-content: flex-end; gap: 12px;
            }
            #ccm-email-modal .ccm-partner-block {
                border: 1px solid #ddd; border-radius: 6px; padding: 16px; margin-bottom: 16px;
            }
            #ccm-email-modal .ccm-partner-block h3 {
                margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;
            }
            #ccm-email-modal .ccm-partner-block .ccm-badge {
                background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 10px; font-size: 12px;
            }
            #ccm-email-modal label { display: block; margin-bottom: 4px; font-weight: 600; }
            #ccm-email-modal input[type="text"],
            #ccm-email-modal input[type="email"] {
                width: 100%; padding: 6px 10px; margin-bottom: 12px; box-sizing: border-box;
            }
            #ccm-email-modal textarea {
                width: 100%; min-height: 200px; padding: 8px 10px; font-family: monospace; font-size: 13px; box-sizing: border-box;
            }
            #ccm-email-modal .ccm-modal-loading { text-align: center; color: #666; padding: 40px; }
            #ccm-email-modal .ccm-send-result {
                padding: 12px; margin-bottom: 8px; border-radius: 4px;
            }
            #ccm-email-modal .ccm-send-result.success { background: #d1fae5; color: #065f46; }
            #ccm-email-modal .ccm-send-result.error { background: #fee2e2; color: #991b1b; }
        </style>
        <?php
    }
}
