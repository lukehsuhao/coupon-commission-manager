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
        $th = 'padding:10px 12px;border:1px solid #e2e8f0;font-weight:600;';
        $td = 'padding:10px 12px;border:1px solid #e2e8f0;';

        $html  = '<table style="border-collapse:collapse;width:100%;max-width:700px;font-size:14px;">';
        $html .= '<thead><tr style="background:#f8f9fa;">';
        $html .= '<th style="' . $th . 'text-align:left;">日期</th>';
        $html .= '<th style="' . $th . 'text-align:left;">購買人</th>';
        $html .= '<th style="' . $th . 'text-align:left;">商品</th>';
        $html .= '<th style="' . $th . 'text-align:center;width:50px;">數量</th>';
        $html .= '<th style="' . $th . 'text-align:right;width:100px;">單筆分潤</th>';
        $html .= '<th style="' . $th . 'text-align:right;width:100px;">小計</th>';
        $html .= '</tr></thead><tbody>';

        $total = 0;
        foreach ( $logs as $log ) {
            $order_date = substr( $log->created_at, 0, 10 );
            $buyer_name = self::get_anonymized_buyer_name( (int) $log->order_id );

            $html .= '<tr>';
            $html .= '<td style="' . $td . '">' . esc_html( $order_date ) . '</td>';
            $html .= '<td style="' . $td . '">' . esc_html( $buyer_name ) . '</td>';
            $html .= '<td style="' . $td . '">' . esc_html( $log->product_name ) . '</td>';
            $html .= '<td style="' . $td . 'text-align:center;">' . esc_html( $log->quantity ) . '</td>';
            $html .= '<td style="' . $td . 'text-align:right;">NT$ ' . number_format( (float) $log->commission_per_unit ) . '</td>';
            $html .= '<td style="' . $td . 'text-align:right;">NT$ ' . number_format( (float) $log->commission_total ) . '</td>';
            $html .= '</tr>';
            $total += (float) $log->commission_total;
        }

        $html .= '</tbody>';
        $html .= '<tfoot><tr style="background:#f8f9fa;font-weight:600;">';
        $html .= '<td colspan="5" style="' . $td . 'text-align:right;">分潤總金額</td>';
        $html .= '<td style="' . $td . 'text-align:right;">NT$ ' . number_format( $total ) . '</td>';
        $html .= '</tr></tfoot>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Get buyer name from order, with anonymization.
     * 陳小明 → 陳○明, 小明 → 小○, John Smith → J○○○ Smith
     */
    public static function get_anonymized_buyer_name( int $order_id ): string {
        static $cache = [];
        if ( isset( $cache[ $order_id ] ) ) {
            return $cache[ $order_id ];
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $cache[ $order_id ] = '—';
            return '—';
        }

        $last  = $order->get_billing_last_name();
        $first = $order->get_billing_first_name();
        $full  = trim( $last . $first );

        if ( empty( $full ) ) {
            $full = trim( $first ?: $order->get_formatted_billing_full_name() );
        }

        $cache[ $order_id ] = self::anonymize_name( $full );
        return $cache[ $order_id ];
    }

    /**
     * Anonymize a name by replacing middle characters with ○.
     * 3+ chars CJK: 陳小明 → 陳○明
     * 2 chars CJK: 小明 → 小○
     * 1 char: keep as-is
     * Latin names: John → J○○n
     */
    public static function anonymize_name( string $name ): string {
        $name = trim( $name );
        if ( empty( $name ) ) return '—';

        $len = mb_strlen( $name );

        if ( $len <= 1 ) {
            return $name;
        }

        if ( $len === 2 ) {
            return mb_substr( $name, 0, 1 ) . '○';
        }

        // 3+ characters: keep first and last, replace middle with ○
        $first = mb_substr( $name, 0, 1 );
        $last  = mb_substr( $name, -1, 1 );
        $middle_len = $len - 2;
        return $first . str_repeat( '○', $middle_len ) . $last;
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
            $send_to = ! empty( $overrides[ $pid ]['email'] ) ? $overrides[ $pid ]['email'] : $partner->email;

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
            $sent = wp_mail( $send_to, $subject, $email_html, $headers );

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
