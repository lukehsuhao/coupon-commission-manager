<?php

namespace CouponCommissionManager\Admin\Pages;

use CouponCommissionManager\Models\CommissionLog;
use CouponCommissionManager\Models\Partner;
use CouponCommissionManager\Models\CommissionRule;

class DashboardPage {

    public static function render(): void {
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
        $date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';

        $totals           = CommissionLog::get_dashboard_totals( $date_from, $date_to );
        $partner_count    = Partner::count_active();
        $rule_count       = CommissionRule::count_all();
        $partner_summaries = CommissionLog::get_partner_summaries( $date_from, $date_to );

        include CCM_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
}
