<?php

namespace CouponCommissionManager\Admin\Pages;

use CouponCommissionManager\Admin\ListTables\CouponsListTable;

class CouponsPage {

    public static function render(): void {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        switch ( $action ) {
            case 'new':
                self::render_form();
                break;
            default:
                self::render_list();
                break;
        }
    }

    private static function render_form(): void {
        $error   = '';
        $message = '';

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ccm_save_coupon_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['ccm_save_coupon_nonce'], 'ccm_save_coupon' ) ) {
                wp_die( __( '安全性驗證失敗', 'ccm' ) );
            }

            $code          = sanitize_text_field( $_POST['coupon_code'] ?? '' );
            $discount_type = sanitize_text_field( $_POST['discount_type'] ?? 'fixed_cart' );
            $amount        = floatval( $_POST['coupon_amount'] ?? 0 );
            $description   = sanitize_textarea_field( $_POST['description'] ?? '' );

            if ( empty( $code ) ) {
                $error = __( '折扣碼為必填欄位', 'ccm' );
            } else {
                // Check duplicate
                $existing = wc_get_coupon_id_by_code( $code );
                if ( $existing ) {
                    $error = __( '此折扣碼已存在', 'ccm' );
                } else {
                    $coupon = new \WC_Coupon();
                    $coupon->set_code( $code );
                    $coupon->set_discount_type( $discount_type );
                    $coupon->set_amount( $amount );
                    $coupon->set_description( $description );
                    $coupon->save();

                    $message = sprintf(
                        __( '折扣碼「%s」已建立！', 'ccm' ),
                        $code
                    );
                    $message .= ' <a href="' . esc_url( admin_url( 'admin.php?page=ccm-rules&action=new' ) ) . '">'
                             . __( '前往設定分潤規則 →', 'ccm' ) . '</a>';
                }
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( '新增折扣碼', 'ccm' ) . '</h1>';

        if ( $error ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
        }
        if ( $message ) {
            echo '<div class="notice notice-success"><p>' . wp_kses_post( $message ) . '</p></div>';
        }

        echo '<form method="post">';
        wp_nonce_field( 'ccm_save_coupon', 'ccm_save_coupon_nonce' );
        echo '<table class="form-table">';

        echo '<tr><th scope="row"><label for="coupon_code">' . esc_html__( '折扣碼', 'ccm' ) . ' <span class="required">*</span></label></th>';
        echo '<td><input type="text" id="coupon_code" name="coupon_code" class="regular-text" required style="text-transform:uppercase;">';
        echo '<p class="description">' . esc_html__( '例如：WANG2026、SUMMER-SALE', 'ccm' ) . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="discount_type">' . esc_html__( '折扣類型', 'ccm' ) . '</label></th>';
        echo '<td><select id="discount_type" name="discount_type">';
        echo '<option value="fixed_cart">' . esc_html__( '購物車固定折扣', 'ccm' ) . '</option>';
        echo '<option value="percent">' . esc_html__( '百分比折扣', 'ccm' ) . '</option>';
        echo '<option value="fixed_product">' . esc_html__( '商品固定折扣', 'ccm' ) . '</option>';
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="coupon_amount">' . esc_html__( '折扣金額', 'ccm' ) . '</label></th>';
        echo '<td><input type="number" id="coupon_amount" name="coupon_amount" step="1" min="0" value="0" style="width:150px;">';
        echo '<p class="description">' . esc_html__( '百分比折扣填數字即可（例如 10 = 10%），純分潤用途可填 0', 'ccm' ) . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="description">' . esc_html__( '說明', 'ccm' ) . '</label></th>';
        echo '<td><textarea id="description" name="description" rows="3" class="large-text"></textarea></td></tr>';

        echo '</table>';
        submit_button( __( '建立折扣碼', 'ccm' ) );
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=ccm-coupons' ) ) . '" class="button">' . esc_html__( '返回列表', 'ccm' ) . '</a>';
        echo '</form></div>';
    }

    private static function render_list(): void {
        $list_table = new CouponsListTable();
        $list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( '折扣碼', 'ccm' ) . '</h1>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=ccm-coupons&action=new' ) ) . '" class="page-title-action">' . esc_html__( '新增折扣碼', 'ccm' ) . '</a>';
        echo '<hr class="wp-header-end">';
        $list_table->display();
        echo '</div>';
    }
}
