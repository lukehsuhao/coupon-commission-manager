<?php

namespace CouponCommissionManager\Admin\Pages;

use CouponCommissionManager\Models\Partner;
use CouponCommissionManager\Admin\ListTables\PartnersListTable;

class PartnersPage {

    public static function render(): void {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        switch ( $action ) {
            case 'new':
            case 'edit':
                self::render_form();
                break;
            default:
                self::render_list();
                break;
        }
    }

    private static function render_form(): void {
        $id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $partner = $id ? Partner::find( $id ) : null;

        // Handle save
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ccm_save_partner_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['ccm_save_partner_nonce'], 'ccm_save_partner' ) ) {
                wp_die( __( '安全性驗證失敗', 'ccm' ) );
            }

            $data = [
                'name'              => sanitize_text_field( $_POST['name'] ?? '' ),
                'email'             => sanitize_email( $_POST['email'] ?? '' ),
                'phone'             => sanitize_text_field( $_POST['phone'] ?? '' ),
                'bank_name'         => sanitize_text_field( $_POST['bank_name'] ?? '' ),
                'bank_account'      => sanitize_text_field( $_POST['bank_account'] ?? '' ),
                'bank_account_name' => sanitize_text_field( $_POST['bank_account_name'] ?? '' ),
                'notes'             => sanitize_textarea_field( $_POST['notes'] ?? '' ),
                'status'            => in_array( $_POST['status'] ?? '', [ 'active', 'inactive' ], true ) ? $_POST['status'] : 'active',
            ];

            if ( empty( $data['name'] ) ) {
                $error = __( '夥伴姓名為必填欄位', 'ccm' );
            } else {
                if ( $id ) {
                    Partner::update( $id, $data );
                    $message = __( '夥伴已更新', 'ccm' );
                    $partner = Partner::find( $id );
                } else {
                    $id      = Partner::create( $data );
                    $message = __( '夥伴已新增', 'ccm' );
                    $partner = Partner::find( $id );
                }
            }
        }

        include CCM_PLUGIN_DIR . 'templates/admin/partner-form.php';
    }

    private static function render_list(): void {
        // Handle bulk actions
        if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bulk-partners' ) ) {
            $action = $_POST['action'] ?? '';
            $ids    = array_map( 'absint', $_POST['partner_ids'] ?? [] );
            if ( ! empty( $ids ) ) {
                foreach ( $ids as $pid ) {
                    if ( 'activate' === $action ) {
                        Partner::update( $pid, [ 'status' => 'active' ] );
                    } elseif ( 'deactivate' === $action ) {
                        Partner::update( $pid, [ 'status' => 'inactive' ] );
                    }
                }
            }
        }

        // Handle single toggle
        if ( isset( $_GET['toggle'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'ccm_toggle_partner' ) ) {
                $toggle_id = absint( $_GET['toggle'] );
                $p         = Partner::find( $toggle_id );
                if ( $p ) {
                    $new_status = 'active' === $p->status ? 'inactive' : 'active';
                    Partner::update( $toggle_id, [ 'status' => $new_status ] );
                }
            }
        }

        $list_table = new PartnersListTable();
        $list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( '夥伴管理', 'ccm' ) . '</h1>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=ccm-partners&action=new' ) ) . '" class="page-title-action">' . esc_html__( '新增夥伴', 'ccm' ) . '</a>';
        echo '<hr class="wp-header-end">';

        echo '<form method="post">';
        $list_table->display();
        echo '</form>';
        echo '</div>';
    }
}
