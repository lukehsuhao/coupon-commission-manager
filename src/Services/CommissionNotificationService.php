<?php

namespace CouponCommissionManager\Services;

use CouponCommissionManager\Models\CommissionLog;
use CouponCommissionManager\Models\Partner;
use CouponCommissionManager\Admin\Pages\SettingsPage;

class CommissionNotificationService {

    /**
     * Get the email data grouped by partner for given log IDs.
     * Returns array of [ partner_id => [ 'partner' => ..., 'logs' => [...], 'total' => ... ] ]
     */
    public static function get_grouped_data( array $log_ids ): array {
        global $wpdb;
        $table = CommissionLog::table();

        if ( empty( $log_ids ) ) {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id IN ({$placeholders})",
            ...$log_ids
        ) );

        // Group by partner
        $grouped = [];
        foreach ( $logs as $log ) {
            $pid = (int) $log->partner_id;
            if ( ! isset( $grouped[ $pid ] ) ) {
                $partner = Partner::find( $pid );
                $grouped[ $pid ] = [
                    'partner' => $partner,
                    'logs'    => [],
                    'total'   => 0,
                ];
            }
            $grouped[ $pid ]['logs'][] = $log;
            $grouped[ $pid ]['total'] += (float) $log->commission_total;
        }

        return $grouped;
    }

    /**
     * Build commission details text table from logs.
     */
    public static function build_details_text( array $logs ): string {
        $lines = [];
        $lines[] = str_pad( '商品', 30 ) . str_pad( '數量', 6 ) . str_pad( '單筆分潤', 12 ) . '小計';
        $lines[] = str_repeat( '-', 60 );

        foreach ( $logs as $log ) {
            $name = mb_substr( $log->product_name, 0, 28 );
            $lines[] = str_pad( $name, 30 - mb_strlen( $name ) + strlen( $name ) )
                     . str_pad( $log->quantity, 6 )
                     . str_pad( 'NT$ ' . number_format( (float) $log->commission_per_unit ), 12 )
                     . 'NT$ ' . number_format( (float) $log->commission_total );
        }

        return implode( "\n", $lines );
    }

    /**
     * Build HTML commission details table from logs.
     */
    public static function build_details_html( array $logs ): string {
        $html = '<table style="border-collapse:collapse;width:100%;max-width:600px;">';
        $html .= '<thead><tr style="background:#f5f5f5;">';
        $html .= '<th style="padding:8px;border:1px solid #ddd;text-align:left;">商品</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;text-align:center;">數量</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;text-align:right;">單筆分潤</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;text-align:right;">小計</th>';
        $html .= '</tr></thead><tbody>';

        foreach ( $logs as $log ) {
            $html .= '<tr>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html( $log->product_name ) . '</td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;text-align:center;">' . esc_html( $log->quantity ) . '</td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;text-align:right;">NT$ ' . number_format( (float) $log->commission_per_unit ) . '</td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;text-align:right;">NT$ ' . number_format( (float) $log->commission_total ) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Replace variables in template.
     */
    public static function replace_vars( string $template, array $vars ): string {
        foreach ( $vars as $key => $value ) {
            $template = str_replace( '{' . $key . '}', $value, $template );
        }
        return $template;
    }

    /**
     * Get the configured subject and body templates.
     */
    public static function get_templates(): array {
        $settings = get_option( 'ccm_settings', [] );
        return [
            'subject' => ! empty( $settings['email_commission_subject'] )
                ? $settings['email_commission_subject']
                : SettingsPage::default_commission_subject(),
            'body'    => ! empty( $settings['email_commission_template'] )
                ? $settings['email_commission_template']
                : SettingsPage::default_commission_template(),
        ];
    }

    /**
     * Preview the email for each partner (returns array keyed by partner_id).
     */
    public static function preview( array $log_ids ): array {
        $grouped   = self::get_grouped_data( $log_ids );
        $templates = self::get_templates();
        $previews  = [];

        foreach ( $grouped as $pid => $data ) {
            $partner = $data['partner'];
            if ( ! $partner ) continue;

            $vars = [
                'partner_name'       => $partner->name,
                'commission_details' => self::build_details_text( $data['logs'] ),
                'commission_total'   => number_format( $data['total'] ),
                'site_name'          => get_bloginfo( 'name' ),
                'site_url'           => home_url(),
            ];

            $previews[ $pid ] = [
                'partner_name'  => $partner->name,
                'partner_email' => $partner->email,
                'subject'       => self::replace_vars( $templates['subject'], $vars ),
                'body'          => self::replace_vars( $templates['body'], $vars ),
                'log_count'     => count( $data['logs'] ),
                'total'         => $data['total'],
            ];
        }

        return $previews;
    }

    /**
     * Send commission notifications and mark logs as paid.
     * $overrides: [ partner_id => [ 'subject' => ..., 'body' => ... ] ]
     */
    public static function send_and_mark_paid( array $log_ids, array $overrides = [] ): array {
        $grouped = self::get_grouped_data( $log_ids );
        $templates = self::get_templates();
        $results = [];

        foreach ( $grouped as $pid => $data ) {
            $partner = $data['partner'];
            if ( ! $partner || empty( $partner->email ) ) {
                $results[ $pid ] = [ 'sent' => false, 'error' => '夥伴 Email 不存在' ];
                continue;
            }

            // Use overrides if provided, otherwise use templates
            if ( isset( $overrides[ $pid ] ) ) {
                $subject = $overrides[ $pid ]['subject'];
                $body    = $overrides[ $pid ]['body'];
            } else {
                $vars = [
                    'partner_name'       => $partner->name,
                    'commission_details' => self::build_details_text( $data['logs'] ),
                    'commission_total'   => number_format( $data['total'] ),
                    'site_name'          => get_bloginfo( 'name' ),
                    'site_url'           => home_url(),
                ];
                $subject = self::replace_vars( $templates['subject'], $vars );
                $body    = self::replace_vars( $templates['body'], $vars );
            }

            // Mark all logs as paid
            foreach ( $data['logs'] as $log ) {
                CommissionLog::mark_paid( (int) $log->id, get_current_user_id(), '分潤通知信已寄出' );
            }

            // Send email
            $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
            $html_body = nl2br( esc_html( $body ) );

            $sent = wp_mail( $partner->email, $subject, $html_body, $headers );

            $results[ $pid ] = [
                'sent'         => $sent,
                'partner_name' => $partner->name,
                'email'        => $partner->email,
                'log_count'    => count( $data['logs'] ),
                'total'        => $data['total'],
            ];
        }

        return $results;
    }
}
