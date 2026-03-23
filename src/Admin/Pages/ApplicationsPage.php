<?php

namespace CouponCommissionManager\Admin\Pages;

use CouponCommissionManager\Models\Application;
use CouponCommissionManager\Services\ApplicationService;
use CouponCommissionManager\Admin\ListTables\ApplicationsListTable;

class ApplicationsPage {

    public static function render(): void {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        switch ( $action ) {
            case 'view':
                self::render_view();
                break;
            case 'approve':
                self::handle_approve();
                break;
            case 'reject':
                self::handle_reject();
                break;
            default:
                self::render_list();
                break;
        }
    }

    private static function render_view(): void {
        $id          = absint( $_GET['id'] ?? 0 );
        $application = $id ? Application::find( $id ) : null;
        $message     = '';
        $error       = '';

        if ( ! $application ) {
            wp_die( __( '找不到此申請', 'ccm' ) );
        }

        // Handle approve/reject from the view page form
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            $admin_note = sanitize_textarea_field( $_POST['admin_note'] ?? '' );

            if ( isset( $_POST['ccm_do_approve'] ) && wp_verify_nonce( $_POST['ccm_review_nonce'] ?? '', 'ccm_review_application' ) ) {
                $result  = ApplicationService::approve( $application, $admin_note );
                $message = sprintf(
                    __( '已通過！夥伴已建立，折扣碼：%s', 'ccm' ),
                    $result['coupon_code']
                );
                if ( $result['coupon_modified'] ) {
                    $message .= ' ' . __( '（折扣碼已被使用，已自動調整）', 'ccm' );
                }
                $application = Application::find( $id ); // reload
            } elseif ( isset( $_POST['ccm_do_reject'] ) && wp_verify_nonce( $_POST['ccm_review_nonce'] ?? '', 'ccm_review_application' ) ) {
                ApplicationService::reject( $application, $admin_note );
                $message     = __( '已駁回，通知信已發送。', 'ccm' );
                $application = Application::find( $id );
            }
        }

        include CCM_PLUGIN_DIR . 'templates/admin/application-view.php';
    }

    private static function handle_approve(): void {
        $id = absint( $_GET['id'] ?? 0 );
        if ( ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ccm_approve_application' ) ) {
            wp_die( __( '安全性驗證失敗', 'ccm' ) );
        }

        $application = Application::find( $id );
        if ( ! $application || 'pending' !== $application->status ) {
            wp_redirect( admin_url( 'admin.php?page=ccm-applications' ) );
            exit;
        }

        $result = ApplicationService::approve( $application );

        $redirect_args = [ 'page' => 'ccm-applications', 'approved' => 1, 'coupon' => $result['coupon_code'] ];
        if ( $result['coupon_modified'] ) {
            $redirect_args['modified'] = 1;
        }
        wp_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function handle_reject(): void {
        $id = absint( $_GET['id'] ?? 0 );
        if ( ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ccm_reject_application' ) ) {
            wp_die( __( '安全性驗證失敗', 'ccm' ) );
        }

        $application = Application::find( $id );
        if ( ! $application || 'pending' !== $application->status ) {
            wp_redirect( admin_url( 'admin.php?page=ccm-applications' ) );
            exit;
        }

        ApplicationService::reject( $application );
        wp_redirect( admin_url( 'admin.php?page=ccm-applications&rejected=1' ) );
        exit;
    }

    private static function render_list(): void {
        $list_table = new ApplicationsListTable();
        $list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( '夥伴申請', 'ccm' ) . '</h1>';

        if ( isset( $_GET['approved'] ) ) {
            $coupon = sanitize_text_field( $_GET['coupon'] ?? '' );
            $msg    = sprintf( __( '申請已通過！折扣碼：%s', 'ccm' ), '<code>' . esc_html( $coupon ) . '</code>' );
            if ( isset( $_GET['modified'] ) ) {
                $msg .= ' ' . __( '（原申請碼已被使用，已自動調整）', 'ccm' );
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( $msg ) . '</p></div>';
        }
        if ( isset( $_GET['rejected'] ) ) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( '申請已駁回，通知信已發送。', 'ccm' ) . '</p></div>';
        }

        $list_table->views();
        $list_table->display();
        echo '</div>';
    }
}
