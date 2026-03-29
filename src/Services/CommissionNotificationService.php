<?php

namespace CouponCommissionManager\Services;

use CouponCommissionManager\Models\CommissionLog;
use CouponCommissionManager\Models\Partner;
use CouponCommissionManager\Admin\Pages\SettingsPage;

class CommissionNotificationService {

    /**
     * Get the email data grouped by partner for given log IDs.
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
     * Build HTML commission details table from logs.
     */
    public static function build_details_html( array $logs ): string {
        $html  = '<table style="border-collapse:collapse;width:100%;max-width:600px;font-size:14px;">';
        $html .= '<thead><tr style="background:#f8f9fa;">';
        $html .= '<th style="padding:10px 12px;border:1px solid #e2e8f0;text-align:left;font-weight:600;">商品</th>';
        $html .= '<th style="padding:10px 12px;border:1px solid #e2e8f0;text-align:center;font-weight:600;width:60px;">數量</th>';
        $html .= '<th style="padding:10px 12px;border:1px solid #e2e8f0;text-align:right;font-weight:600;width:110px;">單筆分潤</th>';
        $html .= '<th style="padding:10px 12px;border:1px solid #e2e8f0;text-align:right;font-weight:600;width:110px;">小計</th>';
        $html .= '</tr></thead><tbody>';

        $total = 0;
        foreach ( $logs as $log ) {
            $html .= '<tr>';
            $html .= '<td style="padding:10px 12px;border:1px solid #e2e8f0;">' . esc_html( $log->product_name ) . '</td>';
            $html .= '<td style="padding:10px 12px;border:1px solid #e2e8f0;text-align:center;">' . esc_html( $log->quantity ) . '</td>';
            $html .= '<td style="padding:10px 12px;border:1px solid #e2e8f0;text-align:right;">NT$ ' . number_format( (float) $log->commission_per_unit ) . '</td>';
            $html .= '<td style="padding:10px 12px;border:1px solid #e2e8f0;text-align:right;">NT$ ' . number_format( (float) $log->commission_total ) . '</td>';
            $html .= '</tr>';
            $total += (float) $log->commission_total;
        }

        $html .= '</tbody>';
        $html .= '<tfoot><tr style="background:#f8f9fa;font-weight:600;">';
        $html .= '<td colspan="3" style="padding:10px 12px;border:1px solid #e2e8f0;text-align:right;">分潤總金額</td>';
        $html .= '<td style="padding:10px 12px;border:1px solid #e2e8f0;text-align:right;">NT$ ' . number_format( $total ) . '</td>';
        $html .= '</tr></tfoot>';
        $html .= '</table>';

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

            $details_html = self::build_details_html( $data['logs'] );

            $vars = [
                'partner_name'       => $partner->name,
                'commission_details' => $details_html,
                'commission_total'   => number_format( $data['total'] ),
                'site_name'          => get_bloginfo( 'name' ),
                'site_url'           => home_url(),
            ];

            $body_template = $templates['body'];
            // Split body into text parts and the details table
            $body_html = self::replace_vars( $body_template, $vars );
            // Convert newlines to <br> for the text portions (but not the HTML table)
            $body_html = self::smart_nl2br( $body_html );

            $previews[ $pid ] = [
                'partner_name'  => $partner->name,
                'partner_email' => $partner->email,
                'subject'       => self::replace_vars( $templates['subject'], $vars ),
                'body_html'     => $body_html,
                'body_template' => $templates['body'],
                'details_html'  => $details_html,
                'log_count'     => count( $data['logs'] ),
                'total'         => $data['total'],
            ];
        }

        return $previews;
    }

    /**
     * Convert newlines to <br> but skip content inside <table> tags.
     */
    private static function smart_nl2br( string $html ): string {
        // Split by table tags, only nl2br on non-table parts
        $parts = preg_split( '/(<table[\s\S]*?<\/table>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
        $result = '';
        foreach ( $parts as $part ) {
            if ( stripos( $part, '<table' ) === 0 ) {
                $result .= $part;
            } else {
                $result .= nl2br( esc_html( $part ) );
            }
        }
        return $result;
    }

    /**
     * Send commission notifications and mark logs as paid.
     * $overrides: [ partner_id => [ 'subject' => ..., 'body_text' => ..., 'details_html' => ... ] ]
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

            $details_html = self::build_details_html( $data['logs'] );

            if ( isset( $overrides[ $pid ] ) ) {
                $subject   = $overrides[ $pid ]['subject'];
                $body_text = $overrides[ $pid ]['body_text'] ?? '';

                // Replace {commission_details} in the text with the HTML table
                $vars = [
                    'commission_details' => $details_html,
                    'commission_total'   => number_format( $data['total'] ),
                ];
                $body_html = self::smart_nl2br( self::replace_vars( $body_text, $vars ) );
            } else {
                $vars = [
                    'partner_name'       => $partner->name,
                    'commission_details' => $details_html,
                    'commission_total'   => number_format( $data['total'] ),
                    'site_name'          => get_bloginfo( 'name' ),
                    'site_url'           => home_url(),
                ];
                $subject   = self::replace_vars( $templates['subject'], $vars );
                $body_html = self::smart_nl2br( self::replace_vars( $templates['body'], $vars ) );
            }

            // Mark all logs as paid
            foreach ( $data['logs'] as $log ) {
                CommissionLog::mark_paid( (int) $log->id, get_current_user_id(), '分潤通知信已寄出' );
            }

            // Wrap in a simple email layout
            $email_html = '<div style="font-family:sans-serif;font-size:14px;line-height:1.6;color:#333;max-width:640px;margin:0 auto;">';
            $email_html .= $body_html;
            $email_html .= '</div>';

            $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
            $sent = wp_mail( $partner->email, $subject, $email_html, $headers );

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
