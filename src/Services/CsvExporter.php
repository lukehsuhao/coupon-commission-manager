<?php

namespace CouponCommissionManager\Services;

use CouponCommissionManager\Models\CommissionLog;
use CouponCommissionManager\Models\Partner;

class CsvExporter {

    public static function register(): void {
        add_action( 'admin_post_ccm_export_csv', [ self::class, 'handle_export' ] );
    }

    public static function handle_export(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( '權限不足', 'ccm' ) );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ccm_export_csv' ) ) {
            wp_die( __( '安全性驗證失敗', 'ccm' ) );
        }

        $args = [
            'partner_id' => absint( $_GET['partner_id'] ?? 0 ) ?: '',
            'coupon_id'  => absint( $_GET['coupon_id'] ?? 0 ) ?: '',
            'status'     => sanitize_text_field( $_GET['status'] ?? '' ),
            'date_from'  => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to'    => sanitize_text_field( $_GET['date_to'] ?? '' ),
            'per_page'   => 99999,
            'offset'     => 0,
        ];

        // Remove empty filters
        $args = array_filter( $args, function ( $v ) {
            return '' !== $v && 0 !== $v;
        } );

        $logs = CommissionLog::all( $args );

        // Cache partner names
        $partner_names = [];

        $filename = 'commission-export-' . date( 'Ymd-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // UTF-8 BOM for Excel
        fwrite( $output, "\xEF\xBB\xBF" );

        // Header row
        fputcsv( $output, [
            '紀錄編號',
            '日期',
            '訂單編號',
            '夥伴名稱',
            '折扣碼',
            '商品名稱',
            '數量',
            '單位分潤',
            '分潤合計',
            '狀態',
            '付款日期',
            '付款備註',
        ] );

        $status_labels = [
            'unpaid' => '未付',
            'paid'   => '已付',
            'void'   => '作廢',
        ];

        foreach ( $logs as $log ) {
            if ( ! isset( $partner_names[ $log->partner_id ] ) ) {
                $partner = Partner::find( $log->partner_id );
                $partner_names[ $log->partner_id ] = $partner ? $partner->name : '未知夥伴';
            }

            fputcsv( $output, [
                $log->id,
                substr( $log->created_at, 0, 10 ),
                '#' . $log->order_id,
                $partner_names[ $log->partner_id ],
                $log->coupon_code,
                $log->product_name,
                $log->quantity,
                $log->commission_per_unit,
                $log->commission_total,
                $status_labels[ $log->status ] ?? $log->status,
                $log->paid_at ? substr( $log->paid_at, 0, 10 ) : '',
                $log->payment_note ?? '',
            ] );
        }

        fclose( $output );
        exit;
    }
}
