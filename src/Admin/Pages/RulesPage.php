<?php

namespace CouponCommissionManager\Admin\Pages;

use CouponCommissionManager\Models\CommissionRule;
use CouponCommissionManager\Models\Partner;
use CouponCommissionManager\Admin\ListTables\RulesListTable;

class RulesPage {

    public static function render(): void {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        switch ( $action ) {
            case 'new':
            case 'edit':
                self::render_form();
                break;
            case 'delete_group':
                self::handle_delete_group();
                break;
            default:
                self::render_list();
                break;
        }
    }

    private static function render_form(): void {
        $error   = '';
        $message = '';

        // Editing existing group?
        $edit_partner_id = absint( $_GET['partner_id'] ?? 0 );
        $edit_coupon_id  = absint( $_GET['coupon_id'] ?? 0 );
        $existing_rules  = [];
        $is_copy         = false;

        // Copy from another rule group?
        $copy_from_partner = absint( $_GET['copy_from_partner'] ?? 0 );
        $copy_from_coupon  = absint( $_GET['copy_from_coupon'] ?? 0 );

        if ( $copy_from_partner && $copy_from_coupon ) {
            $is_copy   = true;
            $all_rules = CommissionRule::find_by_coupon( $copy_from_coupon );
            foreach ( $all_rules as $r ) {
                if ( (int) $r->partner_id === $copy_from_partner ) {
                    $existing_rules[] = $r;
                }
            }
            // Clear partner/coupon so the form shows as "new"
            $edit_partner_id = 0;
            $edit_coupon_id  = 0;
        } elseif ( $edit_partner_id && $edit_coupon_id ) {
            $all_rules = CommissionRule::find_by_coupon( $edit_coupon_id );
            foreach ( $all_rules as $r ) {
                if ( (int) $r->partner_id === $edit_partner_id ) {
                    $existing_rules[] = $r;
                }
            }
        }

        // Handle save
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ccm_save_rules_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['ccm_save_rules_nonce'], 'ccm_save_rules' ) ) {
                wp_die( __( '安全性驗證失敗', 'ccm' ) );
            }

            $partner_id  = absint( $_POST['partner_id'] ?? 0 );
            $coupon_id   = absint( $_POST['coupon_id'] ?? 0 );
            $product_ids = array_map( 'absint', $_POST['product_ids'] ?? [] );
            $amounts     = array_map( 'floatval', $_POST['amounts'] ?? [] );

            if ( ! $partner_id || ! $coupon_id ) {
                $error = __( '請選擇夥伴和折扣碼', 'ccm' );
            } elseif ( empty( $product_ids ) ) {
                $error = __( '請至少新增一筆分潤設定', 'ccm' );
            } else {
                // Delete old rules for this partner+coupon
                if ( $edit_partner_id && $edit_coupon_id ) {
                    CommissionRule::delete_by_partner_coupon( $edit_partner_id, $edit_coupon_id );
                }

                $has_error    = false;
                $saved_count  = 0;
                for ( $i = 0; $i < count( $product_ids ); $i++ ) {
                    $amt = (float) ( $amounts[ $i ] ?? 0 );
                    $pid = (int) $product_ids[ $i ];
                    // Negative values are invalid
                    if ( $amt < 0 ) {
                        continue;
                    }
                    // Skip 0 for product-specific rules (meaningless); allow 0 for default (product_id=0)
                    if ( $amt === 0.0 && $pid !== 0 ) {
                        continue;
                    }
                    $result = CommissionRule::create( [
                        'partner_id'        => $partner_id,
                        'coupon_id'         => $coupon_id,
                        'product_id'        => $pid,
                        'commission_amount' => $amt,
                    ] );
                    if ( false === $result ) {
                        $has_error = true;
                    } else {
                        $saved_count++;
                    }
                }

                if ( $has_error ) {
                    $error = __( '部分規則儲存失敗（可能有重複的商品設定）', 'ccm' );
                } else {
                    $message = __( '分潤規則已儲存', 'ccm' );
                }

                // Reload
                $edit_partner_id = $partner_id;
                $edit_coupon_id  = $coupon_id;
                $existing_rules  = [];
                $all_rules       = CommissionRule::find_by_coupon( $edit_coupon_id );
                foreach ( $all_rules as $r ) {
                    if ( (int) $r->partner_id === $edit_partner_id ) {
                        $existing_rules[] = $r;
                    }
                }
            }
        }

        $partners = Partner::all( [ 'status' => 'active', 'per_page' => 100 ] );
        $is_edit  = ! empty( $existing_rules ) && ! $is_copy;
        $has_prefill = ! empty( $existing_rules );

        include CCM_PLUGIN_DIR . 'templates/admin/rule-form.php';
    }

    private static function handle_delete_group(): void {
        $partner_id = absint( $_GET['partner_id'] ?? 0 );
        $coupon_id  = absint( $_GET['coupon_id'] ?? 0 );

        if ( ! $partner_id || ! $coupon_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ccm_delete_rule_group' ) ) {
            wp_die( __( '安全性驗證失敗', 'ccm' ) );
        }

        CommissionRule::delete_by_partner_coupon( $partner_id, $coupon_id );
        wp_redirect( admin_url( 'admin.php?page=ccm-rules&deleted=1' ) );
        exit;
    }

    private static function render_list(): void {
        $list_table = new RulesListTable();
        $list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( '分潤規則', 'ccm' ) . '</h1>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=ccm-rules&action=new' ) ) . '" class="page-title-action">' . esc_html__( '新增規則', 'ccm' ) . '</a>';
        echo '<hr class="wp-header-end">';

        if ( isset( $_GET['deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '規則已刪除', 'ccm' ) . '</p></div>';
        }

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ccm-rules">';
        $list_table->display();
        echo '</form>';
        echo '</div>';
    }
}
