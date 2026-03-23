<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1><?php esc_html_e( '折扣碼分潤管理 — 總覽', 'ccm' ); ?></h1>

    <!-- Summary Cards -->
    <div class="ccm-cards">
        <div class="ccm-card">
            <h3><?php esc_html_e( '未結算總額', 'ccm' ); ?></h3>
            <p class="ccm-card-value ccm-unpaid">NT$<?php echo esc_html( number_format( $totals->total_unpaid, 0 ) ); ?></p>
        </div>
        <div class="ccm-card">
            <h3><?php esc_html_e( '已結算總額', 'ccm' ); ?></h3>
            <p class="ccm-card-value ccm-paid">NT$<?php echo esc_html( number_format( $totals->total_paid, 0 ) ); ?></p>
        </div>
        <div class="ccm-card">
            <h3><?php esc_html_e( '活躍夥伴', 'ccm' ); ?></h3>
            <p class="ccm-card-value"><?php echo esc_html( $partner_count ); ?></p>
        </div>
        <div class="ccm-card">
            <h3><?php esc_html_e( '分潤規則數', 'ccm' ); ?></h3>
            <p class="ccm-card-value"><?php echo esc_html( $rule_count ); ?></p>
        </div>
    </div>

    <!-- Date Filter -->
    <form method="get" class="ccm-date-filter">
        <input type="hidden" name="page" value="ccm-dashboard">
        <label><?php esc_html_e( '日期區間：', 'ccm' ); ?></label>
        <input type="text" name="date_from" class="ccm-datepicker" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( '起始日期', 'ccm' ); ?>" style="width:120px;">
        ~
        <input type="text" name="date_to" class="ccm-datepicker" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( '結束日期', 'ccm' ); ?>" style="width:120px;">
        <?php submit_button( __( '篩選', 'ccm' ), 'secondary', 'filter', false ); ?>
    </form>

    <!-- Partner Summary Table -->
    <?php if ( ! empty( $partner_summaries ) ) : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( '夥伴', 'ccm' ); ?></th>
                <th style="width:150px;"><?php esc_html_e( '未結算', 'ccm' ); ?></th>
                <th style="width:150px;"><?php esc_html_e( '已結算', 'ccm' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( '操作', 'ccm' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $partner_summaries as $ps ) : ?>
            <tr>
                <td><strong><?php echo esc_html( $ps->partner_name ); ?></strong></td>
                <td class="ccm-unpaid">NT$<?php echo esc_html( number_format( $ps->unpaid_total, 0 ) ); ?></td>
                <td>NT$<?php echo esc_html( number_format( $ps->paid_total, 0 ) ); ?></td>
                <td>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ccm-logs&partner_id=' . $ps->partner_id ) ); ?>" class="button button-small">
                        <?php esc_html_e( '查看明細', 'ccm' ); ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p><?php esc_html_e( '目前沒有分潤紀錄。', 'ccm' ); ?></p>
    <?php endif; ?>

    <!-- Export Button -->
    <div style="margin-top:16px;">
        <?php
        $export_url = add_query_arg( [
            'action'    => 'ccm_export_csv',
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'status'    => 'unpaid',
            '_wpnonce'  => wp_create_nonce( 'ccm_export_csv' ),
        ], admin_url( 'admin-post.php' ) );
        ?>
        <a href="<?php echo esc_url( $export_url ); ?>" class="button">
            <?php esc_html_e( '匯出未結算 CSV', 'ccm' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ccm-logs&status=unpaid' ) ); ?>" class="button button-primary">
            <?php esc_html_e( '前往批次結算', 'ccm' ); ?>
        </a>
    </div>
</div>
